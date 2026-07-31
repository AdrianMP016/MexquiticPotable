<?php
/**
 * Variables esperadas en scope: $pageTitle, $activeView, $currentUser
 */
$cssVersion = @filemtime(__DIR__ . '/../../assets/css/bomba.css') ?: time();
$esAdminHeader = (string) ($currentUser['rol'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Control de Bomba') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<link rel="stylesheet" href="assets/css/bomba.css?v=<?= (int) $cssVersion ?>">
</head>
<body>
<div class="bomba-topbar">
  <button type="button" class="bomba-menu-toggle" id="btnMenuToggle" aria-label="Menu">
    <i class="fas fa-bars"></i>
  </button>
  <h1><i class="fas fa-faucet"></i> Control de Bomba</h1>
  <div class="bomba-topbar-right">
    <div class="bomba-cronometro-widget oculto" id="widgetCronometro">
      <i class="fas fa-stopwatch"></i> <span id="widgetCronometroTexto">--</span>
    </div>
  </div>
</div>

<div class="bomba-drawer-backdrop" id="drawerBackdrop"></div>
<nav class="bomba-drawer" id="drawer">
  <button type="button" id="btnCerrarDrawer" aria-label="Cerrar" class="bomba-drawer-cerrar"><i class="fas fa-times"></i></button>

  <div class="bomba-drawer-marca">
    <div class="bomba-drawer-logo">
      <img src="../assets/img/logo-recibo.png" alt="Logo del sistema">
    </div>
    <div class="bomba-drawer-marca-texto">
      <strong>Sistema de Agua</strong>
      <small>Control de Bomba &middot; Mexquitic de Carmona</small>
    </div>
  </div>

  <div class="bomba-drawer-divisor"></div>

  <div class="bomba-drawer-sesion">
    <div class="bomba-drawer-sesion-meta">
      <span class="bomba-drawer-sesion-label">Sesion activa</span>
      <strong><?= htmlspecialchars((string) ($currentUser['nombre'] ?? '')) ?></strong>
      <small>@<?= htmlspecialchars((string) ($currentUser['usuario'] ?? '')) ?></small>
      <span class="bomba-drawer-rol-badge"><?= $esAdminHeader ? 'Administrador' : 'Operador' ?></span>
    </div>
    <button type="button" class="bomba-drawer-salir" id="btnSalir">
      <i class="fas fa-sign-out-alt"></i> Salir
    </button>
  </div>

  <div class="bomba-drawer-links">
    <a href="index.php" class="<?= ($activeView ?? '') === 'panel' ? 'activo' : '' ?>"><i class="fas fa-tachometer-alt"></i> Panel</a>
    <a href="programacion.php" class="<?= ($activeView ?? '') === 'programacion' ? 'activo' : '' ?>"><i class="fas fa-clock"></i> Programacion</a>
    <a href="actividad.php" class="<?= ($activeView ?? '') === 'actividad' ? 'activo' : '' ?>"><i class="fas fa-chart-bar"></i> Actividad</a>
    <?php if ($esAdminHeader): ?>
    <a href="usuarios.php" class="<?= ($activeView ?? '') === 'usuarios' ? 'activo' : '' ?>"><i class="fas fa-users-cog"></i> Usuarios</a>
    <?php endif; ?>
    <a href="bitacora.php" class="<?= ($activeView ?? '') === 'bitacora' ? 'activo' : '' ?>"><i class="fas fa-history"></i> Bitacora</a>
  </div>
</nav>

<div class="bomba-content">
