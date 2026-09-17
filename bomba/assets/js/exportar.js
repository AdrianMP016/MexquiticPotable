function bombaExportarActividad(formato) {
  var $mensaje = $("#exportarMensaje");
  var desde = $("#exportarFechaDesde").val();
  var hasta = $("#exportarFechaHasta").val();

  if (!desde || !hasta) {
    $mensaje.text("Selecciona la fecha de inicio y de fin.").css("color", "var(--agua-red)");
    return;
  }

  $mensaje.text("Generando archivo...").css("color", "var(--agua-muted)");

  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: {
      accion: "activaciones.exportar",
      desde: desde,
      hasta: hasta,
      formato: formato
    },
    success: function (response) {
      var data = response.data || {};
      $mensaje.text("Listo: " + (data.total_registros || 0) + " registros exportados.").css("color", "var(--agua-green)");
      window.location.href = data.url;
    },
    error: function (xhr) {
      $mensaje.text(bombaExtraerMensaje(xhr, "No se pudo generar la exportacion.")).css("color", "var(--agua-red)");
    }
  });
}

$(function () {
  var hoy = new Date().toISOString().slice(0, 10);
  $("#exportarFechaDesde").val(hoy);
  $("#exportarFechaHasta").val(hoy);

  $("#btnExportarExcel").on("click", function () {
    bombaExportarActividad("excel");
  });

  $("#btnExportarTxt").on("click", function () {
    bombaExportarActividad("txt");
  });
});
