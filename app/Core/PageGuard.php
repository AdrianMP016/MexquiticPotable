<?php

require_once dirname(__DIR__) . '/bootstrap.php';

function mexquiticRequirePageAccess(string $modulo, string $descripcionAcceso): Auth
{
    global $__mexquiticDb;

    SessionManager::setContext($modulo);
    SessionManager::start();
    $auth = new Auth($__mexquiticDb);

    try {
        $user = $auth->requireModule($modulo);
        $auth->registrarAccesoModulo($modulo, $descripcionAcceso);
        return $auth;
    } catch (HttpException $exception) {
        $user = $auth->user();

        if ($user) {
            header('Location: ' . $auth->defaultDestination($user));
            exit;
        }

        $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $loginRoutes = [
            'plataforma' => 'login-admin.php',
            'cobro' => 'login-cobro.php',
            'verificador' => 'login-verificador.php',
        ];
        $loginRoute = $loginRoutes[$modulo] ?? 'login-admin.php';
        header('Location: ' . $loginRoute . '?next=' . rawurlencode($next));
        exit;
    }
}
