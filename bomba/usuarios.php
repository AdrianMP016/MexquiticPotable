<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin']);
$pageTitle = 'Usuarios — Control de Bomba';
$activeView = 'usuarios';
$jsFile = 'usuarios.js';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <h2 style="margin:0;"><i class="fas fa-users-cog"></i> Usuarios</h2>
    <button type="button" class="btn-grande primario" id="btnNuevoUsuario">
      <i class="fas fa-user-plus"></i> Nuevo usuario
    </button>
  </div>
  <div id="usuariosFeedback" class="alerta peligro oculto" style="margin-top:16px;"></div>
  <div class="lista-simple" id="listaUsuarios" style="margin-top:16px;">
    <p style="padding:16px; color:var(--agua-muted);">Cargando...</p>
  </div>
</div>

<div class="bomba-modal-fondo" id="modalUsuario">
  <div class="bomba-modal-caja">
    <h3 id="modalUsuarioTitulo"><i class="fas fa-user-plus"></i> Nuevo usuario</h3>
    <input type="hidden" id="usuarioId">

    <div class="campo-grande">
      <label for="usuarioNombre">Nombre</label>
      <input type="text" id="usuarioNombre">
    </div>
    <div class="campo-grande">
      <label for="usuarioUsuario">Usuario (para entrar)</label>
      <input type="text" id="usuarioUsuario">
    </div>
    <div class="campo-grande">
      <label for="usuarioPassword" id="usuarioPasswordLabel">Contraseña</label>
      <input type="password" id="usuarioPassword">
    </div>
    <div class="campo-grande">
      <label for="usuarioRol">Rol</label>
      <select id="usuarioRol">
        <option value="operador">Operador</option>
        <option value="admin">Administrador</option>
      </select>
    </div>
    <div class="campo-grande">
      <label><input type="checkbox" id="usuarioActivo" checked> Activo</label>
    </div>

    <div id="modalUsuarioFeedback" class="alerta peligro oculto"></div>

    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCancelarUsuario">Cancelar</button>
      <button type="button" class="btn-grande primario" id="btnGuardarUsuario">Guardar</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
