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
  <div class="bitacora-paginador">
    <button type="button" class="btn-grande secundario" id="btnPaginaAnterior" disabled>
      <i class="fas fa-chevron-left"></i> Anterior
    </button>
    <span id="bitacoraPaginaTexto">Pagina 1</span>
    <button type="button" class="btn-grande secundario" id="btnPaginaSiguiente" disabled>
      Siguiente <i class="fas fa-chevron-right"></i>
    </button>
  </div>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
