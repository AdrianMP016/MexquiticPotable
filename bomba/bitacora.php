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

  <div class="bitacora-filtro">
    <div class="bitacora-filtro-tipos" id="bitacoraFiltroTipos">
      <span class="dia-pastilla activo" data-tipo="todo">Todo</span>
      <span class="dia-pastilla" data-tipo="dia">Dia</span>
      <span class="dia-pastilla" data-tipo="mes">Mes</span>
      <span class="dia-pastilla" data-tipo="anio">Año</span>
    </div>

    <div class="bitacora-filtro-valor">
      <input type="date" id="bitacoraFiltroDia" class="oculto">
      <input type="month" id="bitacoraFiltroMes" class="oculto">
      <select id="bitacoraFiltroAnio" class="oculto"></select>
      <button type="button" class="btn-grande primario" id="btnBitacoraFiltrar" style="padding:10px 18px;">
        <i class="fas fa-filter"></i> Filtrar
      </button>
    </div>
  </div>

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
