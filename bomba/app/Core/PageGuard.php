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

/**
 * Igual que bombaRequirePageAccess(), pero en vez de por rol restringe por
 * nombre de usuario exacto. Se usa para pantallas que son solo del usuario
 * de sistemas (ej. exportar.php) y que no deben verse aunque exista otra
 * cuenta con rol admin en el futuro.
 */
function bombaRequireUsuarioExclusivo(array $usuariosPermitidos): array
{
    global $__bombaDb;

    SessionManager::start();
    $auth = new Auth($__bombaDb);

    try {
        $user = $auth->requireLogin();

        if (!in_array((string) $user['usuario'], $usuariosPermitidos, true)) {
            throw new HttpException('No tienes acceso a esta pantalla.', 403);
        }

        return $user;
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
