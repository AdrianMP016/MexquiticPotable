<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin', 'operador']);
$pageTitle = 'Mantenimiento — Control de Bomba';
$activeView = 'mantenimiento';
$jsFile = 'mantenimiento.js';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card">
  <h2><i class="fas fa-tools"></i> Mantenimiento</h2>
  <p style="color:var(--agua-muted); margin-top:-8px;">
    Apaga la bomba por completo para limpieza, reparaciones o cualquier necesidad del pozo. Mientras este activo,
    <strong>nada la puede volver a encender</strong> — ni la regla automatica, ni una regla temporal, ni el cronometro,
    ni un encendido manual desde el Panel — hasta que la reactives aqui mismo.
  </p>

  <div id="mantenimientoBox">
    <p style="color:var(--agua-muted);">Consultando...</p>
  </div>
</div>

<div class="bomba-modal-fondo" id="modalConfirmarMantenimiento">
  <div class="bomba-modal-caja">
    <h3><i class="fas fa-exclamation-triangle"></i> Confirmar apagado por mantenimiento</h3>
    <p>La bomba se apagara de inmediato y <strong>se quedara apagada aunque pasen varios dias</strong>, sin importar la programacion. Se va a avisar a todos los usuarios con notificaciones activadas, con tu nombre. Solo vuelve a encender cuando alguien la reactive desde esta misma pantalla.</p>
    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCancelarMantenimiento">Cancelar</button>
      <button type="button" class="btn-grande peligro" id="btnConfirmarMantenimiento">Si, apagar por mantenimiento</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
