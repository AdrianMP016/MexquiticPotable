<?php

require_once __DIR__ . '/PageGuard.php';
require_once dirname(__DIR__) . '/Clases/Usuarios.php';
require_once dirname(__DIR__) . '/Clases/Medidores.php';
require_once dirname(__DIR__) . '/Clases/Rutas.php';
require_once dirname(__DIR__) . '/Clases/Periodos.php';
require_once dirname(__DIR__) . '/Clases/UsuariosSistema.php';
require_once dirname(__DIR__) . '/Clases/BitacoraSistema.php';
require_once dirname(__DIR__) . '/Clases/Recibos.php';
require_once dirname(__DIR__) . '/Clases/Pagos.php';
require_once dirname(__DIR__) . '/Clases/CobroAgua.php';

function mexquiticAdminBaseBootstrap(): array
{
    return [
        'consulta' => [
            'usuarios' => [],
            'pagination' => null,
        ],
        'medidores' => [
            'medidores' => [],
            'pagination' => null,
        ],
        'rutas' => [
            'rutas' => [],
            'pagination' => null,
        ],
        'periodos' => [
            'periodos' => [],
            'pagination' => null,
        ],
        'lecturas' => [
            'lecturas' => [],
            'summary' => [
                'adeudo' => 0,
                'pendiente' => 0,
                'parcial' => 0,
                'pagado' => 0,
                'sin_recibo' => 0,
            ],
        ],
        'pagos' => [
            'usuarios' => [],
            'selected_usuario_id' => null,
            'recibos' => [],
        ],
        'sistema' => [
            'usuarios' => [],
            'pagination' => null,
            'bitacora' => [],
            'bitacoraPagination' => null,
            'catalogos' => [
                'usuarios' => [],
                'acciones' => [],
            ],
            'filters' => [
                'modulo' => '',
                'usuario' => '',
                'accion' => '',
                'fecha_desde' => '',
                'fecha_hasta' => '',
            ],
        ],
        'cobro_agua' => [],
    ];
}

function mexquiticAdminBootstrapData(PDO $db, array $currentUser, string $view): array
{
    $bootstrapData = mexquiticAdminBaseBootstrap();
    try {
        $bootstrapData['cobro_agua'] = (new CobroAgua($db))->parametrosFrontend();
    } catch (Throwable $exception) {
    }

    try {
        switch ($view) {
            case 'consulta':
                $usuarios = new Usuarios($db);
                $bootstrapData['consulta'] = $usuarios->listar('', 1, 30);
                break;

            case 'medidores':
                $medidores = new Medidores($db);
                $bootstrapData['medidores'] = $medidores->listar(1, 0);
                break;

            case 'rutas':
                $rutas = new Rutas($db);
                $bootstrapData['rutas'] = $rutas->listar(1, 0);
                break;

            case 'periodos':
                $periodos = new Periodos($db);
                $bootstrapData['periodos'] = $periodos->listar(1, 0);
                break;

            case 'lecturas':
                $recibos = new Recibos($db);
                $lecturas = $recibos->listarLecturas('', '', 0);
                $summary = $bootstrapData['lecturas']['summary'];

                foreach ($lecturas as $row) {
                    $estado = (string) ($row['estado_cobro'] ?? 'sin_recibo');
                    if (!array_key_exists($estado, $summary)) {
                        $summary[$estado] = 0;
                    }
                    $summary[$estado]++;
                }

                $bootstrapData['lecturas'] = [
                    'lecturas' => $lecturas,
                    'summary' => $summary,
                ];
                break;

            case 'pagos':
                $pagos = new Pagos($db);
                $bootstrapData['pagos'] = [
                    'usuarios' => $pagos->usuarios(''),
                    'selected_usuario_id' => null,
                    'recibos' => $pagos->recibos(),
                ];
                break;

            case 'sistema':
                if (strtolower((string) ($currentUser['rol'] ?? '')) !== 'admin') {
                    break;
                }

                $usuariosSistema = new UsuariosSistema($db);
                $sistemaData = $usuariosSistema->listar('', 1, 0);
                $bootstrapData['sistema']['usuarios'] = $sistemaData['usuarios'] ?? [];
                $bootstrapData['sistema']['pagination'] = $sistemaData['pagination'] ?? null;

                $bitacora = new BitacoraSistema($db);
                $bitacoraData = $bitacora->listar([], 1, 20);
                $bootstrapData['sistema']['bitacora'] = $bitacoraData['logs'] ?? [];
                $bootstrapData['sistema']['bitacoraPagination'] = $bitacoraData['pagination'] ?? null;
                $bootstrapData['sistema']['catalogos'] = $bitacora->catalogos();
                $bootstrapData['sistema']['filters'] = $bitacoraData['filters'] ?? $bootstrapData['sistema']['filters'];
                break;
        }
    } catch (Throwable $exception) {
    }

    return $bootstrapData;
}

function mexquiticAdminNavItems(): array
{
    return [
        ['id' => 'menuAlta', 'href' => 'alta-usuario.php', 'icon' => 'fas fa-user-plus', 'label' => 'Alta de usuario'],
        ['id' => 'menuConsulta', 'href' => 'index.php', 'icon' => 'fas fa-address-book', 'label' => 'Consultar usuarios'],
        ['id' => 'menuMedidores', 'href' => 'medidores-admin.php', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Medidores'],
        ['id' => 'menuRutas', 'href' => 'rutas-admin.php', 'icon' => 'fas fa-route', 'label' => 'Rutas'],
        ['id' => 'menuPeriodos', 'href' => 'periodos-admin.php', 'icon' => 'fas fa-calendar-alt', 'label' => 'Periodos'],
        ['id' => 'menuLecturas', 'href' => 'lecturas-recibos.php', 'icon' => 'fas fa-clipboard-check', 'label' => 'Lecturas / Recibos'],
        ['id' => 'menuPagos', 'href' => 'pagos-admin.php', 'icon' => 'fas fa-cash-register', 'label' => 'Pagos'],
        ['id' => 'menuPagoCampo', 'href' => 'pago-campo.php', 'icon' => 'fas fa-qrcode', 'label' => 'Cobro', 'target' => '_blank', 'rel' => 'noopener'],
        ['id' => 'menuVerificadorExterno', 'href' => 'verificador.php', 'icon' => 'fas fa-clipboard-check', 'label' => 'Verificador', 'target' => '_blank', 'rel' => 'noopener'],
        ['id' => 'menuWhatsapp', 'href' => 'whatsapp-admin.php', 'icon' => 'fab fa-whatsapp', 'label' => 'WhatsApp'],
        ['id' => 'menuSistema', 'href' => 'usuarios-sistema.php', 'icon' => 'fas fa-user-shield', 'label' => 'Usuarios del sistema', 'admin_only' => true],
    ];
}

function mexquiticAdminFragment(string $relativePath): string
{
    $path = dirname(__DIR__) . '/views/admin/' . ltrim($relativePath, '/');
    $content = is_file($path) ? file_get_contents($path) : false;

    if ($content === false) {
        throw new RuntimeException('No se pudo cargar el fragmento: ' . $relativePath);
    }

    return $content;
}

function mexquiticRenderAdminPage(array $config): void
{
    mexquiticRequirePageAccess('plataforma', (string) ($config['page_access_log'] ?? 'Ingreso a la plataforma administrativa.'));

    global $__mexquiticDb;

    $auth = new Auth($__mexquiticDb);
    $currentUser = $auth->user() ?? [];
    $view = (string) ($config['view'] ?? 'consulta');
    $role = strtolower((string) ($currentUser['rol'] ?? ''));

    if (!empty($config['admin_only']) && $role !== 'admin') {
        header('Location: index.php');
        exit;
    }

    $title = (string) ($config['title'] ?? 'Consulta de usuarios');
    $eyebrow = (string) ($config['eyebrow'] ?? 'Usuarios / Consulta');
    $breadcrumb = (string) ($config['breadcrumb'] ?? 'Consulta');
    $content = mexquiticAdminFragment((string) $config['content_fragment']);
    $content = preg_replace('/\bapp-view d-none\b/', 'app-view', $content, 1) ?? $content;
    $modals = '';

    foreach (($config['modal_fragments'] ?? []) as $fragment) {
        $modals .= PHP_EOL . mexquiticAdminFragment((string) $fragment);
    }

    $bootstrapData = mexquiticAdminBootstrapData($__mexquiticDb, $currentUser, (string) ($config['bootstrap_view'] ?? $view));
    $bootstrapScript = '<script>window.__mexquiticBootstrapData = ' .
        json_encode($bootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
        ';</script>';

    $navItems = array_filter(mexquiticAdminNavItems(), static function (array $item) use ($role): bool {
        return empty($item['admin_only']) || $role === 'admin';
    });

    $mapsScript = '';
    if (!empty($config['needs_maps'])) {
        $mapsScript = <<<HTML
<script>
  window.__mexquiticMapsRequested = false;
  window.initMexquiticMaps = function () {
    window.__mexquiticMapsRequested = true;
    if (typeof window.__mexquiticMapsBootstrap === "function") {
      window.__mexquiticMapsBootstrap();
    }
  };
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDBALkm_Frld3h05vLp9ytk6pzRkTCkuK0&libraries=places&v=weekly&callback=initMexquiticMaps"></script>
HTML;
    }

    $navHtml = '';
    foreach ($navItems as $item) {
        $classes = ['nav-link'];
        if (($item['id'] ?? '') !== 'menuPagoCampo' && ($item['id'] ?? '') !== 'menuVerificadorExterno') {
            $classes[] = 'app-menu-link';
        }
        if (($item['id'] ?? '') === ('menu' . ucfirst($view)) || ($view === 'consulta' && ($item['id'] ?? '') === 'menuConsulta')) {
            $classes[] = 'active';
        }

        $target = !empty($item['target']) ? ' target="' . htmlspecialchars((string) $item['target'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $rel = !empty($item['rel']) ? ' rel="' . htmlspecialchars((string) $item['rel'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $dataNavigation = in_array('app-menu-link', $classes, true) ? ' data-navigation="page"' : '';

        $navHtml .= sprintf(
            '<li class="nav-item"><a href="%s" class="%s" id="%s"%s%s%s><i class="nav-icon %s"></i><p>%s</p></a></li>',
            htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $item['id'], ENT_QUOTES, 'UTF-8'),
            $target,
            $rel,
            $dataNavigation,
            htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8')
        );
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sistema de Agua | {$title}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
  <link rel="stylesheet" href="assets/css/styles.css?v=20260429c">
</head>
<body class="hold-transition sidebar-mini layout-fixed sidebar-overlay-open app-loading" data-admin-view="{$view}">
<div class="wrapper">
  <aside class="main-sidebar sidebar-dark-primary elevation-2">
    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <div class="user-avatar">
            <img src="assets/img/logo-recibo.png" alt="Logo del sistema" class="user-avatar-image">
          </div>
        </div>
        <div class="info">
          <a href="index.php" class="d-block">Sistema de Agua</a>
          <small>Mexquitic de Carmona</small>
        </div>
      </div>

      <div class="session-panel d-none" id="sessionPanelAdmin">
        <div class="session-panel-meta">
          <span class="session-panel-label">Sesion activa</span>
          <strong id="sessionAdminNombre">Cargando...</strong>
          <small id="sessionAdminUsuario">@usuario</small>
          <span class="badge badge-info mt-2" id="sessionAdminRol">Administrador</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-light" id="btnLogoutAdmin">
          <i class="fas fa-sign-out-alt mr-1"></i> Salir
        </button>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          {$navHtml}
        </ul>
      </nav>
    </div>

    <button class="sidebar-curtain-toggle" id="sidebarCurtainToggle" type="button" aria-label="Contraer menu lateral" title="Contraer menu lateral">
      <svg id="icono-abrir" class="sidebar-curtain-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
      </svg>
      <svg id="icono-cerrar" class="sidebar-curtain-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
      </svg>
    </button>
  </aside>

  <div class="sidebar-drawer-backdrop" id="sidebarDrawerBackdrop"></div>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-sm-7">
            <p class="eyebrow mb-1" id="pageEyebrow">{$eyebrow}</p>
            <h1 id="pageTitle">{$title}</h1>
          </div>
          <div class="col-sm-5">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
              <li class="breadcrumb-item active" id="pageBreadcrumb">{$breadcrumb}</li>
            </ol>
          </div>
        </div>
      </div>
    </section>

{$content}
  </div>

  <footer class="main-footer">
    <strong>Sistema de Agua Potable</strong>
    <div class="float-right d-none d-sm-inline-block">{$title}</div>
  </footer>
</div>
{$modals}
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="assets/js/session-client.js?v=20260429b"></script>
{$bootstrapScript}
<script src="assets/js/alta-usuarios.js?v=20260429m"></script>
{$mapsScript}
</body>
</html>
HTML;
}
