<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Clases/ShellyClient.php';
require_once __DIR__ . '/../app/Clases/BitacoraBomba.php';
require_once __DIR__ . '/../app/Clases/ConfigBomba.php';
require_once __DIR__ . '/../app/Clases/WebPushClient.php';

$esCli = PHP_SAPI === 'cli';

if (!$esCli) {
    $configPath = __DIR__ . '/../app/Config/cron.php';
    if (!is_file($configPath)) {
        $configPath = __DIR__ . '/../app/Config/cron.example.php';
    }
    $cronConfig = require $configPath;
    $tokenEsperado = (string) ($cronConfig['token'] ?? '');
    $tokenRecibido = (string) ($_GET['token'] ?? '');

    header('Content-Type: application/json; charset=utf-8');

    if ($tokenEsperado === '' || !hash_equals($tokenEsperado, $tokenRecibido)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'token invalido']);
        exit;
    }
}

global $__bombaDb;
$db = $__bombaDb;
$bitacora = new BitacoraBomba($db);
$configBomba = new ConfigBomba($db);
$webPush = new WebPushClient($db);

$bloqueado = $db->query("SELECT GET_LOCK('bomba_cron_verificar', 0) AS ok")->fetch();
if (!$bloqueado || (int) $bloqueado['ok'] !== 1) {
    salir(['ok' => true, 'skip' => 'cron_solapado']);
}

try {
    $shelly = new ShellyClient($db);
    $ahora = new DateTime('now');
    $diaIso = (int) $ahora->format('N');
    $horaActual = $ahora->format('H:i:s');

    $db->beginTransaction();

    $abierta = $db->query(
        "SELECT * FROM bomba_activaciones WHERE fin_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE"
    )->fetch();

    // pulsoPendiente/accion/descripcion se resuelven aqui dentro de la
    // transaccion (solo lectura/escritura local, rapido), pero el pulso real a
    // Shelly Cloud se manda YA FUERA de la transaccion (ver abajo): esa llamada
    // puede tardar varios segundos y no debe dejar bloqueada la conexion a la
    // base de datos ni el candado de fila mientras espera la respuesta.
    $pulsoPendiente = null;
    $accion = null;
    $descripcion = null;

    // La regla permanente y la regla temporal se evaluan por separado y
    // nunca se pisan entre ellas: si cualquiera de las dos aplica ahora
    // mismo, la bomba debe estar encendida. Solo se apaga cuando ninguna
    // de las dos aplica.
    //
    // El apagado de emergencia esta por encima de las dos: mientras este
    // activo, ninguna regla puede encender la bomba (se tratan como si no
    // aplicaran), sin importar su horario.
    $emergenciaActiva = $configBomba->obtenerBool('emergencia_activa');
    $reglaPermanente = $emergenciaActiva ? null : regla_permanente_aplica($db, $diaIso, $horaActual);
    $reglaTemporal = $emergenciaActiva ? null : regla_temporal_aplica($db, $ahora->format('Y-m-d H:i:s'));
    $algunaReglaAplica = $reglaPermanente !== null || $reglaTemporal !== null;

    if ($abierta && $abierta['origen'] === 'cronometro') {
        $transcurrido = $ahora->getTimestamp() - strtotime((string) $abierta['inicio_at']);
        if ($transcurrido >= (int) $abierta['cronometro_duracion_segundos']) {
            cerrar($db, $abierta, $ahora->format('Y-m-d H:i:s'), 'cronometro_expirado');

            if ($algunaReglaAplica) {
                // Hay una programacion (permanente o temporal) cubriendo este
                // momento: no apagar, solo transferir el control del cronometro
                // a la regla automatica.
                $stmt = $db->prepare(
                    "INSERT INTO bomba_activaciones (origen, regla_automatica_id, inicio_at)
                     VALUES ('automatico', :regla_id, :inicio)"
                );
                $stmt->execute([
                    'regla_id' => $reglaPermanente ? (int) $reglaPermanente['id'] : null,
                    'inicio' => $ahora->format('Y-m-d H:i:s'),
                ]);
                $accion = 'cronometro_expirado';
                $descripcion = 'El cronometro termino, pero la bomba sigue encendida porque hay una programacion activa'
                    . ($reglaTemporal ? ' (regla temporal)' : '') . '.';
            } else {
                $pulsoPendiente = 'paro';
                $accion = 'cronometro_expirado';
                $descripcion = 'El cronometro termino y la bomba se apago automaticamente.';
            }
        }
    } elseif ($abierta && $abierta['origen'] === 'automatico') {
        if (!$algunaReglaAplica) {
            cerrar($db, $abierta, $ahora->format('Y-m-d H:i:s'), 'regla_fin');
            $pulsoPendiente = 'paro';
            $accion = 'automatico_apagado';
            $descripcion = 'La regla automatica termino su ventana y la bomba se apago.';
        }
    } elseif (!$abierta) {
        if ($algunaReglaAplica) {
            $stmt = $db->prepare(
                "INSERT INTO bomba_activaciones (origen, regla_automatica_id, inicio_at)
                 VALUES ('automatico', :regla_id, :inicio)"
            );
            $stmt->execute([
                'regla_id' => $reglaPermanente ? (int) $reglaPermanente['id'] : null,
                'inicio' => $ahora->format('Y-m-d H:i:s'),
            ]);
            $pulsoPendiente = 'inicio';
            $accion = 'automatico_encendido';
            $descripcion = $reglaTemporal && !$reglaPermanente
                ? 'La regla temporal encendio la bomba.'
                : 'La regla automatica encendio la bomba.';
        }
    }

    $db->commit();

    if ($pulsoPendiente === 'paro') {
        $shelly->pulsarParo();
    } elseif ($pulsoPendiente === 'inicio') {
        $shelly->pulsarInicio();
    }

    if ($accion !== null) {
        $bitacora->registrar(['accion' => $accion, 'descripcion' => $descripcion]);
    }

    if ($pulsoPendiente === 'paro') {
        try {
            $webPush->enviarATodos('Bomba apagada', (string) $descripcion);
        } catch (Throwable $exception) {
            // Un push fallido no debe afectar el resultado del cron.
        }
    } elseif ($pulsoPendiente === 'inicio') {
        try {
            $webPush->enviarATodos('Bomba encendida', (string) $descripcion);
        } catch (Throwable $exception) {
            // Un push fallido no debe afectar el resultado del cron.
        }
    }

    // La bitacora se limpia solo una vez al dia (no en cada tick del cron),
    // para que la tabla no crezca sin limite sin recargar el servidor.
    $ultimaLimpieza = $configBomba->obtener('bitacora_limpieza_ultima_at', '');
    if (substr($ultimaLimpieza, 0, 10) !== $ahora->format('Y-m-d')) {
        try {
            $bitacora->limpiarAntiguos(6);
        } catch (Throwable $exception) {
            // No debe afectar el resultado del cron si la limpieza falla.
        }
        $configBomba->establecer('bitacora_limpieza_ultima_at', $ahora->format('Y-m-d H:i:s'));
    }

    $configBomba->establecer('cron_ultima_ejecucion_at', $ahora->format('Y-m-d H:i:s'));
    $configBomba->establecer('cron_ultimo_resultado', 'ok');
    salir(['ok' => true]);
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    try {
        $bitacora->registrar([
            'accion' => 'cron_error',
            'descripcion' => 'shelly_no_disponible: ' . $exception->getMessage(),
        ]);
        $configBomba->establecer('cron_ultimo_resultado', 'error: ' . $exception->getMessage());
    } catch (Throwable $ignored) {
        // Si ni siquiera se pudo registrar el error, no hay mas que hacer en este tick.
    }

    salir(['ok' => false, 'error' => $exception->getMessage()]);
}

function en_dias(string $diasCsv, int $diaIso): bool
{
    $dias = array_map('intval', explode(',', $diasCsv));
    return in_array($diaIso, $dias, true);
}

function regla_permanente_aplica(PDO $db, int $diaIso, string $horaActual): ?array
{
    $regla = $db->query("SELECT * FROM bomba_regla_automatica WHERE activa = 1 LIMIT 1")->fetch();

    if ($regla
        && en_dias($regla['dias_semana'], $diaIso)
        && $horaActual >= $regla['hora_inicio']
        && $horaActual < $regla['hora_fin']
    ) {
        return $regla;
    }

    return null;
}

function regla_temporal_aplica(PDO $db, string $ahoraTexto): ?array
{
    // No se hace auto-limpieza aqui (eso lo hace ReglaTemporal::obtenerActiva
    // cuando alguien ve la pantalla); esta consulta solo revisa si aplica
    // ahora mismo, sin importar si el flag "activa" ya quedo desactualizado.
    // Un solo rango continuo de fecha+hora (no una hora que se repite cada
    // dia), asi que si cruza la medianoche funciona bien.
    $regla = $db->query(
        "SELECT * FROM bomba_regla_temporal
         WHERE activa = 1 AND TIMESTAMP(fecha_fin, hora_fin) >= NOW()
         ORDER BY id DESC LIMIT 1"
    )->fetch();

    if (!$regla) {
        return null;
    }

    $inicioDt = $regla['fecha_inicio'] . ' ' . $regla['hora_inicio'];
    $finDt = $regla['fecha_fin'] . ' ' . $regla['hora_fin'];

    return ($ahoraTexto >= $inicioDt && $ahoraTexto < $finDt) ? $regla : null;
}

function cerrar(PDO $db, array $abierta, string $finAt, string $finMotivo): void
{
    $duracion = max(0, strtotime($finAt) - strtotime((string) $abierta['inicio_at']));
    $stmt = $db->prepare(
        "UPDATE bomba_activaciones SET fin_at = :fin_at, duracion_segundos = :duracion, fin_motivo = :fin_motivo WHERE id = :id"
    );
    $stmt->execute([
        'fin_at' => $finAt,
        'duracion' => $duracion,
        'fin_motivo' => $finMotivo,
        'id' => $abierta['id'],
    ]);
}

function salir(array $payload): void
{
    global $esCli, $db;

    $db->exec("SELECT RELEASE_LOCK('bomba_cron_verificar')");

    if ($esCli) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    exit(0);
}
