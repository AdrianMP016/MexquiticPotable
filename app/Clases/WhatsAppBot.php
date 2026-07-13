<?php

class WhatsAppBot
{
    private const SESION_MINUTOS = 15;

    private PDO $db;
    private Recibos $recibos;
    private WhatsApp $whatsApp;
    private string $rootDir;
    private string $flyerPath;

    public function __construct(PDO $db, Recibos $recibos, WhatsApp $whatsApp)
    {
        $this->db = $db;
        $this->recibos = $recibos;
        $this->whatsApp = $whatsApp;
        $this->rootDir = dirname(__DIR__, 2);
        $this->flyerPath = $this->rootDir . '/assets/img/flyer-transferencia.png';
    }

    public function procesarMensajeEntrante(string $telefonoFrom, string $mensajeCrudo): void
    {
        $telefono = trim($telefonoFrom);

        if ($telefono === '') {
            return;
        }

        $texto = $this->normalizarTexto($mensajeCrudo);
        $opcionesSesion = $this->obtenerSesion($telefono);

        if ($opcionesSesion !== null) {
            $this->responderSesionPendiente($telefono, $texto, $opcionesSesion);
            return;
        }

        if (!$this->esDisparadorRecibo($texto)) {
            return;
        }

        $cuentas = $this->recibos->buscarPorTelefono($telefono);

        if (empty($cuentas)) {
            $this->whatsApp->enviarTexto(
                $telefono,
                'No encontramos tu numero registrado en el sistema de agua potable de Mexquitic de Carmona. Comunicate con tu representante de sector para actualizarlo.'
            );
            return;
        }

        if (count($cuentas) === 1) {
            $this->enviarReciboDeCuenta($telefono, (int) $cuentas[0]['usuario_id']);
            $this->enviarFlyerPagos($telefono);
            return;
        }

        $this->guardarSesion($telefono, $cuentas);
        $this->whatsApp->enviarTexto($telefono, $this->mensajeOpciones($cuentas, true));
    }

    private function esDisparadorRecibo(string $texto): bool
    {
        return $texto === 'recibo'
            || $texto === 'recibos'
            || str_starts_with($texto, 'recibo ')
            || str_starts_with($texto, 'recibos ');
    }

    private function responderSesionPendiente(string $telefono, string $texto, array $opciones): void
    {
        if ($texto === 'todos' || $texto === 'todo') {
            foreach ($opciones as $opcion) {
                $this->enviarReciboDeCuenta($telefono, (int) $opcion['usuario_id']);
            }
            $this->enviarFlyerPagos($telefono);
            $this->borrarSesion($telefono);
            return;
        }

        $seleccionada = $this->resolverOpcion($texto, $opciones);

        if ($seleccionada === null) {
            $this->whatsApp->enviarTexto(
                $telefono,
                "No identifique esa opcion. Responde con el numero, el nombre tal como aparece en la lista, o escribe *todos*:\n\n" . $this->mensajeOpciones($opciones, false)
            );
            return;
        }

        $this->enviarReciboDeCuenta($telefono, (int) $seleccionada['usuario_id']);
        $this->enviarFlyerPagos($telefono);
        $this->borrarSesion($telefono);
    }

    private function resolverOpcion(string $texto, array $opciones): ?array
    {
        if ($texto !== '' && ctype_digit($texto)) {
            $indice = ((int) $texto) - 1;
            return $opciones[$indice] ?? null;
        }

        foreach ($opciones as $opcion) {
            $nombreNormalizado = $this->normalizarTexto((string) $opcion['nombre']);

            if ($nombreNormalizado === $texto
                || str_contains($nombreNormalizado, $texto)
                || str_contains($texto, $nombreNormalizado)
            ) {
                return $opcion;
            }
        }

        return null;
    }

    private function enviarReciboDeCuenta(string $telefono, int $usuarioId): void
    {
        $recibo = $this->recibos->reciboMasRecienteParaUsuario($usuarioId);

        if ($recibo === null) {
            $this->whatsApp->enviarTexto($telefono, 'Por el momento no encontramos recibos generados para esa cuenta.');
            return;
        }

        $mensaje = $this->recibos->mensajeEstadoParaBot($recibo);
        $imagenRelativa = $this->recibos->prepararImagenParaBot($recibo);

        if ($imagenRelativa !== null) {
            $absoluta = $this->rootDir . '/' . ltrim(str_replace('\\', '/', $imagenRelativa), '/');
            $this->whatsApp->enviarImagen($telefono, $absoluta, $mensaje);
            return;
        }

        $this->whatsApp->enviarTexto(
            $telefono,
            $mensaje . "\n\n(Tu recibo aun no esta disponible como imagen; en breve te lo enviamos.)"
        );
    }

    private function enviarFlyerPagos(string $telefono): void
    {
        if (!is_file($this->flyerPath)) {
            return;
        }

        try {
            $this->whatsApp->enviarImagen($telefono, $this->flyerPath, 'Datos para pago por transferencia bancaria.');
        } catch (Throwable $exception) {
            // No interrumpe el flujo si falla el envio del volante.
        }
    }

    private function mensajeOpciones(array $opciones, bool $incluirEncabezado): string
    {
        $lineas = [];

        if ($incluirEncabezado) {
            $lineas[] = 'Encontramos varias cuentas registradas con tu numero. Responde con el numero, el nombre, o escribe *todos* para recibir todos los recibos de este periodo:';
        }

        foreach ($opciones as $i => $opcion) {
            $lineas[] = ($i + 1) . ') ' . $opcion['nombre'];
        }

        return implode("\n", $lineas);
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    private function obtenerSesion(string $telefono): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT opciones_json FROM whatsapp_bot_sesiones
             WHERE telefono = :telefono AND created_at >= (NOW() - INTERVAL " . self::SESION_MINUTOS . " MINUTE)
             LIMIT 1"
        );
        $stmt->execute(['telefono' => $telefono]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $opciones = json_decode((string) $row['opciones_json'], true);

        return is_array($opciones) ? $opciones : null;
    }

    private function guardarSesion(string $telefono, array $opciones): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO whatsapp_bot_sesiones (telefono, opciones_json, created_at)
             VALUES (:telefono, :opciones_json, NOW())
             ON DUPLICATE KEY UPDATE opciones_json = VALUES(opciones_json), created_at = NOW()"
        );
        $stmt->execute([
            'telefono' => $telefono,
            'opciones_json' => json_encode($opciones, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function borrarSesion(string $telefono): void
    {
        $stmt = $this->db->prepare("DELETE FROM whatsapp_bot_sesiones WHERE telefono = :telefono");
        $stmt->execute(['telefono' => $telefono]);
    }
}
