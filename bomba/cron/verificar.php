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

    if ($abierta && $abierta['origen'] === 'cronometro') {
        $transcurrido = $ahora->getTimestamp() - strtotime((string) $abierta['inicio_at']);
        if ($transcurrido >= (int) $abierta['cronometro_duracion_segundos']) {
            $regla = $db->query("SELECT * FROM bomba_regla_automatica WHERE activa = 1 LIMIT 1")->fetch();
            $sigueRegla = $regla
                && en_dias($regla['dias_semana'], $diaIso)
                && $horaActual >= $regla['hora_inicio']
                && $horaActual < $regla['hora_fin'];

            cerrar($db, $abierta, $ahora->format('Y-m-d H:i:s'), 'cronometro_expirado');

            if ($sigueRegla) {
                // Hay una programacion activa cubriendo este momento: no apagar, solo
                // transferir el control de la bomba del cronometro a la regla automatica.
                $stmt = $db->prepare(
                    "INSERT INTO bomba_activaciones (origen, regla_automatica_id, inicio_at)
                     VALUES ('automatico', :regla_id, :inicio)"
                );
                $stmt->execute([
                    'regla_id' => (int) $regla['id'],
                    'inicio' => $ahora->format('Y-m-d H:i:s'),
                ]);
                $accion = 'cronometro_expirado';
                $descripcion = 'El cronometro termino, pero la bomba sigue encendida porque hay una programacion activa.';
            } else {
                $pulsoPendiente = 'paro';
                $accion = 'cronometro_expirado';
                $descripcion = 'El cronometro termino y la bomba se apago automaticamente.';
            }
        }
    } elseif ($abierta && $abierta['origen'] === 'automatico') {
        $regla = $db->query("SELECT * FROM bomba_regla_automatica WHERE activa = 1 LIMIT 1")->fetch();
        $sigueEnVentana = $regla
            && en_dias($regla['dias_semana'], $diaIso)
            && $horaActual >= $regla['hora_inicio']
            && $horaActual < $regla['hora_fin'];

        if (!$sigueEnVentana) {
            cerrar($db, $abierta, $ahora->format('Y-m-d H:i:s'), 'regla_fin');
            $pulsoPendiente = 'paro';
            $accion = 'automatico_apagado';
            $descripcion = 'La regla automatica termino su ventana y la bomba se apago.';
        }
    } elseif (!$abierta) {
        $regla = $db->query("SELECT * FROM bomba_regla_automatica WHERE activa = 1 LIMIT 1")->fetch();
        $debeEncender = $regla
            && en_dias($regla['dias_semana'], $diaIso)
            && $horaActual >= $regla['hora_inicio']
            && $horaActual < $regla['hora_fin'];

        if ($debeEncender) {
            $stmt = $db->prepare(
                "INSERT INTO bomba_activaciones (origen, regla_automatica_id, inicio_at)
                 VALUES ('automatico', :regla_id, :inicio)"
            );
            $stmt->execute([
                'regla_id' => (int) $regla['id'],
                'inicio' => $ahora->format('Y-m-d H:i:s'),
            ]);
            $pulsoPendiente = 'inicio';
            $accion = 'automatico_encendido';
            $descripcion = 'La regla automatica encendio la bomba.';
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
