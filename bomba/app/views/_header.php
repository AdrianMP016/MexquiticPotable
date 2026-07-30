<?php
/**
 * Variables esperadas en scope: $pageTitle, $activeView, $currentUser
 */
$cssVersion = @filemtime(__DIR__ . '/../../assets/css/bomba.css') ?: time();
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
  <h1><i class="fas fa-faucet"></i> Control de Bomba</h1>
  <div class="usuario-info">
    <span><i class="fas fa-user-circle"></i> <?= htmlspecialchars((string) ($currentUser['nombre'] ?? '')) ?></span>
    <button type="button" class="btn-grande secundario" id="btnSalir" style="padding:10px 16px;font-size:15px;">
      <i class="fas fa-sign-out-alt"></i> Salir
    </button>
  </div>
</div>
<div class="bomba-nav">
  <a href="index.php" class="<?= ($activeView ?? '') === 'panel' ? 'activo' : '' ?>"><i class="fas fa-tachometer-alt"></i> Panel</a>
  <a href="programacion.php" class="<?= ($activeView ?? '') === 'programacion' ? 'activo' : '' ?>"><i class="fas fa-clock"></i> Programacion</a>
  <?php if ((string) ($currentUser['rol'] ?? '') === 'admin'): ?>
  <a href="usuarios.php" class="<?= ($activeView ?? '') === 'usuarios' ? 'activo' : '' ?>"><i class="fas fa-users-cog"></i> Usuarios</a>
  <?php endif; ?>
  <a href="bitacora.php" class="<?= ($activeView ?? '') === 'bitacora' ? 'activo' : '' ?>"><i class="fas fa-history"></i> Bitacora</a>
</div>
<div class="bomba-content">
