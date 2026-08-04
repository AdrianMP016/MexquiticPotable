<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin', 'operador']);
$pageTitle = 'Actividad — Control de Bomba';
$activeView = 'actividad';
$jsFile = 'actividad.js';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card">
  <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
    <button type="button" class="btn-grande secundario" id="btnMesAnterior" style="padding:10px 16px;">
      <i class="fas fa-chevron-left"></i>
    </button>
    <h2 style="margin:0;" id="actividadMesTitulo"><i class="fas fa-calendar-alt"></i> --</h2>
    <button type="button" class="btn-grande secundario" id="btnMesSiguiente" style="padding:10px 16px;">
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>

  <div class="actividad-stats" style="margin-top:18px;">
    <div class="actividad-stat">
      <div class="valor" id="actividadMesHoras">--</div>
      <div class="etiqueta">Horas encendida en el mes</div>
    </div>
    <div class="actividad-stat">
      <div class="valor" id="actividadMesVeces">--</div>
      <div class="etiqueta">Veces activada en el mes</div>
    </div>
  </div>

  <div class="calendario-actividad" id="calendarioActividad" style="margin-top:20px;">
    <p style="color:var(--agua-muted);">Cargando...</p>
  </div>
  <p style="color:var(--agua-muted); font-size:14px; margin-top:10px;">
    En cada día: tiempo total encendida &middot; número de veces que se activó (por ejemplo, <strong>3x</strong> = se activó 3 veces). Toca un día para ver el detalle completo.
  </p>
</div>

<div class="bomba-modal-fondo" id="modalDetalleDia">
  <div class="bomba-modal-caja">
    <h3 id="modalDetalleDiaTitulo"><i class="fas fa-calendar-day"></i> Detalle del dia</h3>
    <div id="modalDetalleDiaLista"></div>
    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCerrarDetalleDia" style="width:100%;">Cerrar</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
