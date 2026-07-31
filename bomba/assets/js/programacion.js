function bombaRenderRegla(regla) {
  if (!regla) {
    $("#reglaActualBox").html('<p style="color:var(--agua-muted);"><i class="fas fa-info-circle"></i> No hay ninguna regla automatica configurada.</p>');
    return;
  }

  $("#reglaActualBox").html(
    '<p style="font-size:20px; font-weight:700; color:var(--agua-primary-dark); margin-bottom:6px;">' +
      bombaFormatoHora12h(regla.hora_inicio) + ' - ' + bombaFormatoHora12h(regla.hora_fin) + ' (' + regla.dias_semana_texto + ')' +
    '</p>' +
    '<p style="color:var(--agua-muted); margin:0;">Configurada por ' + regla.creado_por_nombre + ' el ' + bombaFormatoFecha12h(regla.created_at) + '</p>'
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

function bombaHoraSelectorA24h(prefijo) {
  var h12 = parseInt($("#" + prefijo + "H").val(), 10);
  var m = parseInt($("#" + prefijo + "M").val(), 10);
  var ampm = $("#" + prefijo + "AmPm .ampm-pastilla.activo").data("valor") || "AM";

  var h24 = h12 % 12;
  if (ampm === "PM") { h24 += 12; }

  return String(h24).padStart(2, "0") + ":" + String(m).padStart(2, "0");
}

function bombaEnviarRegla(forzar) {
  var dias = [];
  $("#diasSemanaSelector .dia-pastilla.activo").each(function () {
    dias.push($(this).data("dia"));
  });

  var $feedback = $("#reglaFeedback");
  var payload = {
    accion: "regla.guardar",
    hora_inicio: bombaHoraSelectorA24h("reglaHoraInicio"),
    hora_fin: bombaHoraSelectorA24h("reglaHoraFin"),
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
          "Actualmente configurada por " + actual.creado_por_nombre + " el " + bombaFormatoFecha12h(actual.created_at) + ": " +
          actual.dias_semana_texto + " de " + bombaFormatoHora12h(actual.hora_inicio) + " a " + bombaFormatoHora12h(actual.hora_fin) + ". ¿Quieres reemplazarla?"
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

  $(document).on("click", ".ampm-pastilla", function () {
    $(this).siblings().removeClass("activo");
    $(this).addClass("activo");
  });

  // Valor inicial: AM seleccionado por defecto en ambos selectores.
  $(".ampm-selector").each(function () {
    $(this).children().first().addClass("activo");
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
