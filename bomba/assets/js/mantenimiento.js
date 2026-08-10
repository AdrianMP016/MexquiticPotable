function bombaRenderMantenimiento(data) {
  var mantenimiento = data.mantenimiento || { activo: false };
  var $box = $("#mantenimientoBox");

  if (mantenimiento.activo) {
    $box.html(
      '<div class="alerta peligro" style="margin-bottom:14px;">' +
        '<strong>Apagada por mantenimiento</strong><br>' +
        'Activado por ' + mantenimiento.activado_por + ' el ' + bombaFormatoFecha12h(mantenimiento.activado_en) +
      '</div>' +
      '<button type="button" class="btn-grande primario" id="btnReactivarMantenimiento" style="width:100%; justify-content:center;">' +
        '<i class="fas fa-play"></i> Reactivar bomba' +
      '</button>'
    );
  } else {
    $box.html(
      '<p style="color:var(--agua-green);"><i class="fas fa-check-circle"></i> La bomba no esta en mantenimiento; opera normal.</p>' +
      '<button type="button" class="btn-grande peligro" id="btnIniciarMantenimiento" style="width:100%; justify-content:center;">' +
        '<i class="fas fa-tools"></i> Apagado temporal (mantenimiento)' +
      '</button>'
    );
  }
}

function bombaCargarMantenimiento() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.estado" },
    success: function (response) {
      bombaRenderMantenimiento(response.data || {});
    }
  });
}

$(function () {
  bombaCargarMantenimiento();

  $(document).on("click", "#btnIniciarMantenimiento", function () {
    $("#modalConfirmarMantenimiento").addClass("abierto");
  });

  $("#btnCancelarMantenimiento").on("click", function () {
    $("#modalConfirmarMantenimiento").removeClass("abierto");
  });

  $("#btnConfirmarMantenimiento").on("click", function () {
    $("#modalConfirmarMantenimiento").removeClass("abierto");

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.activarMantenimiento" },
      success: function (response) {
        bombaRenderMantenimiento(response.data || {});
      },
      error: function (xhr) {
        alert(bombaExtraerMensaje(xhr, "No se pudo activar el mantenimiento."));
      }
    });
  });

  $(document).on("click", "#btnReactivarMantenimiento", function () {
    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.desactivarMantenimiento" },
      success: function (response) {
        bombaRenderMantenimiento(response.data || {});
      },
      error: function (xhr) {
        alert(bombaExtraerMensaje(xhr, "No se pudo reactivar la bomba."));
      }
    });
  });
});
