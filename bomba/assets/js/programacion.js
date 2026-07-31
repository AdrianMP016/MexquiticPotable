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

function bombaRenderDiagnosticoCron(data) {
  var $box = $("#diagnosticoCronBox");
  if (!$box.length) {
    return;
  }

  if (!data.cron_ultima_ejecucion_at) {
    $box.html(
      '<p style="color:var(--agua-red);"><i class="fas fa-times-circle"></i> Todavia no se ha registrado ninguna corrida. Es probable que el cron no este configurado en Hostinger o nunca se haya ejecutado.</p>'
    );
    return;
  }

  var segundosDesde = Math.max(0, Math.round((new Date(data.hora_servidor_actual.replace(" ", "T")).getTime() - new Date(data.cron_ultima_ejecucion_at.replace(" ", "T")).getTime()) / 1000));
  var atrasado = segundosDesde > 600;

  var html = '<p><i class="fas ' + (atrasado ? 'fa-exclamation-triangle" style="color:var(--agua-red);"' : 'fa-check-circle" style="color:var(--agua-green);"') + '></i> ';
  html += 'Ultima corrida: ' + bombaFormatoFecha12h(data.cron_ultima_ejecucion_at) + ' (hace ' + segundosDesde + ' segundos)</p>';
  html += '<p style="color:var(--agua-muted);">Resultado: ' + (data.cron_ultimo_resultado || '--') + '</p>';
  if (atrasado) {
    html += '<p style="color:var(--agua-red);">Han pasado mas de 10 minutos desde la ultima corrida: revisa que el cron de "bomba/cron/verificar.php" siga activo en hPanel.</p>';
  }

  $box.html(html);
}

function bombaCargarDiagnosticoCron() {
  if (!$("#diagnosticoCronBox").length) {
    return;
  }

  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.diagnosticoCron" },
    success: function (response) {
      bombaRenderDiagnosticoCron(response.data || {});
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
  bombaCargarDiagnosticoCron();
  setInterval(bombaCargarDiagnosticoCron, 30000);

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
