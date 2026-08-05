<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Clases/Activaciones.php';
require_once __DIR__ . '/../app/Clases/ReglaAutomatica.php';
require_once __DIR__ . '/../app/Clases/ReglaTemporal.php';
require_once __DIR__ . '/../app/Clases/UsuariosBomba.php';
require_once __DIR__ . '/../app/Clases/WebPushClient.php';

function bombaAccionesPermitidas(string $accion): array
{
    $map = [
        'auth.login' => ['admin', 'operador'],
        'auth.logout' => ['admin', 'operador'],
        'auth.session' => ['admin', 'operador'],

        'activaciones.estado' => ['admin', 'operador'],
        'activaciones.encender' => ['admin', 'operador'],
        'activaciones.apagar' => ['admin', 'operador'],
        'activaciones.cronometro' => ['admin', 'operador'],
        'activaciones.cancelarCronometro' => ['admin', 'operador'],
        'activaciones.estadisticas' => ['admin', 'operador'],
        'activaciones.resumenMensual' => ['admin', 'operador'],
        'activaciones.detalleDia' => ['admin', 'operador'],
        'activaciones.verificarConexion' => ['admin', 'operador'],
        'activaciones.diagnosticoCron' => ['admin'],

        'regla.obtenerActiva' => ['admin', 'operador'],
        'regla.guardar' => ['admin'],

        'reglaTemporal.obtenerActiva' => ['admin', 'operador'],
        'reglaTemporal.guardar' => ['admin'],
        'reglaTemporal.cancelar' => ['admin'],

        'usuarios.listar' => ['admin'],
        'usuarios.obtener' => ['admin'],
        'usuarios.guardar' => ['admin'],
        'usuarios.actualizar' => ['admin'],
        'usuarios.restablecerPassword' => ['admin'],
        'usuarios.baja' => ['admin'],

        'bitacora.listar' => ['admin', 'operador'],

        'push.clavePublica' => ['admin', 'operador'],
        'push.suscribir' => ['admin', 'operador'],
        'push.desuscribir' => ['admin', 'operador'],
    ];

    return $map[$accion] ?? [];
}

function bombaDescripcionBitacora(string $accion): ?array
{
    $map = [
        'activaciones.encender' => 'Encendido manual de la bomba.',
        'activaciones.apagar' => 'Apagado manual de la bomba.',
        'activaciones.cronometro' => 'Cronometro iniciado.',
        'activaciones.cancelarCronometro' => 'Cronometro cancelado.',
        'regla.guardar' => 'Cambio en la regla automatica.',
        'usuarios.guardar' => 'Alta de usuario.',
        'usuarios.actualizar' => 'Actualizacion de usuario.',
        'usuarios.restablecerPassword' => 'Restablecimiento de contraseña.',
        'usuarios.baja' => 'Baja de usuario.',
    ];

    return isset($map[$accion]) ? ['descripcion' => $map[$accion]] : null;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::error('Metodo no permitido.', [], 405);
    }

    $accion = Request::input('accion');

    if (!$accion) {
        JsonResponse::error('No se recibio la accion solicitada.');
    }

    global $__bombaDb;
    $db = $__bombaDb ?? Database::connection();
    $auth = new Auth($db);

    SessionManager::start();

    if ($accion === 'auth.login') {
        $data = $auth->login((string) Request::input('usuario', ''), (string) Request::input('password', ''));
        JsonResponse::success('Sesion iniciada correctamente.', $data);
    }

    $rolesPermitidos = bombaAccionesPermitidas($accion);

    if (empty($rolesPermitidos)) {
        JsonResponse::error('La accion solicitada no existe: ' . $accion, [], 404);
    }

    $currentUser = $auth->requireRol($rolesPermitidos);

    switch ($accion) {
        case 'auth.logout':
            $auth->logout();
            JsonResponse::success('Sesion cerrada correctamente.', []);
            break;

        case 'auth.session':
            JsonResponse::success('Sesion activa.', ['user' => $currentUser]);
            break;

        case 'activaciones.estado':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            JsonResponse::success('Estado consultado correctamente.', $activaciones->estado());
            break;

        case 'activaciones.encender':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            $data = $activaciones->encenderManual($currentUser);
            JsonResponse::success('Bomba encendida correctamente.', $data);
            break;

        case 'activaciones.apagar':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            $data = $activaciones->apagarManual($currentUser);
            JsonResponse::success('Bomba apagada correctamente.', $data);
            break;

        case 'activaciones.cronometro':
            $horas = (int) Request::input('horas', 0);
            $minutos = (int) Request::input('minutos', 0);
            $duracionSegundos = ($horas * 3600) + ($minutos * 60);
            $activaciones = new Activaciones($db, new ShellyClient($db));
            $data = $activaciones->iniciarCronometro($currentUser, $duracionSegundos);
            JsonResponse::success('Cronometro iniciado correctamente.', $data);
            break;

        case 'activaciones.cancelarCronometro':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            $data = $activaciones->cancelarCronometro($currentUser);
            JsonResponse::success('Cronometro cancelado correctamente.', $data);
            break;

        case 'activaciones.estadisticas':
            $periodo = (string) Request::input('periodo', 'dia');
            $activaciones = new Activaciones($db, new ShellyClient($db));
            JsonResponse::success('Estadisticas consultadas correctamente.', $activaciones->estadisticas($periodo));
            break;

        case 'activaciones.resumenMensual':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            $data = $activaciones->resumenMensual(
                (int) Request::input('anio', date('Y')),
                (int) Request::input('mes', date('n'))
            );
            JsonResponse::success('Resumen mensual consultado correctamente.', $data);
            break;

        case 'activaciones.detalleDia':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            $data = $activaciones->detalleDia((string) Request::input('fecha', ''));
            JsonResponse::success('Detalle del dia consultado correctamente.', ['activaciones' => $data]);
            break;

        case 'activaciones.verificarConexion':
            $shelly = new ShellyClient($db);
            JsonResponse::success('Conexion verificada.', $shelly->verificarConexion());
            break;

        case 'activaciones.diagnosticoCron':
            $activaciones = new Activaciones($db, new ShellyClient($db));
            JsonResponse::success('Diagnostico consultado correctamente.', $activaciones->diagnosticoCron());
            break;

        case 'regla.obtenerActiva':
            $regla = new ReglaAutomatica($db);
            JsonResponse::success('Regla consultada correctamente.', ['regla' => $regla->obtenerActiva()]);
            break;

        case 'regla.guardar':
            $regla = new ReglaAutomatica($db);
            $data = $regla->guardar($currentUser, Request::post());
            JsonResponse::success('Regla procesada correctamente.', $data);
            break;

        case 'reglaTemporal.obtenerActiva':
            $reglaTemporal = new ReglaTemporal($db);
            JsonResponse::success('Regla temporal consultada correctamente.', ['regla' => $reglaTemporal->obtenerActiva()]);
            break;

        case 'reglaTemporal.guardar':
            $reglaTemporal = new ReglaTemporal($db);
            $data = $reglaTemporal->guardar($currentUser, Request::post());
            JsonResponse::success('Regla temporal procesada correctamente.', $data);
            break;

        case 'reglaTemporal.cancelar':
            $reglaTemporal = new ReglaTemporal($db);
            $reglaTemporal->cancelar($currentUser);
            JsonResponse::success('Regla temporal cancelada.', []);
            break;

        case 'usuarios.listar':
            $usuarios = new UsuariosBomba($db);
            JsonResponse::success('Usuarios consultados correctamente.', ['usuarios' => $usuarios->listar()]);
            break;

        case 'usuarios.obtener':
            $usuarios = new UsuariosBomba($db);
            JsonResponse::success('Usuario cargado correctamente.', $usuarios->obtener((int) Request::input('id', 0)));
            break;

        case 'usuarios.guardar':
            $usuarios = new UsuariosBomba($db);
            $data = $usuarios->guardar(Request::post());
            JsonResponse::success('Usuario guardado correctamente.', $data);
            break;

        case 'usuarios.actualizar':
            $usuarios = new UsuariosBomba($db);
            $data = $usuarios->actualizar(Request::post());
            JsonResponse::success('Usuario actualizado correctamente.', $data);
            break;

        case 'usuarios.restablecerPassword':
            $usuarios = new UsuariosBomba($db);
            $data = $usuarios->restablecerPassword(
                (int) Request::input('id', 0),
                (string) Request::input('password', '')
            );
            JsonResponse::success('Contraseña restablecida correctamente.', $data);
            break;

        case 'usuarios.baja':
            $usuarios = new UsuariosBomba($db);
            $data = $usuarios->darDeBaja((int) Request::input('id', 0), (int) ($currentUser['id'] ?? 0));
            JsonResponse::success('Usuario dado de baja correctamente.', $data);
            break;

        case 'bitacora.listar':
            $bitacora = new BitacoraBomba($db);
            $data = $bitacora->listar(
                [
                    'usuario' => (string) Request::input('usuario', ''),
                    'accion' => (string) Request::input('accion_filtro', ''),
                    'fecha_desde' => (string) Request::input('fecha_desde', ''),
                    'fecha_hasta' => (string) Request::input('fecha_hasta', ''),
                ],
                (int) Request::input('page', 1),
                (int) Request::input('per_page', 25)
            );
            JsonResponse::success('Bitacora consultada correctamente.', $data);
            break;

        case 'push.clavePublica':
            $webPush = new WebPushClient($db);
            JsonResponse::success('Clave publica consultada.', ['clave_publica' => $webPush->clavePublica()]);
            break;

        case 'push.suscribir':
            $webPush = new WebPushClient($db);
            $webPush->suscribir(
                (int) ($currentUser['id'] ?? 0) ?: null,
                (string) Request::input('endpoint', ''),
                (string) Request::input('p256dh', ''),
                (string) Request::input('auth', ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
            JsonResponse::success('Suscripcion guardada.', []);
            break;

        case 'push.desuscribir':
            $webPush = new WebPushClient($db);
            $webPush->desuscribir((string) Request::input('endpoint', ''));
            JsonResponse::success('Suscripcion eliminada.', []);
            break;

        default:
            JsonResponse::error('La accion solicitada no existe: ' . $accion, [], 404);
    }

    $descripcion = bombaDescripcionBitacora($accion);
    if ($descripcion !== null && !in_array($accion, ['auth.login', 'auth.logout'], true)) {
        (new BitacoraBomba($db))->registrar([
            'usuario_bomba_id' => (int) ($currentUser['id'] ?? 0),
            'nombre_usuario' => (string) ($currentUser['nombre'] ?? ''),
            'rol' => (string) ($currentUser['rol'] ?? ''),
            'accion' => $accion,
            'descripcion' => $descripcion['descripcion'],
        ]);
    }
} catch (InvalidArgumentException $exception) {
    $errors = json_decode($exception->getMessage(), true);

    if (!is_array($errors)) {
        $errors = ['general' => $exception->getMessage()];
    }

    JsonResponse::error('Revisa los datos capturados.', $errors, 422);
} catch (HttpException $exception) {
    JsonResponse::error($exception->getMessage(), [], $exception->statusCode());
} catch (PDOException $exception) {
    JsonResponse::error('Error al conectar o guardar en la base de datos.', [
        'detalle' => $exception->getMessage(),
    ], 500);
} catch (Throwable $exception) {
    JsonResponse::error($exception->getMessage(), [], 500);
}
