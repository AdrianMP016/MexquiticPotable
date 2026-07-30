<?php

require_once __DIR__ . '/ConfigBomba.php';

class ShellyClient
{
    private array $config;
    private string $configSource;
    private bool $modoSimulado;
    private ?ConfigBomba $configBomba = null;

    public function __construct(PDO $db)
    {
        $configPath = __DIR__ . '/../Config/shelly.php';
        if (!is_file($configPath)) {
            $configPath = __DIR__ . '/../Config/shelly.example.php';
        }

        $this->config = require $configPath;
        $this->configSource = basename($configPath);
        $this->modoSimulado = !empty($this->config['modo_simulado']);

        if (!$this->modoSimulado) {
            $this->validateConfig();
        } else {
            $this->configBomba = new ConfigBomba($db);
        }
    }

    public function obtenerEstadoRelay(): array
    {
        if ($this->modoSimulado) {
            return ['encendido' => $this->configBomba->obtener('sim_relay_encendido', '0') === '1'];
        }

        $respuesta = $this->rpc((string) $this->config['device_id_relay'], 'Switch.GetStatus', [
            'id' => (int) ($this->config['channel_relay'] ?? 0),
        ]);

        return [
            'encendido' => (bool) ($respuesta['output'] ?? false),
            'raw' => $respuesta,
        ];
    }

    public function encenderRelay(): array
    {
        if ($this->modoSimulado) {
            $this->configBomba->establecer('sim_relay_encendido', '1');
            return ['encendido' => true];
        }

        $respuesta = $this->rpc((string) $this->config['device_id_relay'], 'Switch.Set', [
            'id' => (int) ($this->config['channel_relay'] ?? 0),
            'on' => true,
        ]);

        return ['encendido' => true, 'raw' => $respuesta];
    }

    public function apagarRelay(): array
    {
        if ($this->modoSimulado) {
            $this->configBomba->establecer('sim_relay_encendido', '0');
            return ['encendido' => false];
        }

        $respuesta = $this->rpc((string) $this->config['device_id_relay'], 'Switch.Set', [
            'id' => (int) ($this->config['channel_relay'] ?? 0),
            'on' => false,
        ]);

        return ['encendido' => false, 'raw' => $respuesta];
    }

    public function leerSensorTemperatura(): array
    {
        if ($this->modoSimulado) {
            return [
                'temperatura_c' => $this->configBomba->obtenerNumero('sim_temperatura_c', 22.5),
                'humedad_pct' => null,
            ];
        }

        $respuesta = $this->rpc((string) $this->config['device_id_sensor'], 'Temperature.GetStatus', ['id' => 0]);

        return [
            'temperatura_c' => isset($respuesta['tC']) ? (float) $respuesta['tC'] : null,
            'humedad_pct' => isset($respuesta['rh']) ? (float) $respuesta['rh'] : null,
            'raw' => $respuesta,
        ];
    }

    private function rpc(string $deviceId, string $method, array $params = []): array
    {
        $respuesta = $this->request('POST', '/device/rpc', [
            'id' => $deviceId,
            'auth_key' => $this->config['auth_key'],
            'method' => $method,
            'params' => $params,
        ]);

        if (isset($respuesta['error'])) {
            $mensaje = is_array($respuesta['error']) ? json_encode($respuesta['error']) : (string) $respuesta['error'];
            throw new RuntimeException('Shelly Cloud devolvio un error: ' . $mensaje);
        }

        return (array) ($respuesta['result'] ?? $respuesta);
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $baseUrl = 'https://' . trim((string) $this->config['server'], '/');
        $url = $baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => (bool) ($this->config['verify_ssl'] ?? true),
            CURLOPT_SSL_VERIFYHOST => (bool) ($this->config['verify_ssl'] ?? true) ? 2 : 0,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
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

        return $decoded;
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
