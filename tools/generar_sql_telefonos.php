<?php
/**
 * Genera el SQL de actualizaciÃ³n de telÃ©fonos a partir de Cent1.csv y Llani.csv.
 * Columnas CSV: Nombre | Ruta | NÃºmero de TelÃ©fono | Telefono Extra
 *   - NÃºmero de TelÃ©fono â†’ u.whatsapp  (canal principal para envÃ­os)
 *   - Telefono Extra     â†’ u.telefono  (canal secundario / respaldo)
 * Clave de coincidencia: domicilios.ruta
 */

function normalizarTelefono(string $raw): string
{
    $tel = preg_replace('/[^0-9+]/', '', $raw); // quitar espacios y caracteres no numÃ©ricos
    return $tel;
}

function esValido(string $tel): bool
{
    $digits = preg_replace('/[^0-9]/', '', $tel);
    return strlen($digits) >= 7;
}

/**
 * Lee un CSV y agrupa filas por ruta.
 * Si la misma ruta aparece mÃ¡s de una vez combina los telÃ©fonos:
 *   primera fila con telÃ©fono â†’ whatsapp
 *   segunda fila con telÃ©fono (o Telefono Extra de cualquier fila) â†’ telefono
 */
function leerCsv(string $file): array
{
    $porRuta = []; // [ruta => ['whatsapp' => ..., 'telefono' => ..., 'nombres' => []]]

    if (($handle = fopen($file, 'r')) === false) {
        die("No se pudo abrir: $file\n");
    }

    $encabezado = null;
    while (($row = fgetcsv($handle, 2000, ',', '"', '')) !== false) {
        if ($encabezado === null) {
            $encabezado = $row;
            continue;
        }

        // Mapear columnas (tolerante a variaciones de BOM / encoding)
        $nombre = trim($row[0] ?? '');
        $ruta   = trim($row[1] ?? '');
        $tel1   = normalizarTelefono($row[2] ?? '');
        $tel2   = normalizarTelefono($row[3] ?? '');

        if ($ruta === '') continue;

        if (!isset($porRuta[$ruta])) {
            $porRuta[$ruta] = ['whatsapp' => '', 'telefono' => '', 'nombres' => []];
        }

        $porRuta[$ruta]['nombres'][] = $nombre;

        // Llenar whatsapp con el primer nÃºmero vÃ¡lido encontrado
        if (esValido($tel1) && $porRuta[$ruta]['whatsapp'] === '') {
            $porRuta[$ruta]['whatsapp'] = $tel1;
        }

        // Llenar telefono con el Telefono Extra vÃ¡lido, o con el nÃºmero principal
        // si la ruta ya tenÃ­a un whatsapp registrado (segunda persona comparte domicilio)
        if (esValido($tel2) && $porRuta[$ruta]['telefono'] === '') {
            $porRuta[$ruta]['telefono'] = $tel2;
        } elseif (esValido($tel1) && $porRuta[$ruta]['whatsapp'] !== '' && $tel1 !== $porRuta[$ruta]['whatsapp'] && $porRuta[$ruta]['telefono'] === '') {
            $porRuta[$ruta]['telefono'] = $tel1;
        }
    }

    fclose($handle);
    return $porRuta;
}

function generarBloqueSql(array $porRuta, string $etiqueta): array
{
    $lineas = [];
    foreach ($porRuta as $ruta => $datos) {
        $wa  = $datos['whatsapp'];
        $tel = $datos['telefono'];

        if (!esValido($wa) && !esValido($tel)) {
            continue; // sin nÃºmeros vÃ¡lidos â†’ omitir
        }

        $setParts = [];
        if (esValido($wa))  $setParts[] = "u.whatsapp = '" . addslashes($wa) . "'";
        if (esValido($tel)) $setParts[] = "u.telefono = '" . addslashes($tel) . "'";

        $comentario = implode(' / ', $datos['nombres']);
        $set        = implode(', ', $setParts);
        $lineas[]   = "-- $comentario";
        $lineas[]   = "UPDATE usuarios_servicio u INNER JOIN domicilios d ON d.usuario_id = u.id SET $set WHERE d.ruta = '" . addslashes($ruta) . "';";
    }
    return $lineas;
}

// â”€â”€ Leer archivos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$dirTools = __DIR__;
$cent1    = leerCsv("$dirTools/Cent1.csv");
$llani    = leerCsv("$dirTools/Llani.csv");

$lineasCent1 = generarBloqueSql($cent1, 'Cent1');
$lineasLlani = generarBloqueSql($llani, 'Llani');

// â”€â”€ Construir archivo SQL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$fecha   = date('Y-m-d H:i:s');
$totalC1 = count(array_filter(array_keys($cent1), fn($r) => true));
$totalLl = count(array_filter(array_keys($llani), fn($r) => true));

$sql  = "-- ===========================================================\n";
$sql .= "-- ActualizaciÃ³n de telÃ©fonos: Cent1 y Llani\n";
$sql .= "-- Generado: $fecha\n";
$sql .= "-- Fuente:   tools/Cent1.csv y tools/Llani.csv\n";
$sql .= "-- Regla:    NÃºmero de TelÃ©fono â†’ whatsapp\n";
$sql .= "--           Telefono Extra     â†’ telefono\n";
$sql .= "-- Clave:    domicilios.ruta\n";
$sql .= "-- ===========================================================\n\n";

$sql .= "-- â”€â”€ Cent1 ($totalC1 rutas) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€\n";
$sql .= implode("\n", $lineasCent1) . "\n\n";

$sql .= "-- â”€â”€ Llani ($totalLl rutas) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€\n";
$sql .= implode("\n", $lineasLlani) . "\n";

$outFile = "$dirTools/sql/telefonos_cent1_llani.sql";
file_put_contents($outFile, $sql);

$conWa  = array_filter(array_merge($cent1, $llani), fn($d) => esValido($d['whatsapp']));
$conTel = array_filter(array_merge($cent1, $llani), fn($d) => esValido($d['telefono']));

echo "âœ“ Generado: $outFile\n";
echo "  Cent1: $totalC1 rutas | Llani: $totalLl rutas\n";
echo "  Con WhatsApp: " . count($conWa) . " | Con Telefono extra: " . count($conTel) . "\n";

