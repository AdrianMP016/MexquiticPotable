function bombaRenderRegla(regla) {
  if (!regla) {
    $("#reglaActualBox").html('<p style="color:var(--agua-muted);"><i class="fas fa-info-circle"></i> No hay ninguna regla automatica configurada.</p>');
    return;
  }

  $("#reglaActualBox").html(
    '<p style="font-size:20px; font-weight:700; color:var(--agua-primary-dark); margin-bottom:6px;">' +
      regla.hora_inicio + ' - ' + regla.hora_fin + ' (' + regla.dias_semana_texto + ')' +
    '</p>' +
    '<p style="color:var(--agua-muted); margin:0;">Configurada por ' + regla.creado_por_nombre + ' el ' + regla.created_at + '</p>'
  );
}

function bombaCargarRegla() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "regla.obtenerActiva" },
    success: function (response) {
      bombaRenderRegla((response.data || {}).regla);
    }
  });
}

function bombaEnviarRegla(forzar) {
  var dias = [];
  $("#diasSemanaSelector .dia-pastilla.activo").each(function () {
    dias.push($(this).data("dia"));
  });

  var $feedback = $("#reglaFeedback");
  var payload = {
    accion: "regla.guardar",
    hora_inicio: $("#reglaHoraInicio").val(),
    hora_fin: $("#reglaHoraFin").val(),
    "dias_semana[]": dias,
    forzar: forzar ? 1 : 0
  };

  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: payload,
    traditional: true,
    beforeSend: function () {
      $feedback.addClass("oculto");
    },
    success: function (response) {
      var data = response.data || {};

      if (data.requiere_confirmacion) {
        var actual = data.regla_actual;
        $("#modalConfirmarTexto").text(
          "Actualmente configurada por " + actual.creado_por_nombre + " el " + actual.created_at + ": " +
          actual.dias_semana_texto + " de " + actual.hora_inicio + " a " + actual.hora_fin + ". ¿Quieres reemplazarla?"
        );
        $("#modalConfirmarRegla").addClass("abierto");
        return;
      }

      $("#modalConfirmarRegla").removeClass("abierto");
      bombaRenderRegla(data.regla);
      $feedback.removeClass("oculto peligro").addClass("exito").text("Regla guardada correctamente.");
    },
    error: function (xhr) {
      $feedback.removeClass("oculto exito").addClass("peligro").text(bombaExtraerMensaje(xhr, "No se pudo guardar la regla."));
    }
  });
}

$(function () {
  bombaCargarRegla();

  $(document).on("click", ".dia-pastilla", function () {
    $(this).toggleClass("activo");
  });

  $("#btnGuardarRegla").on("click", function () {
    bombaEnviarRegla(false);
  });

  $("#btnConfirmarReemplazo").on("click", function () {
    bombaEnviarRegla(true);
  });

  $("#btnCancelarReemplazo").on("click", function () {
    $("#modalConfirmarRegla").removeClass("abierto");
  });
});
