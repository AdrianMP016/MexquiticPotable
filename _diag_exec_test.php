<?php

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ziparchive_existe' => class_exists('ZipArchive'),
    'zip_extension_loaded' => extension_loaded('zip'),
    'php_version' => PHP_VERSION,
], JSON_PRETTY_PRINT);
