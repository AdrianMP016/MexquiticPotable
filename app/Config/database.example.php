<?php

$hostCandidates = [
    strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')),
    strtolower((string) ($_SERVER['SERVER_NAME'] ?? '')),
];

$isLocal = PHP_OS_FAMILY === 'Windows'
    || in_array('localhost', $hostCandidates, true)
    || in_array('127.0.0.1', $hostCandidates, true)
    || in_array('::1', $hostCandidates, true)
    || str_contains(strtolower(__DIR__), 'wamp64');

if ($isLocal) {
    return [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'mezquitic_agua',
        'username' => 'root',
        'password' => 'admin',
        'charset' => 'utf8mb4',
    ];
}

return [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'TU_BASE_DE_DATOS',
    'username' => 'TU_USUARIO',
    'password' => 'TU_PASSWORD',
    'charset' => 'utf8mb4',
];