<?php

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Clases/ImportadorPadronExcel.php';

$defaultPath = 'C:/Users/Acer_V/Downloads/2026 Datos.xlsx';
$excelPath = $argv[1] ?? $defaultPath;

try {
    $db = Database::connection();
    $importador = new ImportadorPadronExcel($db);
    $resultado = $importador->importarPadronExcel($excelPath);
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    $payload = [
        'ok' => false,
        'mensaje' => $exception->getMessage(),
    ];
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}
