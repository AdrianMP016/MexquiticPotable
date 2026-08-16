<?php

header('Content-Type: application/json; charset=utf-8');

$resultado = [];

$candidatos = [
    'python3', 'python',
    '/usr/bin/python3', '/usr/local/bin/python3',
    '/opt/alt/python311/bin/python3', '/opt/alt/python310/bin/python3',
    '/opt/alt/python39/bin/python3', '/opt/alt/python38/bin/python3',
    '/opt/cpanel/ea-python311/root/usr/bin/python3',
    '/opt/cpanel/ea-python39/root/usr/bin/python3',
];

foreach ($candidatos as $candidato) {
    $out = [];
    $code = 0;
    @exec(escapeshellarg($candidato) . ' --version 2>&1', $out, $code);
    $resultado[$candidato] = ['salida' => $out, 'codigo' => $code];
}

$out = [];
@exec('find /opt -maxdepth 3 -iname "python3*" -type f 2>/dev/null', $out);
$resultado['busqueda_opt'] = $out;

$out = [];
@exec('ls /opt/alt 2>&1', $out);
$resultado['ls_opt_alt'] = $out;

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
