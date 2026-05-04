<?php
/**
 * Importa lecturas históricas 2026 desde el CSV de campo.
 * Uso: php tools/import_lecturas_2026.php [ruta_csv]
 *
 * Periodos:
 *   id=1  Enero-Febrero 2026   (lectura_anterior=NOV25, lectura_actual=ENE26)
 *   id=2  Marzo-Abril 2026     (lectura_anterior=ENE26, lectura_actual=MAR26)
 *
 * ADVERTENCIA: borra los registros de prueba de los periodos 1 y 2 antes de insertar.
 */

require_once __DIR__ . '/../app/Core/Database.php';

// ── Configuración ─────────────────────────────────────────────────────────────

$csvPath = $argv[1] ?? 'C:/Users/Acer_V/Downloads/Lectura Marzo 2026.csv';

// Índices de columna (base 0)
const COL_MEDIDOR  = 2;
const COL_NOV25    = 12;   // lectura_anterior período 1
const COL_ENE26    = 13;   // lectura_actual período 1 / lectura_anterior período 2
const COL_MAR26    = 14;   // lectura_actual período 2

const PERIODO_1 = 1;
const PERIODO_2 = 2;

// ── Utilidades ────────────────────────────────────────────────────────────────

function esNumero(string $v): bool
{
    $v = trim($v);
    return $v !== '' && is_numeric($v);
}

function parsearLectura(string $v): float
{
    return (float) trim($v);
}

function esNoRegistrado(string $medidor): bool
{
    // SAPMXQ-XXXX indica medidor sin número de serie registrado
    return stripos($medidor, 'XXXX') !== false;
}

// ── Ejecución ─────────────────────────────────────────────────────────────────

if (!is_file($csvPath)) {
    echo "ERROR: No se encontró el archivo CSV: {$csvPath}\n";
    exit(1);
}

try {
    $db = Database::connection();
} catch (Throwable $e) {
    echo "ERROR de conexión: " . $e->getMessage() . "\n";
    exit(1);
}

// Leer CSV completo
$handle = fopen($csvPath, 'r');
if (!$handle) {
    echo "ERROR: No se pudo abrir el archivo CSV.\n";
    exit(1);
}

// Saltar encabezado
fgetcsv($handle);

$rows = [];
while (($cols = fgetcsv($handle)) !== false) {
    $rows[] = $cols;
}
fclose($handle);

echo "Filas leídas: " . count($rows) . "\n";

// ── Borrar registros de prueba ────────────────────────────────────────────────

$db->exec("SET FOREIGN_KEY_CHECKS = 0");

$deletedPagos = $db->exec(
    "DELETE FROM pagos
     WHERE recibo_id IN (SELECT id FROM recibos WHERE periodo_id IN (1, 2))"
);
echo "Pagos de prueba eliminados      : {$deletedPagos}\n";

$deletedRec = $db->exec("DELETE FROM recibos WHERE periodo_id IN (1, 2)");
echo "Recibos de prueba eliminados    : {$deletedRec}\n";

$deleted = $db->exec("DELETE FROM lecturas WHERE periodo_id IN (1, 2)");
echo "Lecturas de prueba eliminadas   : {$deleted}\n\n";

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

// ── Preparar sentencias ───────────────────────────────────────────────────────

$stmtBuscarMedidor = $db->prepare(
    "SELECT id FROM medidores WHERE numero = :numero LIMIT 1"
);

$stmtInsertar = $db->prepare(
    "INSERT INTO lecturas
        (medidor_id, periodo_id, lectura_anterior, lectura_actual, fecha_captura, observaciones)
     VALUES
        (:medidor_id, :periodo_id, :lectura_anterior, :lectura_actual, NOW(), 'Histórico importado desde CSV')"
);

// ── Procesar filas ────────────────────────────────────────────────────────────

$insertados1 = 0;
$insertados2 = 0;
$omitidos    = 0;

foreach ($rows as $i => $cols) {
    $fila     = $i + 2; // nro de fila en el CSV (con encabezado)
    $medidor  = trim($cols[COL_MEDIDOR] ?? '');

    if ($medidor === '' || esNoRegistrado($medidor)) {
        $omitidos++;
        continue;
    }

    // Buscar medidor_id
    $stmtBuscarMedidor->execute([':numero' => $medidor]);
    $med = $stmtBuscarMedidor->fetch();

    if (!$med) {
        echo "  [fila {$fila}] MEDIDOR NO ENCONTRADO en BD: {$medidor}\n";
        $omitidos++;
        continue;
    }

    $medidorId = (int) $med['id'];
    $nov25     = $cols[COL_NOV25] ?? '';
    $ene26     = $cols[COL_ENE26] ?? '';
    $mar26     = $cols[COL_MAR26] ?? '';

    // Periodo 1: anterior=NOV25, actual=ENE26
    if (esNumero($ene26)) {
        $anterior1 = esNumero($nov25) ? parsearLectura($nov25) : 0.0;
        $stmtInsertar->execute([
            ':medidor_id'      => $medidorId,
            ':periodo_id'      => PERIODO_1,
            ':lectura_anterior' => $anterior1,
            ':lectura_actual'  => parsearLectura($ene26),
        ]);
        $insertados1++;
    }

    // Periodo 2: anterior=ENE26, actual=MAR26
    if (esNumero($mar26)) {
        $anterior2 = esNumero($ene26) ? parsearLectura($ene26) : 0.0;
        $stmtInsertar->execute([
            ':medidor_id'      => $medidorId,
            ':periodo_id'      => PERIODO_2,
            ':lectura_anterior' => $anterior2,
            ':lectura_actual'  => parsearLectura($mar26),
        ]);
        $insertados2++;
    }
}

// ── Resumen ───────────────────────────────────────────────────────────────────

echo "\n=== IMPORTACIÓN COMPLETADA ===\n";
echo "  Período 1 (ENE 26) insertados : {$insertados1}\n";
echo "  Período 2 (MAR 26) insertados : {$insertados2}\n";
echo "  Omitidos (XXXX o sin BD)      : {$omitidos}\n";
