<?php
/**
 * Importa lecturas históricas 2026 desde el CSV de campo.
 * Acceso: /tools/import_lecturas_2026.php?clave=agua2026
 *
 * Vincula por padron_id (col 0 del CSV) → usuarios_servicio.padron_id → medidores.
 *
 * Periodos:
 *   id=1  Enero-Febrero 2026   (lectura_anterior=NOV25, lectura_actual=ENE26)
 *   id=2  Marzo-Abril 2026     (lectura_anterior=ENE26, lectura_actual=MAR26)
 */

// ── Protección de acceso ──────────────────────────────────────────────────────

$claveSecreta  = 'agua2026';
$claveRecibida = $_GET['clave'] ?? ($argv[1] ?? '');

if ($claveRecibida !== $claveSecreta) {
    http_response_code(403);
    die('Acceso no autorizado. Agrega ?clave=agua2026 a la URL.');
}

// ── Rutas ─────────────────────────────────────────────────────────────────────

$esCli   = php_sapi_name() === 'cli';
$csvPath = __DIR__ . '/Lectura Marzo 2026.csv';

require_once __DIR__ . '/../app/Core/Database.php';

// ── Salida ────────────────────────────────────────────────────────────────────

function out(string $msg): void
{
    global $esCli;
    echo $esCli ? $msg . "\n" : nl2br(htmlspecialchars($msg)) . "\n";
}

if (!$esCli) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
       . '<title>Importar lecturas 2026</title>'
       . '<style>body{font-family:monospace;padding:2rem;background:#111;color:#0f0;}'
       . 'h2{color:#fff;}</style></head><body>'
       . '<h2>Importador de lecturas 2026</h2><pre>';
}

// ── Índices de columna (base 0) ───────────────────────────────────────────────

const COL_PADRON  = 0;   // padron_id en usuarios_servicio
const COL_USUARIO = 1;   // nombre (solo para logs)
const COL_NOV25   = 12;
const COL_ENE26   = 13;
const COL_MAR26   = 14;
const PERIODO_1   = 1;
const PERIODO_2   = 2;

// ── Utilidades ────────────────────────────────────────────────────────────────

function esNumero(string $v): bool
{
    return trim($v) !== '' && is_numeric(trim($v));
}

function parsearLectura(string $v): float
{
    return (float) trim($v);
}

// ── Validar CSV ───────────────────────────────────────────────────────────────

if (!is_file($csvPath)) {
    out("ERROR: No se encontró el CSV en: {$csvPath}");
    exit(1);
}

// ── Conectar a BD ─────────────────────────────────────────────────────────────

try {
    $db = Database::connection();
} catch (Throwable $e) {
    out("ERROR de conexión: " . $e->getMessage());
    exit(1);
}

// ── Leer CSV ──────────────────────────────────────────────────────────────────

$handle = fopen($csvPath, 'r');
fgetcsv($handle); // saltar encabezado
$rows = [];
while (($cols = fgetcsv($handle)) !== false) {
    $rows[] = $cols;
}
fclose($handle);

out("Filas leídas: " . count($rows));

// ── Borrar registros de prueba ────────────────────────────────────────────────

$db->exec("SET FOREIGN_KEY_CHECKS = 0");

$dp = $db->exec(
    "DELETE FROM pagos WHERE recibo_id IN (SELECT id FROM recibos WHERE periodo_id IN (1, 2))"
);
out("Pagos de prueba eliminados      : {$dp}");

$dr = $db->exec("DELETE FROM recibos WHERE periodo_id IN (1, 2)");
out("Recibos de prueba eliminados    : {$dr}");

$dl = $db->exec("DELETE FROM lecturas WHERE periodo_id IN (1, 2)");
out("Lecturas de prueba eliminadas   : {$dl}");
out("");

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

// ── Preparar sentencias ───────────────────────────────────────────────────────

// Busca el medidor del usuario a partir de su padron_id
$stmtMedidor = $db->prepare(
    "SELECT m.id AS medidor_id
     FROM medidores m
     INNER JOIN usuarios_servicio u ON u.id = m.usuario_id
     WHERE u.padron_id = :padron_id
     ORDER BY FIELD(m.estado, 'activo', 'inactivo', 'reemplazado', 'sin_medidor') ASC, m.id ASC
     LIMIT 1"
);

$stmtInsertar = $db->prepare(
    "INSERT INTO lecturas
        (medidor_id, periodo_id, lectura_anterior, lectura_actual, fecha_captura, observaciones)
     VALUES
        (:medidor_id, :periodo_id, :lectura_anterior, :lectura_actual, NOW(), 'Histórico importado desde CSV')"
);

// ── Procesar filas ────────────────────────────────────────────────────────────

$ins1 = $ins2 = $sinMedidor = $sinLectura = 0;

foreach ($rows as $i => $cols) {
    $fila    = $i + 2;
    $padron  = (int) trim($cols[COL_PADRON] ?? '0');
    $nombre  = trim($cols[COL_USUARIO] ?? '');

    if ($padron <= 0) {
        $sinLectura++;
        continue;
    }

    // Buscar medidor_id por padron_id
    $stmtMedidor->execute([':padron_id' => $padron]);
    $med = $stmtMedidor->fetch();

    if (!$med) {
        out("  [fila {$fila}] Sin medidor en BD para padrón #{$padron} ({$nombre})");
        $sinMedidor++;
        continue;
    }

    $medId = (int) $med['medidor_id'];
    $nov25 = $cols[COL_NOV25] ?? '';
    $ene26 = $cols[COL_ENE26] ?? '';
    $mar26 = $cols[COL_MAR26] ?? '';

    // Período 1: anterior=NOV25, actual=ENE26
    if (esNumero($ene26)) {
        $stmtInsertar->execute([
            ':medidor_id'       => $medId,
            ':periodo_id'       => PERIODO_1,
            ':lectura_anterior' => esNumero($nov25) ? parsearLectura($nov25) : 0.0,
            ':lectura_actual'   => parsearLectura($ene26),
        ]);
        $ins1++;
    }

    // Período 2: anterior=ENE26, actual=MAR26
    if (esNumero($mar26)) {
        $stmtInsertar->execute([
            ':medidor_id'       => $medId,
            ':periodo_id'       => PERIODO_2,
            ':lectura_anterior' => esNumero($ene26) ? parsearLectura($ene26) : 0.0,
            ':lectura_actual'   => parsearLectura($mar26),
        ]);
        $ins2++;
    }

    if (!esNumero($ene26) && !esNumero($mar26)) {
        $sinLectura++;
    }
}

// ── Resumen ───────────────────────────────────────────────────────────────────

out("");
out("=== IMPORTACIÓN COMPLETADA ===");
out("  Período 1 (ENE 26) insertados : {$ins1}");
out("  Período 2 (MAR 26) insertados : {$ins2}");
out("  Sin medidor en BD             : {$sinMedidor}");
out("  Sin lectura en CSV            : {$sinLectura}");

if (!$esCli) {
    echo '</pre></body></html>';
}
