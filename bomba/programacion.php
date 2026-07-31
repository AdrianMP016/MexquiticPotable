<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin', 'operador']);
$pageTitle = 'Programacion — Control de Bomba';
$activeView = 'programacion';
$jsFile = 'programacion.js';
$esAdmin = (string) ($currentUser['rol'] ?? '') === 'admin';

require __DIR__ . '/app/views/_header.php';

$opcionesHora = '';
for ($h = 1; $h <= 12; $h++) {
    $opcionesHora .= '<option value="' . $h . '">' . $h . '</option>';
}

$opcionesMinuto = '';
for ($m = 0; $m <= 59; $m++) {
    $opcionesMinuto .= '<option value="' . $m . '">' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '</option>';
}

function bombaSelectorHora12h(string $prefijo, string $etiqueta, string $opcionesHora, string $opcionesMinuto): void
{
    ?>
    <div class="campo-grande" style="flex:1; min-width:220px;">
      <label><?= htmlspecialchars($etiqueta) ?></label>
      <div class="hora-selector-12h">
        <select id="<?= htmlspecialchars($prefijo) ?>H"><?= $opcionesHora ?></select>
        <span>:</span>
        <select id="<?= htmlspecialchars($prefijo) ?>M"><?= $opcionesMinuto ?></select>
        <div class="ampm-selector" id="<?= htmlspecialchars($prefijo) ?>AmPm">
          <span class="ampm-pastilla" data-valor="AM">AM</span>
          <span class="ampm-pastilla" data-valor="PM">PM</span>
        </div>
      </div>
    </div>
    <?php
}
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
    <?php
    bombaSelectorHora12h('reglaHoraInicio', 'Hora de inicio', $opcionesHora, $opcionesMinuto);
    bombaSelectorHora12h('reglaHoraFin', 'Hora de fin', $opcionesHora, $opcionesMinuto);
    ?>
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

<?php if ($esAdmin): ?>
<div class="bomba-card">
  <h2><i class="fas fa-heartbeat"></i> Diagnostico del verificador automatico</h2>
  <p style="color:var(--agua-muted); margin-top:-8px;">Esta tarea corre en el servidor cada cierto tiempo (configurado en el cron de Hostinger) y es la que enciende/apaga la bomba por la regla automatica y cierra el cronometro cuando termina. Si la fecha de abajo esta muy atrasada, esa tarea no esta corriendo.</p>
  <div id="diagnosticoCronBox">
    <p style="color:var(--agua-muted);">Consultando...</p>
  </div>
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
