let bombaEstadoActual = null;
let bombaPeriodoActividad = "dia";
let bombaCronometroFinTs = null;
let bombaCronometroOtroMotivo = null;

function bombaPlural(cantidad, singular, plural) {
  return cantidad === 1 ? singular : plural;
}

function bombaFormatoRelojCompleto(segundos) {
  segundos = Math.max(0, Math.floor(segundos));
  var h = Math.floor(segundos / 3600);
  var m = Math.floor((segundos % 3600) / 60);
  var s = segundos % 60;

  return (
    h + " " + bombaPlural(h, "hora", "horas") + " " +
    m + " " + bombaPlural(m, "minuto", "minutos") + " " +
    s + " " + bombaPlural(s, "segundo", "segundos")
  );
}

function bombaPintarEstado(data) {
  bombaEstadoActual = data;

  var encendido = !!data.encendido;
  var $led = $("#bombaLed");
  var $texto = $("#bombaEstadoTexto");
  var $btn = $("#btnEncenderApagar");
  var $espera = $("#esperaTexto");

  $led.toggleClass("encendida", encendido).toggleClass("apagada", !encendido);
  $texto.toggleClass("encendida", encendido).toggleClass("apagada", !encendido);
  $texto.text(encendido ? "BOMBA ENCENDIDA" : "BOMBA APAGADA");
  $led.html('<i class="fas fa-power-off"></i>');

  if (data.temperatura && typeof data.temperatura.temperatura_c === "number") {
    $("#bombaTemperatura").html('<i class="fas fa-thermometer-half"></i> ' + data.temperatura.temperatura_c.toFixed(1) + ' &deg;C');
  } else {
    $("#bombaTemperatura").html('<i class="fas fa-thermometer-half"></i> sensor no disponible');
  }

  var espera = data.espera_restante_segundos || 0;
  if (espera > 0) {
    $btn.prop("disabled", true).html('<i class="fas fa-hourglass-half"></i> Espera...');
    $espera.removeClass("oculto").text(
      "Espera " + espera + " " + bombaPlural(espera, "segundo", "segundos") + " antes de otro comando."
    );
  } else {
    $btn.prop("disabled", false);
    $espera.addClass("oculto");
    if (encendido) {
      $btn.removeClass("encender").addClass("apagar").html('<i class="fas fa-power-off"></i> Apagar bomba');
    } else {
      $btn.removeClass("apagar").addClass("encender").html('<i class="fas fa-power-off"></i> Encender bomba');
    }
  }

  bombaPintarCronometro(data);
}

function bombaPintarCronometro(data) {
  var activa = data.activacion_actual;

  if (activa && activa.origen === "cronometro") {
    var inicio = new Date(activa.inicio_at.replace(" ", "T")).getTime();
    var duracionMs = (activa.cronometro_duracion_segundos || 0) * 1000;

    bombaCronometroFinTs = inicio + duracionMs;
    bombaCronometroOtroMotivo = null;

    $("#cronometroInactivo").addClass("oculto");
    $("#cronometroActivoBox").removeClass("oculto");
    $("#btnCancelarCronometro").removeClass("oculto");
    bombaActualizarCronometroLocal();
  } else if (activa) {
    bombaCronometroFinTs = null;
    bombaCronometroOtroMotivo = activa.origen === "automatico" ? "automatica" : "manual";

    $("#cronometroInactivo").addClass("oculto");
    $("#cronometroActivoBox").removeClass("oculto");
    $("#cronometroTexto").text("La bomba ya esta encendida (" + bombaCronometroOtroMotivo + ")");
    $("#btnCancelarCronometro").addClass("oculto");
  } else {
    bombaCronometroFinTs = null;
    bombaCronometroOtroMotivo = null;

    $("#cronometroInactivo").removeClass("oculto");
    $("#cronometroActivoBox").addClass("oculto");
    $("#btnCancelarCronometro").removeClass("oculto");
  }
}

function bombaActualizarCronometroLocal() {
  if (bombaCronometroFinTs === null) {
    return;
  }

  var restanteSegundos = Math.max(0, Math.round((bombaCronometroFinTs - Date.now()) / 1000));
  $("#cronometroTexto").text("Faltan " + bombaFormatoRelojCompleto(restanteSegundos));
}

function bombaRefrescarEstado() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.estado" },
    success: function (response) {
      bombaPintarEstado(response.data || {});
    }
  });
}

function bombaRefrescarActividad() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.estadisticas", periodo: bombaPeriodoActividad },
    success: function (response) {
      var data = response.data || {};
      $("#actividadHoras").text((data.horas || 0).toFixed(1));
      $("#actividadVeces").text(data.veces || 0);
    }
  });
}

$(function () {
  bombaRefrescarEstado();
  bombaRefrescarActividad();
  setInterval(bombaRefrescarEstado, 4000);
  setInterval(bombaActualizarCronometroLocal, 1000);

  $("#btnEncenderApagar").on("click", function () {
    var accion = bombaEstadoActual && bombaEstadoActual.encendido ? "activaciones.apagar" : "activaciones.encender";
    var $feedback = $("#estadoFeedback");
    var $btn = $(this);

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: accion },
      beforeSend: function () {
        $feedback.addClass("oculto");
        $btn.prop("disabled", true);
      },
      success: function (response) {
        bombaPintarEstado(response.data || {});
        bombaRefrescarActividad();
      },
      error: function (xhr) {
        $feedback.removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudo enviar el comando."));
        bombaRefrescarEstado();
      }
    });
  });

  $("#btnIniciarCronometro").on("click", function () {
    var horas = parseInt($("#cronHoras").val(), 10) || 0;
    var minutos = parseInt($("#cronMinutos").val(), 10) || 0;
    var $feedback = $("#cronometroFeedback");

    if (horas <= 0 && minutos <= 0) {
      $feedback.removeClass("oculto").text("Indica cuanto tiempo quieres que trabaje la bomba.");
      return;
    }

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.cronometro", horas: horas, minutos: minutos },
      beforeSend: function () {
        $feedback.addClass("oculto");
      },
      success: function (response) {
        bombaPintarEstado(response.data || {});
        bombaRefrescarActividad();
      },
      error: function (xhr) {
        $feedback.removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudo iniciar el cronometro."));
      }
    });
  });

  $("#btnCancelarCronometro").on("click", function () {
    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.cancelarCronometro" },
      success: function (response) {
        bombaPintarEstado(response.data || {});
        bombaRefrescarActividad();
      },
      error: function (xhr) {
        $("#cronometroFeedback").removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudo cancelar el cronometro."));
      }
    });
  });

  $(".actividad-tabs button").on("click", function () {
    $(".actividad-tabs button").removeClass("activo");
    $(this).addClass("activo");
    bombaPeriodoActividad = $(this).data("periodo");
    bombaRefrescarActividad();
  });
});
