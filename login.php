<?php

require_once __DIR__ . '/app/bootstrap.php';

function mexquiticLoginAccess(): string
{
    $access = strtolower(trim((string) ($_GET['access'] ?? '')));
    return in_array($access, ['plataforma', 'cobro', 'verificador'], true) ? $access : 'plataforma';
}

$access = mexquiticLoginAccess();
SessionManager::setContext($access);
SessionManager::start();
$auth = new Auth($__mexquiticDb);
$forceLogin = isset($_GET['force']) && $_GET['force'] === '1';
$activeSession = null;
$activeSessionDestination = null;

if ($forceLogin) {
    $auth->logout($access);
}

$activeUser = $auth->user();
if ($activeUser) {
    if (!$auth->canAccessModule((string) ($activeUser['rol'] ?? ''), $access)) {
        header('Location: ' . $auth->defaultDestination($activeUser));
        exit;
    }
    $activeSession = $activeUser;
    $activeSessionDestination = $auth->defaultDestination($activeUser);
}

$defaultNextByAccess = [
    'plataforma' => 'index.php',
    'cobro' => 'pago-campo.php',
    'verificador' => 'verificador.php',
];

$next = basename((string) ($_GET['next'] ?? $defaultNextByAccess[$access]));
if (!in_array($next, ['index.php', 'pago-campo.php', 'verificador.php'], true)) {
    $next = 'index.php';
}

$titles = [
    'plataforma' => 'Administrador de la plataforma',
    'cobro' => 'Cobro',
    'verificador' => 'Verificador',
];
$pageTitle = $titles[$access] ?? $titles['plataforma'];
$loginRoutes = [
    'plataforma' => 'login-admin.php',
    'cobro' => 'login-cobro.php',
    'verificador' => 'login-verificador.php',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | Sistema de Agua Mexquitic</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root {
      --agua-primary: #1f7dd7;
      --agua-cyan: #42b8df;
      --agua-ink: #1f3556;
      --agua-bg: #eff6fc;
      --agua-border: #d7e7f5;
    }
    body {
      background: linear-gradient(160deg, rgba(31, 125, 215, 0.1), rgba(66, 184, 223, 0.06)), var(--agua-bg);
      color: var(--agua-ink);
      min-height: 100vh;
    }
    .login-shell {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .login-card {
      width: min(1040px, 100%);
      border: 1px solid var(--agua-border);
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 28px 54px rgba(24, 50, 84, 0.12);
      background: #fff;
    }
    .login-brand {
      background: linear-gradient(180deg, #163c72 0%, #0f2b52 100%);
      color: #fff;
      height: 100%;
      padding: 42px 34px;
    }
    .login-brand img {
      width: 136px;
      max-width: 100%;
      display: block;
      margin: 0 auto 22px;
    }
    .login-brand h1 {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    .login-brand p {
      color: rgba(255,255,255,.78);
      font-size: 16px;
      margin-bottom: 0;
    }
    .login-body {
      padding: 42px 34px;
    }
    .login-body h2 {
      font-size: 30px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    .login-body .text-muted {
      margin-bottom: 28px;
    }
    .nav-pills .nav-link.active {
      background: linear-gradient(90deg, var(--agua-primary), var(--agua-cyan));
    }
    .access-pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      background: rgba(31, 125, 215, 0.1);
      color: var(--agua-primary);
      font-size: 12px;
      font-weight: 700;
      padding: 6px 12px;
      margin-bottom: 16px;
      text-transform: uppercase;
    }
    .btn-primary {
      background: linear-gradient(90deg, var(--agua-primary), var(--agua-cyan));
      border: 0;
    }
    .login-mini-help {
      font-size: 12px;
      color: #6b7c93;
    }
    @media (max-width: 991.98px) {
      .login-brand {
        border-bottom: 1px solid rgba(255,255,255,.08);
      }
    }
  </style>
</head>
<body>
  <div class="login-shell">
    <div class="login-card">
      <div class="row no-gutters">
        <div class="col-lg-5">
          <div class="login-brand">
            <img src="assets/img/logo-recibo.png" alt="Sistema de Agua Potable de Mexquitic de Carmona">
            <h1>Sistema de Agua</h1>
            <p>Acceso controlado para administración, cobro y verificación en campo.</p>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="login-body">
            <div class="access-pill"><i class="fas fa-shield-alt mr-2"></i><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></div>
            <h2>Iniciar sesión</h2>
            <p class="text-muted">Cada ingreso queda registrado para saber quién entró, en qué entorno y qué movimientos realizó.</p>

            <div class="btn-group btn-group-sm mb-3 d-flex flex-wrap" role="group" aria-label="Instancias de acceso">
              <a class="btn <?php echo $access === 'plataforma' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="login-admin.php">Administrador</a>
              <a class="btn <?php echo $access === 'cobro' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="login-cobro.php">Cobro</a>
              <a class="btn <?php echo $access === 'verificador' ? 'btn-primary' : 'btn-outline-primary'; ?>" href="login-verificador.php">Verificador</a>
            </div>

            <?php if ($activeSession): ?>
              <div class="alert alert-info">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                  <div class="mb-3 mb-md-0">
                    <strong>Ya hay una sesion activa</strong><br>
                    <span><?php echo htmlspecialchars((string) ($activeSession['nombre'] ?? 'Usuario activo'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string) ($activeSession['usuario'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>)</span>
                  </div>
                  <div class="d-flex flex-wrap">
                    <a href="<?php echo htmlspecialchars((string) $activeSessionDestination, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm mr-2 mb-2 mb-md-0">Continuar con esta sesion</a>
                    <a href="<?php echo htmlspecialchars((string) ($loginRoutes[$access] ?? 'login-admin.php'), ENT_QUOTES, 'UTF-8'); ?>?force=1" class="btn btn-outline-primary btn-sm">Cambiar de cuenta</a>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="alert alert-danger d-none" id="loginFeedback"></div>
            <div class="alert alert-success d-none" id="loginSuccess"></div>

            <ul class="nav nav-pills mb-4" id="loginTabs" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" id="tab-acceso" data-toggle="pill" href="#panel-acceso" role="tab">Acceso</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-recuperar" data-toggle="pill" href="#panel-recuperar" role="tab">Recuperar contraseña</a>
              </li>
            </ul>

            <div class="tab-content">
              <div class="tab-pane fade show active" id="panel-acceso" role="tabpanel">
                <form id="formLogin">
                  <input type="hidden" name="accion" value="auth.login">
                  <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="form-group">
                    <label for="loginUsuario">Usuario</label>
                    <input type="text" class="form-control" id="loginUsuario" name="usuario" maxlength="60" autocomplete="username" required>
                  </div>
                  <div class="form-group">
                    <label for="loginPassword">Contraseña</label>
                    <input type="password" class="form-control" id="loginPassword" name="password" maxlength="80" autocomplete="current-password" required>
                  </div>
                  <input type="hidden" id="loginModulo" name="modulo" value="<?php echo htmlspecialchars($access, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="form-group">
                    <label>Entrar a</label>
                    <div class="form-control bg-light d-flex align-items-center">
                      <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt mr-1"></i> Entrar
                  </button>
                  <div class="login-mini-help mt-3">
                    Usuario inicial: <strong>admin</strong> | Contraseña inicial: <strong>admin</strong>
                  </div>
                </form>
              </div>

              <div class="tab-pane fade" id="panel-recuperar" role="tabpanel">
                <form id="formRecuperar">
                  <input type="hidden" name="accion" value="auth.recuperar">
                  <div class="form-group">
                    <label for="recuperarUsuario">Usuario</label>
                    <input type="text" class="form-control" id="recuperarUsuario" name="usuario" maxlength="60" required>
                  </div>
                  <div class="form-group">
                    <label for="recuperarTelefono">Telefono registrado</label>
                    <input type="tel" class="form-control" id="recuperarTelefono" name="telefono" maxlength="10" inputmode="numeric" required>
                  </div>
                  <div class="form-group">
                    <label for="recuperarPassword">Nueva contraseña</label>
                    <input type="password" class="form-control" id="recuperarPassword" name="nueva_password" maxlength="80" required>
                  </div>
                  <button type="submit" class="btn btn-outline-primary btn-block">
                    <i class="fas fa-key mr-1"></i> Actualizar contraseña
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      const $feedback = $("#loginFeedback");
      const $success = $("#loginSuccess");

      function showError(message) {
        $success.addClass("d-none").text("");
        $feedback.removeClass("d-none").text(message || "No se pudo completar la solicitud.");
      }

      function showSuccess(message) {
        $feedback.addClass("d-none").text("");
        $success.removeClass("d-none").text(message || "Proceso completado.");
      }

      function goToDestination(data) {
        const next = $("input[name='next']").val() || "index.php";
        const session = data && data.data ? data.data : {};
        const requestedModule = $("#loginModulo").val();
        const modules = session.modules || {};

        if (requestedModule === "cobro" && modules.cobro) {
          window.location.href = "pago-campo.php";
          return;
        }

        if (requestedModule === "verificador" && modules.verificador) {
          window.location.href = "verificador.php";
          return;
        }

        if (requestedModule === "plataforma" && modules.plataforma) {
          window.location.href = "index.php";
          return;
        }

        if (next === "pago-campo.php" && modules.cobro) {
          window.location.href = next;
          return;
        }

        if (next === "verificador.php" && modules.verificador) {
          window.location.href = next;
          return;
        }

        window.location.href = session.destino_actual || "index.php";
      }

      $("#formLogin").on("submit", function (event) {
        event.preventDefault();
        const $form = $(this);
        $.ajax({
          url: "ajax/peticiones.php",
          method: "POST",
          dataType: "json",
          data: $form.serialize(),
          beforeSend: function () {
            $form.find("button[type='submit']").prop("disabled", true);
            $feedback.addClass("d-none").text("");
          }
        }).done(function (response) {
          showSuccess(response.message || "Acceso correcto.");
          goToDestination(response);
        }).fail(function (xhr) {
          const response = xhr.responseJSON || {};
          showError(response.message || "No se pudo iniciar sesión.");
        }).always(function () {
          $form.find("button[type='submit']").prop("disabled", false);
        });
      });

      $("#formRecuperar").on("submit", function (event) {
        event.preventDefault();
        const $form = $(this);
        $.ajax({
          url: "ajax/peticiones.php",
          method: "POST",
          dataType: "json",
          data: $form.serialize(),
          beforeSend: function () {
            $form.find("button[type='submit']").prop("disabled", true);
            $feedback.addClass("d-none").text("");
          }
        }).done(function (response) {
          showSuccess(response.message || "Contraseña actualizada correctamente.");
          $form.trigger("reset");
          $("#tab-acceso").tab("show");
        }).fail(function (xhr) {
          const response = xhr.responseJSON || {};
          showError(response.message || "No se pudo actualizar la contraseña.");
        }).always(function () {
          $form.find("button[type='submit']").prop("disabled", false);
        });
      });
    })();
  </script>
</body>
</html>
