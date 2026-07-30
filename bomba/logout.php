<?php

require_once __DIR__ . '/app/bootstrap.php';

SessionManager::start();
(new Auth($__bombaDb))->logout();

header('Location: login.php');
exit;
