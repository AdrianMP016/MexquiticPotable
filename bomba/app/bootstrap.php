<?php

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/JsonResponse.php';
require_once __DIR__ . '/Core/Request.php';
require_once __DIR__ . '/Clases/BitacoraBomba.php';
require_once __DIR__ . '/Core/SystemBootstrap.php';
require_once __DIR__ . '/Core/SessionManager.php';
require_once __DIR__ . '/Core/Auth.php';

$__bombaDb = Database::connection();
SystemBootstrap::ensure($__bombaDb);
