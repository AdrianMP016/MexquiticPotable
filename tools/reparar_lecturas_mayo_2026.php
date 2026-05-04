<?php
/**
 * Repara lecturas del 2026-05-04 capturadas en periodo 2 por error del servidor.
 *
 * Problema: Verificadores capturaron lecturas de Mayo 2026 pero el servidor
 * devolvía periodo_id=2 (Marzo-Abril) antes del deployment, sobreescribiendo
 * los datos históricos del CSV.
 *
 * Solución:
 *  1. Para cada medidor afectado CON dato en el CSV:
 *     a. Restaura periodo 2: lectura_anterior=ENE26, lectura_actual=MAR26
 *     b. Inserta periodo 3: lectura_anterior=MAR26, lectura_actual=MAY26
 *        (mantiene foto, GPS, observaciones, capturado_por_id del verificador)
 *  2. Para medidores SIN dato en CSV:
 *     a. Reasigna el registro de periodo 2 directamente a periodo 3
 *        (lectura_anterior quedará en ENE26 en vez de MAR26)
 *
 * Acceso web   (solo ver): /tools/reparar_lecturas_mayo_2026.php?clave=agua2026
 * Acceso web   (ejecutar): /tools/reparar_lecturas_mayo_2026.php?clave=agua2026&ejecutar=1
 * CLI dry-run:             php reparar_lecturas_mayo_2026.php agua2026
 * CLI ejecutar:            php reparar_lecturas_mayo_2026.php agua2026 --ejecutar
 */

// ── Protección ────────────────────────────────────────────────────────────────

$claveSecreta  = 'agua2026';
$claveRecibida = $_GET['clave'] ?? ($argv[1] ?? '');

if ($claveRecibida !== $claveSecreta) {
    http_response_code(403);
    die('Acceso no autorizado. Agrega ?clave=agua2026 a la URL.');
}

$esCli    = php_sapi_name() === 'cli';
$ejecutar = isset($_GET['ejecutar']) || in_array('--ejecutar', $argv ?? []);
$csvPath  = __DIR__ . '/Lectura Marzo 2026.csv';

require_once __DIR__ . '/../app/Core/Database.php';

// ── Salida ────────────────────────────────────────────────────────────────────

function out(string $msg, string $clase = ''): void
{
    global $esCli;
    if ($esCli) {
        echo $msg . "\n";
    } else {
        $encoded = htmlspecialchars($msg);
        echo $clase ? "<span class=\"{$clase}\">{$encoded}</span>\n" : "{$encoded}\n";
    }
}

if (!$esCli) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
       . '<title>Reparar lecturas Mayo 2026</title>'
       . '<style>body{font-family:monospace;padding:2rem;background:#111;color:#0f0;white-space:pre}'
       . '.warn{color:#fa0} .err{color:#f44} .ok{color:#0f0} h2{color:#fff;white-space:normal}'
       . '</style></head><body><h2>Reparar lecturas capturadas en periodo incorrecto</h2>';
}

out($ejecutar ? '=== MODO EJECUTAR ===' : '=== MODO DRY-RUN (solo diagnóstico, no se modifica nada) ===');
out('');

// ── Validar CSV ───────────────────────────────────────────────────────────────

if (!is_file($csvPath)) {
    out("ERROR: No se encontró el CSV en: {$csvPath}", 'err');
    exit(1);
}

// ── Conectar a BD ─────────────────────────────────────────────────────────────

try {
    $db = Database::connection();
} catch (Throwable $e) {
    out('ERROR de conexión: ' . $e->getMessage(), 'err');
    exit(1);
}

// ── 1. Leer lecturas mal asignadas (periodo 2, capturadas hoy por verificadores) ──

$CORTE_FECHA = '2026-05-04 04:38:11'; // Timestamp del import CSV; capturas después = verificadores
$PERIODO_2   = 2;
$PERIODO_3   = 3;

$stmtAfectados = $db->prepare(
    "SELECT id, medidor_id, lectura_anterior, lectura_actual,
            capturado_por_id, latitud, longitud, observaciones, foto_medicion_path, fecha_captura
     FROM lecturas
     WHERE periodo_id = :pid
       AND capturado_por_id IS NOT NULL
       AND fecha_captura > :corte
     ORDER BY medidor_id"
);
$stmtAfectados->execute([':pid' => $PERIODO_2, ':corte' => $CORTE_FECHA]);

$afectados = []; // medidor_id => row
foreach ($stmtAfectados->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $afectados[(int) $row['medidor_id']] = $row;
}

out("Lecturas mal asignadas en periodo 2: " . count($afectados));

if (empty($afectados)) {
    out('No hay registros que reparar. ¿Ya se ejecutó este script antes?', 'warn');
    exit(0);
}

// ── 2. Verificar cuáles ya tienen registro en periodo 3 (captura correcta post-deploy) ──

$medidoresConP3 = [];
if (!empty($afectados)) {
    $ids = implode(',', array_keys($afectados));
    $stmt = $db->query(
        "SELECT medidor_id, id AS lectura_id
         FROM lecturas
         WHERE periodo_id = {$PERIODO_3}
           AND medidor_id IN ({$ids})"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $medidoresConP3[(int) $row['medidor_id']] = (int) $row['lectura_id'];
    }
}

out("De esos, ya tienen registro correcto en periodo 3: " . count($medidoresConP3));
out('');

// ── 3. Leer CSV y construir mapa padron_id → {medidor_id, ENE26, MAR26} ──────

$stmtMedidor = $db->prepare(
    "SELECT m.id AS medidor_id
     FROM medidores m
     INNER JOIN usuarios_servicio u ON u.id = m.usuario_id
     WHERE u.padron_id = :padron_id
     ORDER BY FIELD(m.estado, 'activo', 'inactivo', 'reemplazado', 'sin_medidor') ASC, m.id ASC
     LIMIT 1"
);

$csvData = []; // medidor_id => ['ene26' => float, 'mar26' => float]

$handle = fopen($csvPath, 'r');
fgetcsv($handle); // saltar encabezado
while (($cols = fgetcsv($handle)) !== false) {
    $padron = (int) trim($cols[0] ?? '0');
    if ($padron <= 0) {
        continue;
    }

    $ene26 = trim($cols[13] ?? '');
    $mar26 = trim($cols[14] ?? '');

    if (!is_numeric($mar26) && !is_numeric($ene26)) {
        continue;
    }

    $stmtMedidor->execute([':padron_id' => $padron]);
    $med = $stmtMedidor->fetch(PDO::FETCH_ASSOC);
    if (!$med) {
        continue;
    }

    $medId = (int) $med['medidor_id'];
    if (!isset($afectados[$medId])) {
        continue; // este medidor no fue afectado, no lo necesitamos
    }

    $csvData[$medId] = [
        'ene26' => is_numeric($ene26) ? (float) $ene26 : 0.0,
        'mar26' => is_numeric($mar26) ? (float) $mar26 : null,
    ];
}
fclose($handle);

out("Medidores afectados con dato en CSV: " . count($csvData));
out("Medidores afectados SIN dato en CSV: " . (count($afectados) - count($csvData)));
out('');

// ── 4. Preparar sentencias ────────────────────────────────────────────────────

$stmtRestaurarP2 = $db->prepare(
    "UPDATE lecturas
     SET lectura_anterior = :ant,
         lectura_actual   = :act,
         capturado_por_id = NULL,
         latitud          = NULL,
         longitud         = NULL,
         foto_medicion_path = NULL,
         observaciones    = 'Histórico importado desde CSV',
         fecha_captura    = :fc
     WHERE id = :id"
);

$stmtInsertarP3 = $db->prepare(
    "INSERT INTO lecturas
        (medidor_id, periodo_id, lectura_anterior, lectura_actual,
         capturado_por_id, latitud, longitud, observaciones, foto_medicion_path, fecha_captura)
     VALUES
        (:mid, :pid, :ant, :act, :cap, :lat, :lng, :obs, :foto, :fc)"
);

$stmtReasignarP3 = $db->prepare(
    "UPDATE lecturas SET periodo_id = :pid3 WHERE id = :id"
);

// ── 5. Procesar cada medidor afectado ─────────────────────────────────────────

$reparadosConCsv  = 0;
$reparadosSinCsv  = 0;
$omitidosConP3    = 0;
$errores          = 0;

out('--- Detalle de acciones ---');

if ($ejecutar) {
    $db->beginTransaction();
}

foreach ($afectados as $medId => $registro) {
    $lecturaMay = (float) $registro['lectura_actual']; // La medición real de Mayo
    $lecturaP2Id = (int) $registro['id'];
    $yaConP3 = isset($medidoresConP3[$medId]);

    if (isset($csvData[$medId])) {
        $mar26 = $csvData[$medId]['mar26'];
        $ene26 = $csvData[$medId]['ene26'];

        if ($mar26 === null) {
            // CSV tiene ENE26 pero no MAR26 → tratar como sin CSV
            $yaConP3
                ? out("  [medidor {$medId}] Sin MAR26 en CSV + P3 ya existe → omitido", 'warn')
                : out("  [medidor {$medId}] Sin MAR26 en CSV → reasignar a P3 (ant={$ene26}, act={$lecturaMay})", 'warn');

            if (!$yaConP3) {
                if ($ejecutar) {
                    $stmtReasignarP3->execute([':pid3' => $PERIODO_3, ':id' => $lecturaP2Id]);
                }
                $reparadosSinCsv++;
            } else {
                $omitidosConP3++;
            }
            continue;
        }

        // Caso ideal: restaurar P2 con datos CSV, crear P3 con la medición real
        out("  [medidor {$medId}] CSV: ant={$ene26} act={$mar26} | P3: ant={$mar26} act={$lecturaMay}"
            . ($yaConP3 ? ' [P3 ya existe, no se toca]' : ''));

        if ($ejecutar) {
            try {
                // a) Restaurar periodo 2 con valores originales del CSV
                $stmtRestaurarP2->execute([
                    ':ant' => $ene26,
                    ':act' => $mar26,
                    ':fc'  => $CORTE_FECHA, // fecha original del import
                    ':id'  => $lecturaP2Id,
                ]);

                // b) Crear periodo 3 solo si no existe ya
                if (!$yaConP3) {
                    $stmtInsertarP3->execute([
                        ':mid'  => $medId,
                        ':pid'  => $PERIODO_3,
                        ':ant'  => $mar26,
                        ':act'  => $lecturaMay,
                        ':cap'  => $registro['capturado_por_id'],
                        ':lat'  => $registro['latitud'],
                        ':lng'  => $registro['longitud'],
                        ':obs'  => $registro['observaciones'],
                        ':foto' => $registro['foto_medicion_path'],
                        ':fc'   => $registro['fecha_captura'],
                    ]);
                }
            } catch (Throwable $e) {
                out("  [medidor {$medId}] ERROR: " . $e->getMessage(), 'err');
                $errores++;
                continue;
            }
        }

        $yaConP3 ? $omitidosConP3++ : $reparadosConCsv++;
    } else {
        // Sin dato en CSV → reasignar directamente a periodo 3
        if ($yaConP3) {
            out("  [medidor {$medId}] Sin CSV + P3 ya existe → omitido (P2 queda corrupto)", 'warn');
            $omitidosConP3++;
        } else {
            out("  [medidor {$medId}] Sin CSV → reasignar a P3 (ant={$registro['lectura_anterior']}, act={$lecturaMay})", 'warn');
            if ($ejecutar) {
                $stmtReasignarP3->execute([':pid3' => $PERIODO_3, ':id' => $lecturaP2Id]);
            }
            $reparadosSinCsv++;
        }
    }
}

if ($ejecutar) {
    if ($errores > 0) {
        $db->rollBack();
        out('', '');
        out("ROLLBACK — hubo {$errores} error(es). No se guardó nada.", 'err');
    } else {
        $db->commit();
        out('', '');
        out('Transacción confirmada (COMMIT).', 'ok');
    }
}

// ── 6. Resumen ────────────────────────────────────────────────────────────────

out('');
out('=== RESUMEN ===');
out("  Reparados con CSV (P2 restaurado + P3 creado) : {$reparadosConCsv}");
out("  Reparados sin CSV (solo reasignados a P3)     : {$reparadosSinCsv}");
out("  Omitidos (P3 ya existía correctamente)        : {$omitidosConP3}");
out("  Errores                                       : {$errores}");
out('');

if (!$ejecutar) {
    out('--- Este fue un DRY-RUN. Para aplicar los cambios agrega &ejecutar=1 a la URL ---', 'warn');
}

if (!$esCli) {
    echo '</body></html>';
}
