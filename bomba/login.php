<?php

require_once __DIR__ . '/app/bootstrap.php';

SessionManager::start();
$auth = new Auth($__bombaDb);

if ($auth->user()) {
    header('Location: index.php');
    exit;
}

$cssVersion = @filemtime(__DIR__ . '/assets/css/bomba.css') ?: time();
$jsVersion = @filemtime(__DIR__ . '/assets/js/login.js') ?: time();
$next = isset($_GET['next']) ? basename((string) $_GET['next']) : 'index.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Control de Bomba — Acceso</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<link rel="stylesheet" href="assets/css/bomba.css?v=<?= (int) $cssVersion ?>">
</head>
<body class="bomba-login-body">
  <div class="bomba-login-box">
    <div class="bomba-login-icono"><i class="fas fa-faucet"></i></div>
    <h1>Control de Bomba</h1>
    <p class="subtitulo">Sistema de Agua Potable</p>

    <div id="loginFeedback" class="alerta peligro oculto"></div>

    <form id="formLogin">
      <div class="campo-grande" style="text-align:left;">
        <label for="loginUsuario">Usuario</label>
        <input type="text" id="loginUsuario" name="usuario" autocomplete="username" autofocus>
      </div>
      <div class="campo-grande" style="text-align:left;">
        <label for="loginPassword">Contraseña</label>
        <input type="password" id="loginPassword" name="password" autocomplete="current-password">
      </div>
      <input type="hidden" id="loginNext" value="<?= htmlspecialchars($next) ?>">
      <button type="submit" class="btn-grande primario" style="width:100%; justify-content:center;" id="btnEntrar">
        <i class="fas fa-sign-in-alt"></i> Entrar
      </button>
    </form>
  </div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="assets/js/login.js?v=<?= (int) $jsVersion ?>"></script>
</body>
</html>
