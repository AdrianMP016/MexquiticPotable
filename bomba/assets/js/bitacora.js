let bombaBitacoraPagina = 1;

function bombaRenderBitacora(logs, agregar) {
  if (!logs.length && !agregar) {
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

  if (agregar) {
    $("#bitacoraLista").append(html);
  } else {
    $("#bitacoraLista").html(html);
  }
}

function bombaCargarBitacora(agregar) {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "bitacora.listar", page: bombaBitacoraPagina, per_page: 25 },
    success: function (response) {
      var data = response.data || {};
      bombaRenderBitacora(data.logs || [], agregar);

      var pagination = data.pagination || {};
      if (pagination.page < pagination.total_pages) {
        $("#btnCargarMas").removeClass("oculto");
      } else {
        $("#btnCargarMas").addClass("oculto");
      }
    }
  });
}

$(function () {
  bombaCargarBitacora(false);

  $("#btnCargarMas").on("click", function () {
    bombaBitacoraPagina++;
    bombaCargarBitacora(true);
  });
});
