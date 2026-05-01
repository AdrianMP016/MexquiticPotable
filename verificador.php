<?php

require_once __DIR__ . '/app/Core/PageGuard.php';
require_once __DIR__ . '/app/Clases/Rutas.php';

mexquiticRequirePageAccess('verificador', 'Ingreso al entorno de verificacion.');

$bootstrapData = [
    'rutas' => [],
];

try {
    $rutas = new Rutas($__mexquiticDb);
    $bootstrapData['rutas'] = $rutas->catalogo(0);
} catch (Throwable $exception) {
}

$html = file_get_contents(__DIR__ . '/verificador.html');

if ($html === false) {
    throw new RuntimeException('No se pudo cargar la interfaz del verificador.');
}

$bootstrapScript = '<script>window.__mexquiticVerificadorBootstrap = ' .
    json_encode($bootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
    ';</script>';

$html = str_replace(
    '<script src="assets/js/session-client.js?v=20260429b"></script>',
    '<script src="assets/js/session-client.js?v=20260429b"></script>' . PHP_EOL . $bootstrapScript,
    $html
);

echo $html;
