<?php

require_once __DIR__ . '/Core/Database.php';
require_once __DIR__ . '/Core/JsonResponse.php';
require_once __DIR__ . '/Core/Request.php';
require_once __DIR__ . '/Clases/BitacoraSistema.php';
require_once __DIR__ . '/Core/SystemBootstrap.php';
require_once __DIR__ . '/Core/SessionManager.php';
require_once __DIR__ . '/Core/Auth.php';

$__mexquiticDb = Database::connection();
SystemBootstrap::ensure($__mexquiticDb);
