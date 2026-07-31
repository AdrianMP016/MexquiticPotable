let bombaBitacoraPagina = 1;

function bombaRenderBitacora(logs) {
  if (!logs.length) {
    $("#bitacoraLista").html('<p style="color:var(--agua-muted);">Sin actividad registrada.</p>');
    return;
  }

  var html = logs.map(function (log) {
    return (
      '<div class="bitacora-item">' +
        '<div class="fecha">' + log.created_at + ' &middot; ' + (log.nombre_usuario || "Sistema") + '</div>' +
        '<div class="accion">' + (log.descripcion || log.accion) + '</div>' +
      '</div>'
    );
  }).join("");

  $("#bitacoraLista").html(html);
}

function bombaCargarBitacora() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "bitacora.listar", page: bombaBitacoraPagina, per_page: 25 },
    success: function (response) {
      var data = response.data || {};
      bombaRenderBitacora(data.logs || []);

      var pagination = data.pagination || { page: 1, total_pages: 1 };
      bombaBitacoraPagina = pagination.page;

      $("#bitacoraPaginaTexto").text("Pagina " + pagination.page + " de " + pagination.total_pages);
      $("#btnPaginaAnterior").prop("disabled", pagination.page <= 1);
      $("#btnPaginaSiguiente").prop("disabled", pagination.page >= pagination.total_pages);
    }
  });
}

$(function () {
  bombaCargarBitacora();

  $("#btnPaginaAnterior").on("click", function () {
    if (bombaBitacoraPagina > 1) {
      bombaBitacoraPagina--;
      bombaCargarBitacora();
    }
  });

  $("#btnPaginaSiguiente").on("click", function () {
    bombaBitacoraPagina++;
    bombaCargarBitacora();
  });
});
