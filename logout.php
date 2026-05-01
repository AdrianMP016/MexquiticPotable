<?php

require_once __DIR__ . '/app/bootstrap.php';

$module = SessionManager::normalizeContext((string) ($_GET['module'] ?? 'plataforma'));
SessionManager::setContext($module);
SessionManager::start();
$auth = new Auth($__mexquiticDb);
$auth->logout($module);

if ($module === 'cobro') {
    header('Location: login-cobro.php');
    exit;
}

if ($module === 'verificador') {
    header('Location: login-verificador.php');
    exit;
}

header('Location: login-admin.php');
exit;
