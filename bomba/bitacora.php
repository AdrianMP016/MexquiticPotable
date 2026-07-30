<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin', 'operador']);
$pageTitle = 'Bitacora — Control de Bomba';
$activeView = 'bitacora';
$jsFile = 'bitacora.js';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card">
  <h2><i class="fas fa-history"></i> Bitacora de actividad</h2>
  <div id="bitacoraLista">
    <p style="color:var(--agua-muted);">Cargando...</p>
  </div>
  <button type="button" class="btn-grande secundario oculto" id="btnCargarMas" style="width:100%; justify-content:center; margin-top:14px;">
    <i class="fas fa-chevron-down"></i> Cargar mas
  </button>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
