<?php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/Clases/Usuarios.php';
require_once __DIR__ . '/../app/Clases/Medidores.php';
require_once __DIR__ . '/../app/Clases/Periodos.php';
require_once __DIR__ . '/../app/Clases/Rutas.php';
require_once __DIR__ . '/../app/Clases/Recibos.php';
require_once __DIR__ . '/../app/Clases/Pagos.php';
require_once __DIR__ . '/../app/Clases/WhatsApp.php';
require_once __DIR__ . '/../app/Clases/Verificador.php';
require_once __DIR__ . '/../app/Clases/ImportadorPadronExcel.php';
require_once __DIR__ . '/../app/Clases/UsuariosSistema.php';

function mexquiticAllowedModules(string $accion): array
{
    $map = [
        'usuarios.guardar' => ['plataforma'],
        'usuarios.buscarDuplicados' => ['plataforma'],
        'usuarios.listar' => ['plataforma'],
        'usuarios.obtener' => ['plataforma'],
        'usuarios.actualizar' => ['plataforma'],
        'usuarios.baja' => ['plataforma'],
        'medidores.listar' => ['plataforma'],
        'medidores.usuariosDisponibles' => ['plataforma'],
        'medidores.obtener' => ['plataforma'],
        'medidores.guardar' => ['plataforma'],
        'medidores.actualizar' => ['plataforma'],
        'periodos.listar' => ['plataforma'],
        'periodos.obtener' => ['plataforma'],
        'periodos.guardar' => ['plataforma'],
        'periodos.actualizar' => ['plataforma'],
        'periodos.baja' => ['plataforma'],
        'rutas.listar' => ['plataforma'],
        'rutas.catalogo' => ['plataforma', 'verificador'],
        'rutas.obtener' => ['plataforma'],
        'rutas.guardar' => ['plataforma'],
        'rutas.actualizar' => ['plataforma'],
        'rutas.baja' => ['plataforma'],
        'padron.importar' => ['plataforma'],
        'verificador.guardarMedicion' => ['verificador'],
        'verificador.buscarUsuarios' => ['verificador'],
        'verificador.obtenerUsuario' => ['verificador'],
        'lecturas.listar' => ['plataforma'],
        'lecturas.obtener' => ['plataforma'],
        'recibos.generar' => ['plataforma'],
        'recibos.previsualizarPeriodo' => ['plataforma'],
        'recibos.prepararImpresion' => ['plataforma'],
        'recibos.enviarWhatsApp' => ['plataforma'],
        'recibos.notificaciones' => ['plataforma'],
        'recibos.notificarWhatsApp' => ['plataforma'],
        'pagos.usuarios' => ['plataforma'],
        'pagos.recibos' => ['plataforma'],
        'pagos.obtenerRecibo' => ['plataforma'],
        'pagos.reciboPorQr' => ['cobro'],
        'pagos.actualizarEntrega' => ['plataforma', 'cobro'],
        'pagos.registrar' => ['plataforma'],
        'pagos.registrarPorQr' => ['cobro'],
        'pagos.eliminar' => ['plataforma'],
        'whatsapp.panel' => ['plataforma'],
        'whatsapp.logout' => ['plataforma'],
        'usuariosSistema.listar' => ['plataforma'],
        'usuariosSistema.obtener' => ['plataforma'],
        'usuariosSistema.guardar' => ['plataforma'],
        'usuariosSistema.actualizar' => ['plataforma'],
        'usuariosSistema.baja' => ['plataforma'],
        'usuariosSistema.resetPassword' => ['plataforma'],
        'bitacora.listar' => ['plataforma'],
        'auth.session' => ['plataforma', 'cobro', 'verificador'],
        'auth.logout' => ['plataforma', 'cobro', 'verificador'],
    ];

    return $map[$accion] ?? [];
}

function mexquiticAutorizarAccion(Auth $auth, string $accion): ?array
{
    $modules = mexquiticAllowedModules($accion);

    if (empty($modules)) {
        return null;
    }

    $payload = $auth->sessionPayload();
    $user = $payload['user'];

    foreach ($modules as $module) {
        if ($auth->canAccessModule((string) $user['rol'], $module)) {
            return $auth->requireModule($module);
        }
    }

    throw new HttpException('Tu cuenta no tiene acceso a esta acción.', 403);
}

function mexquiticRegistrarBitacora(Auth $auth, PDO $db, string $accion, $data = null): void
{
    $acciones = [
        'usuarios.guardar' => ['modulo' => 'plataforma', 'descripcion' => 'Alta de usuario de servicio.'],
        'usuarios.actualizar' => ['modulo' => 'plataforma', 'descripcion' => 'Actualización de usuario de servicio.'],
        'usuarios.baja' => ['modulo' => 'plataforma', 'descripcion' => 'Baja de usuario de servicio.'],
        'medidores.guardar' => ['modulo' => 'plataforma', 'descripcion' => 'Alta de medidor.'],
        'medidores.actualizar' => ['modulo' => 'plataforma', 'descripcion' => 'Actualización de medidor.'],
        'periodos.guardar' => ['modulo' => 'plataforma', 'descripcion' => 'Alta de periodo bimestral.'],
        'periodos.actualizar' => ['modulo' => 'plataforma', 'descripcion' => 'Actualización de periodo bimestral.'],
        'periodos.baja' => ['modulo' => 'plataforma', 'descripcion' => 'Baja de periodo bimestral.'],
        'rutas.guardar' => ['modulo' => 'plataforma', 'descripcion' => 'Alta de ruta.'],
        'rutas.actualizar' => ['modulo' => 'plataforma', 'descripcion' => 'Actualización de ruta.'],
        'rutas.baja' => ['modulo' => 'plataforma', 'descripcion' => 'Baja de ruta.'],
        'padron.importar' => ['modulo' => 'plataforma', 'descripcion' => 'Importación de padrón desde Excel.'],
        'verificador.guardarMedicion' => ['modulo' => 'verificador', 'descripcion' => 'Captura o actualización de lectura.'],
        'recibos.generar' => ['modulo' => 'plataforma', 'descripcion' => 'Generación de recibo individual.'],
        'recibos.previsualizarPeriodo' => ['modulo' => 'plataforma', 'descripcion' => 'Preparación de vista previa masiva de recibos.'],
        'recibos.enviarWhatsApp' => ['modulo' => 'plataforma', 'descripcion' => 'Envío de recibo por WhatsApp.'],
        'recibos.notificarWhatsApp' => ['modulo' => 'plataforma', 'descripcion' => 'Envío de notificación masiva por WhatsApp.'],
        'pagos.actualizarEntrega' => ['modulo' => 'cobro', 'descripcion' => 'Marcado de entrega de recibo.'],
        'pagos.registrar' => ['modulo' => 'plataforma', 'descripcion' => 'Registro manual de pago desde plataforma.'],
        'pagos.registrarPorQr' => ['modulo' => 'cobro', 'descripcion' => 'Registro de pago desde cobro por QR.'],
        'pagos.eliminar' => ['modulo' => 'plataforma', 'descripcion' => 'Eliminación de pago registrado.'],
        'usuariosSistema.guardar' => ['modulo' => 'plataforma', 'descripcion' => 'Alta de usuario del sistema.'],
        'usuariosSistema.actualizar' => ['modulo' => 'plataforma', 'descripcion' => 'Actualización de usuario del sistema.'],
        'usuariosSistema.baja' => ['modulo' => 'plataforma', 'descripcion' => 'Baja de usuario del sistema.'],
        'usuariosSistema.resetPassword' => ['modulo' => 'plataforma', 'descripcion' => 'Restablecimiento de contraseña de usuario del sistema.'],
    ];

    if (!isset($acciones[$accion])) {
        return;
    }

    $user = $auth->user();
    if (!$user) {
        return;
    }

    $referenceId = null;
    if (is_array($data)) {
        foreach (['usuario_id', 'medidor_id', 'ruta_id', 'periodo_id', 'lectura_id', 'recibo_id', 'pago_id', 'id'] as $key) {
            if (!empty($data[$key])) {
                $referenceId = (string) $data[$key];
                break;
            }
        }
    }

    $bitacora = new BitacoraSistema($db);
    $bitacora->registrar([
        'usuario_sistema_id' => (int) ($user['id'] ?? 0),
        'nombre_usuario' => (string) ($user['nombre'] ?? ''),
        'rol' => (string) ($user['rol'] ?? ''),
        'modulo' => $acciones[$accion]['modulo'],
        'accion' => $accion,
        'referencia_tipo' => strtok($accion, '.'),
        'referencia_id' => $referenceId,
        'descripcion' => $acciones[$accion]['descripcion'],
        'payload_json' => is_array($data) ? $data : null,
        'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::error('Metodo no permitido.', [], 405);
    }

    $accion = Request::input('accion');

    if (!$accion) {
        JsonResponse::error('No se recibio la accion solicitada.');
    }

    $requestedContext = SessionManager::normalizeContext(
        (string) Request::input('modulo_contexto', (string) Request::input('modulo', 'plataforma'))
    );
    SessionManager::setContext($requestedContext);
    SessionManager::start();

    $db = $__mexquiticDb ?? Database::connection();
    $auth = new Auth($db);

    if ($accion === 'auth.login') {
        $data = $auth->login(
            (string) Request::input('usuario', ''),
            (string) Request::input('password', ''),
            (string) Request::input('modulo', 'plataforma')
        );
        JsonResponse::success('Sesión iniciada correctamente.', $data);
    }

    if ($accion === 'auth.recuperar') {
        $data = $auth->recuperarPassword(
            (string) Request::input('usuario', ''),
            (string) Request::input('telefono', ''),
            (string) Request::input('nueva_password', '')
        );
        JsonResponse::success('Contraseña actualizada correctamente.', $data);
    }

    $currentUser = mexquiticAutorizarAccion($auth, $accion);
    if ($currentUser) {
        $_POST['_usuario_sistema_id'] = (int) ($currentUser['id'] ?? 0);
    }

    switch ($accion) {
        case 'auth.session':
            JsonResponse::success('Sesión consultada correctamente.', $auth->sessionPayload($requestedContext));
            break;

        case 'auth.logout':
            $auth->logout($requestedContext);
            JsonResponse::success('Sesión cerrada correctamente.', []);
            break;

        case 'usuarios.guardar':
            $usuarios = new Usuarios($db);
            $fachada = $_FILES['fachada'] ?? null;
            $data = $usuarios->guardar(Request::post(), $fachada);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Usuario guardado correctamente.', $data);
            break;

        case 'usuarios.buscarDuplicados':
            $usuarios = new Usuarios($db);
            $termino = Request::cleanString(Request::input('termino', '')) ?? '';
            $data = $usuarios->buscarDuplicados($termino);
            JsonResponse::success('Busqueda realizada correctamente.', [
                'coincidencias' => $data,
                'total' => count($data),
            ]);
            break;

        case 'usuarios.listar':
            $usuarios = new Usuarios($db);
            $nombre = Request::cleanString(Request::input('nombre', '')) ?? '';
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $data = $usuarios->listar($nombre, $page, $perPage);
            JsonResponse::success('Usuarios consultados correctamente.', [
                'usuarios' => $data['usuarios'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'usuarios.obtener':
            $usuarios = new Usuarios($db);
            $usuarioId = (int) Request::input('usuario_id', 0);
            $data = $usuarios->obtener($usuarioId);
            JsonResponse::success('Usuario cargado correctamente.', $data);
            break;

        case 'usuarios.actualizar':
            $usuarios = new Usuarios($db);
            $fachada = $_FILES['fachada'] ?? null;
            $data = $usuarios->actualizar(Request::post(), $fachada);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Usuario actualizado correctamente.', $data);
            break;

        case 'usuarios.baja':
            $usuarios = new Usuarios($db);
            $usuarioId = (int) Request::input('usuario_id', 0);
            $data = $usuarios->darDeBaja($usuarioId);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Usuario dado de baja correctamente.', $data);
            break;

        case 'medidores.listar':
            $medidores = new Medidores($db);
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $buscar = (string) Request::input('buscar', '');
            $campo = (string) Request::input('campo', 'todos');
            $data = $medidores->listar($page, $perPage, $buscar, $campo);
            JsonResponse::success('Medidores consultados correctamente.', [
                'medidores' => $data['medidores'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'medidores.usuariosDisponibles':
            $medidores = new Medidores($db);
            $data = $medidores->usuariosDisponibles();
            JsonResponse::success('Usuarios disponibles consultados correctamente.', [
                'usuarios' => $data,
                'total' => count($data),
            ]);
            break;

        case 'medidores.obtener':
            $medidores = new Medidores($db);
            $medidorId = (int) Request::input('medidor_id', 0);
            $data = $medidores->obtener($medidorId);
            JsonResponse::success('Medidor cargado correctamente.', $data);
            break;

        case 'medidores.guardar':
            $medidores = new Medidores($db);
            $data = $medidores->guardar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Medidor guardado correctamente.', $data);
            break;

        case 'medidores.actualizar':
            $medidores = new Medidores($db);
            $data = $medidores->actualizar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Medidor actualizado correctamente.', $data);
            break;

        case 'periodos.listar':
            $periodos = new Periodos($db);
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $data = $periodos->listar($page, $perPage);
            JsonResponse::success('Periodos consultados correctamente.', [
                'periodos' => $data['periodos'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'periodos.obtener':
            $periodos = new Periodos($db);
            $periodoId = (int) Request::input('periodo_id', 0);
            $data = $periodos->obtener($periodoId);
            JsonResponse::success('Periodo cargado correctamente.', $data);
            break;

        case 'periodos.guardar':
            $periodos = new Periodos($db);
            $data = $periodos->guardar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Periodo guardado correctamente.', $data);
            break;

        case 'periodos.actualizar':
            $periodos = new Periodos($db);
            $data = $periodos->actualizar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Periodo actualizado correctamente.', $data);
            break;

        case 'periodos.baja':
            $periodos = new Periodos($db);
            $periodoId = (int) Request::input('periodo_id', 0);
            $data = $periodos->darDeBaja($periodoId);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Periodo dado de baja correctamente.', $data);
            break;

        case 'rutas.listar':
            $rutas = new Rutas($db);
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $data = $rutas->listar($page, $perPage);
            JsonResponse::success('Rutas consultadas correctamente.', [
                'rutas' => $data['rutas'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'rutas.catalogo':
            $rutas = new Rutas($db);
            $comunidadId = (int) Request::input('comunidad_id', 0);
            $data = $rutas->catalogo($comunidadId);
            JsonResponse::success('Catalogo de rutas consultado correctamente.', [
                'rutas' => $data,
                'total' => count($data),
            ]);
            break;

        case 'rutas.obtener':
            $rutas = new Rutas($db);
            $rutaId = (int) Request::input('ruta_id', 0);
            $data = $rutas->obtener($rutaId);
            JsonResponse::success('Ruta cargada correctamente.', $data);
            break;

        case 'rutas.guardar':
            $rutas = new Rutas($db);
            $data = $rutas->guardar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Ruta guardada correctamente.', $data);
            break;

        case 'rutas.actualizar':
            $rutas = new Rutas($db);
            $data = $rutas->actualizar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Ruta actualizada correctamente.', $data);
            break;

        case 'rutas.baja':
            $rutas = new Rutas($db);
            $rutaId = (int) Request::input('ruta_id', 0);
            $data = $rutas->darDeBaja($rutaId);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Ruta dada de baja correctamente.', $data);
            break;

        case 'padron.importar':
            $importador = new ImportadorPadronExcel($db);
            $excelPath = Request::input(
                'excel_path',
                'C:/Users/Acer_V/Documents/Proyectos Lalo/Mezquitic/PADRON ACTUALIZADO AL DIA 27 DE JUNIO.xlsx'
            );
            $data = $importador->importarPadronExcel((string) $excelPath);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Padron importado correctamente.', $data);
            break;

        case 'verificador.guardarMedicion':
            $verificador = new Verificador($db);
            $foto = $_FILES['foto_medidor'] ?? null;
            $data = $verificador->guardarMedicion(Request::post(), $foto);
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Medicion guardada correctamente.', $data);
            break;

        case 'verificador.buscarUsuarios':
            $verificador = new Verificador($db);
            $termino = Request::cleanString(Request::input('termino', '')) ?? '';
            $data = $verificador->buscarUsuarios($termino);
            if ($currentUser) {
                (new BitacoraSistema($db))->registrar([
                    'usuario_sistema_id' => (int) ($currentUser['id'] ?? 0),
                    'nombre_usuario' => (string) ($currentUser['nombre'] ?? ''),
                    'rol' => (string) ($currentUser['rol'] ?? ''),
                    'modulo' => 'verificador',
                    'accion' => 'verificador.buscarUsuarios',
                    'descripcion' => 'Consulta de usuarios por ruta en verificador.',
                    'payload_json' => ['termino' => $termino, 'total' => count($data)],
                    'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45),
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]);
            }
            JsonResponse::success('Busqueda de verificador realizada correctamente.', [
                'coincidencias' => $data,
                'total' => count($data),
            ]);
            break;

        case 'verificador.obtenerUsuario':
            $verificador = new Verificador($db);
            $usuarioId = (int) Request::input('usuario_id', 0);
            $data = $verificador->obtenerUsuario($usuarioId);
            if ($currentUser) {
                (new BitacoraSistema($db))->registrar([
                    'usuario_sistema_id' => (int) ($currentUser['id'] ?? 0),
                    'nombre_usuario' => (string) ($currentUser['nombre'] ?? ''),
                    'rol' => (string) ($currentUser['rol'] ?? ''),
                    'modulo' => 'verificador',
                    'accion' => 'verificador.obtenerUsuario',
                    'referencia_tipo' => 'usuario_servicio',
                    'referencia_id' => (string) $usuarioId,
                    'descripcion' => 'Consulta de detalle de usuario en verificador.',
                    'payload_json' => ['usuario_id' => $usuarioId],
                    'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45),
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]);
            }
            JsonResponse::success('Usuario del verificador cargado correctamente.', $data);
            break;

        case 'lecturas.listar':
            $recibos = new Recibos($db);
            $termino = Request::cleanString(Request::input('termino', '')) ?? '';
            $estadoCobro = Request::cleanString(Request::input('estado_cobro', '')) ?? '';
            $periodoId = (int) Request::input('periodo_id', 0);
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $data = $recibos->listarLecturas($termino, $estadoCobro, $periodoId, $page, $perPage);
            JsonResponse::success('Lecturas consultadas correctamente.', [
                'lecturas' => $data['lecturas'],
                'summary' => $data['summary'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'lecturas.obtener':
            $recibos = new Recibos($db);
            $lecturaId = (int) Request::input('lectura_id', 0);
            $data = $recibos->obtenerLectura($lecturaId);
            JsonResponse::success('Lectura cargada correctamente.', $data);
            break;

        case 'recibos.generar':
            $recibos = new Recibos($db);
            $data = $recibos->generar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Recibo generado correctamente.', $data);
            break;

        case 'recibos.previsualizarPeriodo':
            $recibos = new Recibos($db);
            $data = $recibos->previsualizarPeriodo(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, ['periodo_id' => Request::input('periodo_id', 0)] + $data);
            JsonResponse::success('Vista previa de recibos preparada correctamente.', $data);
            break;

        case 'recibos.prepararImpresion':
            $recibos = new Recibos($db);
            $data = $recibos->prepararImpresion(Request::post());
            JsonResponse::success('Datos de impresion preparados correctamente.', $data);
            break;

        case 'recibos.enviarWhatsApp':
            $recibos = new Recibos($db);
            $whatsApp = new WhatsApp();
            $reciboId = (int) Request::input('recibo_id', 0);
            $data = $recibos->enviarWhatsApp($reciboId, $whatsApp);
            mexquiticRegistrarBitacora($auth, $db, $accion, ['recibo_id' => $reciboId] + $data);
            JsonResponse::success('Recibo enviado por WhatsApp correctamente.', $data);
            break;

        case 'recibos.notificaciones':
            $recibos = new Recibos($db);
            $data = $recibos->listarNotificaciones(Request::post());
            JsonResponse::success('Lista de notificaciones preparada correctamente.', [
                'notificaciones' => $data,
                'total' => count($data),
            ]);
            break;

        case 'recibos.notificarWhatsApp':
            $recibos = new Recibos($db);
            $whatsApp = new WhatsApp();
            $reciboId = (int) Request::input('recibo_id', 0);
            $tipoMensaje = Request::cleanString(Request::input('tipo_mensaje', 'recordatorio')) ?? 'recordatorio';
            $data = $recibos->notificarWhatsApp($reciboId, $tipoMensaje, $whatsApp);
            mexquiticRegistrarBitacora($auth, $db, $accion, ['recibo_id' => $reciboId, 'tipo_mensaje' => $tipoMensaje] + $data);
            JsonResponse::success('Notificacion enviada por WhatsApp correctamente.', $data);
            break;

        case 'pagos.usuarios':
            $pagos = new Pagos($db);
            $termino = Request::cleanString(Request::input('termino', '')) ?? '';
            $data = $pagos->usuarios($termino);
            JsonResponse::success('Usuarios consultados correctamente.', [
                'usuarios' => $data,
                'total' => count($data),
            ]);
            break;

        case 'pagos.recibos':
            $pagos = new Pagos($db);
            $usuarioId = (int) Request::input('usuario_id', 0);
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $data = $pagos->recibos($usuarioId, $page, $perPage);
            JsonResponse::success('Recibos consultados correctamente.', [
                'recibos' => $data['recibos'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'pagos.obtenerRecibo':
            $pagos = new Pagos($db);
            $reciboId = (int) Request::input('recibo_id', 0);
            $data = $pagos->obtenerRecibo($reciboId);
            JsonResponse::success('Recibo cargado correctamente.', $data);
            break;

        case 'pagos.reciboPorQr':
            $pagos = new Pagos($db);
            $qrToken = Request::cleanString(Request::input('qr_token', '')) ?? '';
            $data = $pagos->obtenerReciboPorQr($qrToken);
            if ($currentUser) {
                (new BitacoraSistema($db))->registrar([
                    'usuario_sistema_id' => (int) ($currentUser['id'] ?? 0),
                    'nombre_usuario' => (string) ($currentUser['nombre'] ?? ''),
                    'rol' => (string) ($currentUser['rol'] ?? ''),
                    'modulo' => 'cobro',
                    'accion' => 'pagos.reciboPorQr',
                    'referencia_tipo' => 'recibo',
                    'referencia_id' => !empty($data['recibo_id']) ? (string) $data['recibo_id'] : null,
                    'descripcion' => 'Consulta de recibo desde QR en cobro.',
                    'payload_json' => ['recibo_id' => $data['recibo_id'] ?? null, 'folio' => $data['folio'] ?? null],
                    'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45),
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]);
            }
            JsonResponse::success('Recibo localizado correctamente por QR.', $data);
            break;

        case 'pagos.actualizarEntrega':
            $pagos = new Pagos($db);
            $reciboId = (int) Request::input('recibo_id', 0);
            $entregadoRaw = Request::input('recibo_entregado', 0);
            $entregado = in_array(mb_strtolower(trim((string) $entregadoRaw), 'UTF-8'), ['1', 'true', 'si', 'on', 'yes'], true);
            $data = $pagos->actualizarEntrega($reciboId, $entregado);
            mexquiticRegistrarBitacora($auth, $db, $accion, ['recibo_id' => $reciboId, 'recibo_entregado' => $entregado] + $data);
            JsonResponse::success('Entrega del recibo actualizada correctamente.', $data);
            break;

        case 'pagos.registrar':
            $pagos = new Pagos($db);
            $data = $pagos->registrar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Pago registrado correctamente.', $data);
            break;

        case 'pagos.registrarPorQr':
            $pagos = new Pagos($db);
            $data = $pagos->registrarPorQr(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Pago registrado correctamente desde QR.', $data);
            break;

        case 'pagos.eliminar':
            $pagos = new Pagos($db);
            $pagoId = (int) Request::input('pago_id', 0);
            $data = $pagos->eliminar($pagoId);
            mexquiticRegistrarBitacora($auth, $db, $accion, ['pago_id' => $pagoId] + $data);
            JsonResponse::success('Pago eliminado correctamente.', $data);
            break;

        case 'whatsapp.panel':
            $whatsApp = new WhatsApp();
            $messageStatus = Request::cleanString(Request::input('message_status', 'sent')) ?? 'sent';
            $data = $whatsApp->panel($messageStatus, 15);
            JsonResponse::success('Panel de WhatsApp consultado correctamente.', $data);
            break;

        case 'whatsapp.logout':
            $whatsApp = new WhatsApp();
            $data = $whatsApp->logout();
            JsonResponse::success('Sesion de WhatsApp cerrada. Escanea el nuevo QR para enlazar de nuevo.', $data);
            break;

        case 'usuariosSistema.listar':
            $usuariosSistema = new UsuariosSistema($db);
            $termino = Request::cleanString(Request::input('termino', '')) ?? '';
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $data = $usuariosSistema->listar($termino, $page, $perPage);
            JsonResponse::success('Usuarios del sistema consultados correctamente.', [
                'usuarios' => $data['usuarios'],
                'pagination' => $data['pagination'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        case 'usuariosSistema.obtener':
            $usuariosSistema = new UsuariosSistema($db);
            $usuarioSistemaId = (int) Request::input('usuario_sistema_id', 0);
            $data = $usuariosSistema->obtener($usuarioSistemaId);
            JsonResponse::success('Usuario del sistema cargado correctamente.', $data);
            break;

        case 'usuariosSistema.guardar':
            $usuariosSistema = new UsuariosSistema($db);
            $data = $usuariosSistema->guardar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Usuario del sistema guardado correctamente.', $data);
            break;

        case 'usuariosSistema.actualizar':
            $usuariosSistema = new UsuariosSistema($db);
            $data = $usuariosSistema->actualizar(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Usuario del sistema actualizado correctamente.', $data);
            break;

        case 'usuariosSistema.baja':
            $usuariosSistema = new UsuariosSistema($db);
            $usuarioSistemaId = (int) Request::input('usuario_sistema_id', 0);
            $data = $usuariosSistema->darDeBaja($usuarioSistemaId, (int) ($auth->userId() ?? 0));
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Usuario del sistema dado de baja correctamente.', $data);
            break;

        case 'usuariosSistema.resetPassword':
            $usuariosSistema = new UsuariosSistema($db);
            $data = $usuariosSistema->restablecerPassword(Request::post());
            mexquiticRegistrarBitacora($auth, $db, $accion, $data);
            JsonResponse::success('Contraseña restablecida correctamente.', $data);
            break;

        case 'bitacora.listar':
            $bitacora = new BitacoraSistema($db);
            $modulo = Request::cleanString(Request::input('modulo', '')) ?? '';
            $page = (int) Request::input('page', 1);
            $perPage = (int) Request::input('per_page', 25);
            $usuario = Request::cleanString(Request::input('usuario', '')) ?? '';
            $accionFiltro = Request::cleanString(Request::input('accion_filtro', '')) ?? '';
            $fechaDesde = Request::cleanString(Request::input('fecha_desde', '')) ?? '';
            $fechaHasta = Request::cleanString(Request::input('fecha_hasta', '')) ?? '';
            $data = $bitacora->listar([
                'modulo' => $modulo,
                'usuario' => $usuario,
                'accion' => $accionFiltro,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ], $page, $perPage);
            JsonResponse::success('Bitácora consultada correctamente.', [
                'logs' => $data['logs'],
                'pagination' => $data['pagination'],
                'catalogos' => $bitacora->catalogos(),
                'filters' => $data['filters'],
                'total' => $data['pagination']['total'],
            ]);
            break;

        default:
            JsonResponse::error('La accion solicitada no existe: ' . $accion, [], 404);
            break;
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
