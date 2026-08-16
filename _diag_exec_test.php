<?php

header('Content-Type: application/json; charset=utf-8');

$python = '/opt/alt/python311/bin/python3';
$resultado = [];

$out = [];
$code = 0;
@exec(escapeshellarg($python) . ' -c "import openpyxl; print(openpyxl.__version__)" 2>&1', $out, $code);
$resultado['openpyxl'] = ['salida' => $out, 'codigo' => $code];

$out2 = [];
$code2 = 0;
@exec(escapeshellarg($python) . ' -m pip --version 2>&1', $out2, $code2);
$resultado['pip'] = ['salida' => $out2, 'codigo' => $code2];

$out3 = [];
$code3 = 0;
@exec(escapeshellarg($python) . ' -m pip list 2>&1', $out3, $code3);
$resultado['pip_list'] = ['salida' => $out3, 'codigo' => $code3];

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
