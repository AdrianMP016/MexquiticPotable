<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequireUsuarioExclusivo(['admin']);
$pageTitle = 'Exportar actividad — Control de Bomba';
$activeView = 'exportar';
$jsFile = 'exportar.js';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card">
  <h2><i class="fas fa-file-export"></i> Exportar actividad de la bomba</h2>
  <p style="color:var(--agua-muted); margin-top:-8px;">
    Descarga el historial de encendidos y apagados (quien los hizo, cuando y por que se apagaron) en Excel o en texto.
    Esta pantalla es exclusiva de tu cuenta y no aparece para los demas usuarios.
  </p>

  <div style="display:flex; gap:16px; flex-wrap:wrap;">
    <div class="campo-grande" style="flex:1; min-width:160px;">
      <label>Desde</label>
      <input type="date" id="exportarFechaDesde">
    </div>
    <div class="campo-grande" style="flex:1; min-width:160px;">
      <label>Hasta</label>
      <input type="date" id="exportarFechaHasta">
    </div>
  </div>

  <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:10px;">
    <button type="button" class="btn-grande primario" id="btnExportarExcel">
      <i class="fas fa-file-excel"></i> Descargar Excel
    </button>
    <button type="button" class="btn-grande secundario" id="btnExportarTxt">
      <i class="fas fa-file-alt"></i> Descargar texto
    </button>
  </div>

  <p id="exportarMensaje" style="margin-top:12px;"></p>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
