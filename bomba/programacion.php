<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin', 'operador']);
$pageTitle = 'Programacion — Control de Bomba';
$activeView = 'programacion';
$jsFile = 'programacion.js';
$esAdmin = (string) ($currentUser['rol'] ?? '') === 'admin';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card">
  <h2><i class="fas fa-clock"></i> Regla automatica activa</h2>
  <div id="reglaActualBox">
    <p style="color:var(--agua-muted);">Consultando...</p>
  </div>
</div>

<?php if ($esAdmin): ?>
<div class="bomba-card">
  <h2><i class="fas fa-pen"></i> Editar regla automatica</h2>
  <p style="color:var(--agua-muted); margin-top:-8px;">La bomba se encendera sola en el horario y dias que elijas aqui.</p>

  <div class="campo-grande">
    <label>Dias de la semana</label>
    <div class="dias-semana" id="diasSemanaSelector">
      <span class="dia-pastilla" data-dia="1">Lun</span>
      <span class="dia-pastilla" data-dia="2">Mar</span>
      <span class="dia-pastilla" data-dia="3">Mie</span>
      <span class="dia-pastilla" data-dia="4">Jue</span>
      <span class="dia-pastilla" data-dia="5">Vie</span>
      <span class="dia-pastilla" data-dia="6">Sab</span>
      <span class="dia-pastilla" data-dia="7">Dom</span>
    </div>
  </div>

  <div style="display:flex; gap:16px; flex-wrap:wrap;">
    <div class="campo-grande" style="flex:1; min-width:160px;">
      <label for="reglaHoraInicio">Hora de inicio</label>
      <input type="time" id="reglaHoraInicio">
    </div>
    <div class="campo-grande" style="flex:1; min-width:160px;">
      <label for="reglaHoraFin">Hora de fin</label>
      <input type="time" id="reglaHoraFin">
    </div>
  </div>

  <div id="reglaFeedback" class="alerta peligro oculto"></div>

  <button type="button" class="btn-grande primario" id="btnGuardarRegla" style="width:100%; justify-content:center;">
    <i class="fas fa-save"></i> Guardar regla
  </button>
</div>
<?php else: ?>
<div class="bomba-card">
  <p><i class="fas fa-info-circle"></i> Solo un administrador puede cambiar la regla automatica.</p>
</div>
<?php endif; ?>

<div class="bomba-modal-fondo" id="modalConfirmarRegla">
  <div class="bomba-modal-caja">
    <h3><i class="fas fa-exclamation-triangle"></i> Ya existe una regla activa</h3>
    <p id="modalConfirmarTexto"></p>
    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCancelarReemplazo">Cancelar</button>
      <button type="button" class="btn-grande peligro" id="btnConfirmarReemplazo">Reemplazar</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
