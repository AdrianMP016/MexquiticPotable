<?php

require_once dirname(__DIR__) . '/bootstrap.php';

function bombaRequirePageAccess(array $rolesPermitidos): array
{
    global $__bombaDb;

    SessionManager::start();
    $auth = new Auth($__bombaDb);

    try {
        return $auth->requireRol($rolesPermitidos);
    } catch (HttpException $exception) {
        $user = $auth->user();

        if ($user) {
            header('Location: index.php');
            exit;
        }

        $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        header('Location: login.php?next=' . rawurlencode($next));
        exit;
    }
}
