<?php

header('Content-Type: application/json; charset=utf-8');

$resultado = [
    'exec_existe' => function_exists('exec'),
    'shell_exec_existe' => function_exists('shell_exec'),
    'disable_functions' => ini_get('disable_functions'),
    'open_basedir' => ini_get('open_basedir'),
];

if (function_exists('exec')) {
    $out1 = [];
    $code1 = 0;
    @exec('python3 --version 2>&1', $out1, $code1);
    $resultado['python3_version'] = ['salida' => $out1, 'codigo' => $code1];

    $out2 = [];
    $code2 = 0;
    @exec('python --version 2>&1', $out2, $code2);
    $resultado['python_version'] = ['salida' => $out2, 'codigo' => $code2];

    $out3 = [];
    $code3 = 0;
    @exec('which python3 2>&1', $out3, $code3);
    $resultado['which_python3'] = ['salida' => $out3, 'codigo' => $code3];

    $out4 = [];
    $code4 = 0;
    @exec('python3 -c "import openpyxl; print(openpyxl.__version__)" 2>&1', $out4, $code4);
    $resultado['openpyxl_version'] = ['salida' => $out4, 'codigo' => $code4];
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
