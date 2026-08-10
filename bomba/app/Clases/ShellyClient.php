<?php

require_once __DIR__ . '/ConfigBomba.php';

/**
 * El circuito de la bomba usa 2 canales del Shelly Pro 2 como botones de pulso
 * (Marcha/Paro), no como un rele que se queda encendido. Por eso esta clase
 * solo sabe "mandar un pulso", nunca "leer si la bomba esta prendida" — esa
 * informacion se calcula en Activaciones.php a partir de nuestra propia
 * bitacora de activaciones, no preguntandole al Shelly.
 *
 * Nota de integracion: se usa el endpoint clasico de Shelly Cloud
 * (/device/status, /device/relay/control) con auth_key como campo de
 * formulario — verificado en vivo contra la cuenta real. El endpoint RPC
 * mas nuevo (/device/rpc) exige un esquema de autenticacion distinto
 * (token OAuth, no el "Cloud Key") y no aplica aqui.
 */
class ShellyClient
{
    private array $config;
    private string $configSource;
    private bool $modoSimulado;
    private ConfigBomba $configBomba;

    public function __construct(PDO $db)
    {
        $configPath = __DIR__ . '/../Config/shelly.php';
        if (!is_file($configPath)) {
            $configPath = __DIR__ . '/../Config/shelly.example.php';
        }

        $this->config = require $configPath;
        $this->configSource = basename($configPath);
        $this->modoSimulado = !empty($this->config['modo_simulado']);
        $this->configBomba = new ConfigBomba($db);

        if (!$this->modoSimulado) {
            $this->validateConfig();
        }
    }

    public function pulsarInicio(): array
    {
        return $this->pulsar((int) ($this->config['channel_inicio'] ?? 0), 'marcha');
    }

    public function pulsarParo(): array
    {
        return $this->pulsar((int) ($this->config['channel_paro'] ?? 1), 'paro');
    }

    public function verificarConexion(): array
    {
        if ($this->modoSimulado) {
            return [
                'conectado' => true,
                'en_linea' => true,
                'simulado' => true,
                'consultado_en' => date('Y-m-d H:i:s'),
            ];
        }

        $respuesta = $this->request('POST', '/device/status', [
            'id' => $this->config['device_id_relay'],
            'auth_key' => $this->config['auth_key'],
        ]);

        $status = (array) ($respuesta['device_status'] ?? []);

        return [
            'conectado' => (bool) ($status['cloud']['connected'] ?? false),
            'en_linea' => (bool) ($respuesta['online'] ?? false),
            // No se usa el "_updated" que manda Shelly: se comprobo en vivo que
            // no se actualiza en tiempo real (se queda pegado en la ultima vez
            // que algo cambio en su nube, no en "ahora"). En su lugar se usa la
            // hora de nuestro propio servidor en el momento exacto de esta
            // consulta, que si es confiable.
            'consultado_en' => date('Y-m-d H:i:s'),
        ];
    }

    public function leerSensorTemperatura(): array
    {
        if ($this->modoSimulado) {
            return [
                'temperatura_c' => $this->configBomba->obtenerNumero('sim_temperatura_c', 22.5),
                'humedad_pct' => $this->configBomba->obtenerNumero('sim_humedad_pct', 45),
                'bateria_pct' => 100.0,
                'actualizado_at' => date('Y-m-d H:i:s'),
            ];
        }

        // El sensor solo reporta cada varios minutos y Shelly Cloud tiene limite de
        // peticiones, asi que aqui NO se pregunta a Shelly cada vez que alguien ve
        // el panel — se cachea por 30 segundos, sin importar cuantos navegadores
        // esten viendo la pagina al mismo tiempo.
        $cacheSegundos = 30;
        $leidoEn = $this->configBomba->obtener('cache_sensor_leido_en', '');

        if ($leidoEn !== '' && (time() - strtotime($leidoEn)) < $cacheSegundos) {
            return $this->cacheSensor();
        }

        try {
            $respuesta = $this->request('POST', '/device/status', [
                'id' => $this->config['device_id_sensor'],
                'auth_key' => $this->config['auth_key'],
            ]);
        } catch (Throwable $exception) {
            // Si Shelly Cloud rechaza la peticion (limite excedido, momentaneamente
            // caido, etc.) se devuelve la ultima lectura buena conocida en vez de
            // tronar toda la pantalla.
            if ($leidoEn !== '') {
                return $this->cacheSensor();
            }
            throw $exception;
        }

        $status = (array) ($respuesta['device_status'] ?? []);
        $temperatura = (array) ($status['temperature:0'] ?? []);
        $humedad = (array) ($status['humidity:0'] ?? []);
        $bateria = (array) ($status['devicepower:0']['battery'] ?? []);

        $resultado = [
            'temperatura_c' => isset($temperatura['tC']) ? (float) $temperatura['tC'] : null,
            'humedad_pct' => isset($humedad['rh']) ? (float) $humedad['rh'] : null,
            'bateria_pct' => isset($bateria['percent']) ? (float) $bateria['percent'] : null,
            // El H&T reporta cada varios minutos para ahorrar bateria, no en vivo:
            // esta marca de tiempo es la de su ultimo reporte, no la de "ahora mismo".
            'actualizado_at' => isset($status['_updated']) ? (string) $status['_updated'] : null,
        ];

        $this->configBomba->establecer('cache_sensor_leido_en', date('Y-m-d H:i:s'));
        $this->configBomba->establecer('cache_sensor_temperatura_c', (string) ($resultado['temperatura_c'] ?? ''));
        $this->configBomba->establecer('cache_sensor_humedad_pct', (string) ($resultado['humedad_pct'] ?? ''));
        $this->configBomba->establecer('cache_sensor_bateria_pct', (string) ($resultado['bateria_pct'] ?? ''));
        $this->configBomba->establecer('cache_sensor_actualizado_at', (string) ($resultado['actualizado_at'] ?? ''));

        $resultado['raw'] = $respuesta;

        return $resultado;
    }

    private function cacheSensor(): array
    {
        return [
            'temperatura_c' => ($v = $this->configBomba->obtener('cache_sensor_temperatura_c', '')) !== '' ? (float) $v : null,
            'humedad_pct' => ($v = $this->configBomba->obtener('cache_sensor_humedad_pct', '')) !== '' ? (float) $v : null,
            'bateria_pct' => ($v = $this->configBomba->obtener('cache_sensor_bateria_pct', '')) !== '' ? (float) $v : null,
            'actualizado_at' => ($v = $this->configBomba->obtener('cache_sensor_actualizado_at', '')) !== '' ? $v : null,
        ];
    }

    private function pulsar(int $canal, string $tipo): array
    {
        if ($this->modoSimulado) {
            return ['pulsado' => true, 'canal' => $canal, 'tipo' => $tipo, 'simulado' => true];
        }

        $duracion = max(1, (int) $this->configBomba->obtenerNumero('proteccion_delay_segundos', 2));

        $this->request('POST', '/device/relay/control', [
            'id' => $this->config['device_id_relay'],
            'auth_key' => $this->config['auth_key'],
            'channel' => $canal,
            'turn' => 'on',
        ]);

        // El parametro "timer" del endpoint clasico no se esta respitando en
        // este Shelly Pro 2 (el canal se queda encendido en vez de regresar
        // solo), asi que el pulso se cierra explicitamente desde aqui en vez
        // de confiar en que el propio Shelly lo apague solo.
        sleep($duracion);

        $respuesta = $this->request('POST', '/device/relay/control', [
            'id' => $this->config['device_id_relay'],
            'auth_key' => $this->config['auth_key'],
            'channel' => $canal,
            'turn' => 'off',
        ]);

        return ['pulsado' => true, 'canal' => $canal, 'tipo' => $tipo, 'raw' => $respuesta];
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $baseUrl = 'https://' . trim((string) $this->config['server'], '/');
        $url = $baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => (bool) ($this->config['verify_ssl'] ?? true),
            CURLOPT_SSL_VERIFYHOST => (bool) ($this->config['verify_ssl'] ?? true) ? 2 : 0,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error) {
            throw new RuntimeException('No se pudo conectar con Shelly Cloud: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('Shelly Cloud devolvio un error HTTP ' . $httpCode . ' al consultar ' . $path . '.');
        }

        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('La respuesta de Shelly Cloud no se pudo interpretar.');
        }

        if (empty($decoded['isok'])) {
            $errores = $decoded['errors'] ?? [];
            $mensaje = is_array($errores) ? implode(' | ', $errores) : (string) $errores;
            throw new RuntimeException('Shelly Cloud devolvio un error: ' . $mensaje);
        }

        return (array) ($decoded['data'] ?? []);
    }

    private function validateConfig(): void
    {
        $requeridos = ['server', 'auth_key', 'device_id_relay', 'device_id_sensor'];

        foreach ($requeridos as $clave) {
            if (trim((string) ($this->config[$clave] ?? '')) === '' || $this->config[$clave] === 'PENDIENTE') {
                throw new RuntimeException(
                    'La configuracion de Shelly Cloud esta incompleta en ' . $this->configSource . ' (falta "' . $clave . '").'
                );
            }
        }
    }
}
