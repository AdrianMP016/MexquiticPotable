let bombaEstadoActual = null;
let bombaPeriodoActividad = "dia";
let bombaPollTimer = null;

function bombaFormatoReloj(segundos) {
  segundos = Math.max(0, Math.floor(segundos));
  var h = Math.floor(segundos / 3600);
  var m = Math.floor((segundos % 3600) / 60);
  var s = segundos % 60;
  var partes = [];
  if (h > 0) { partes.push(h + "h"); }
  partes.push((m < 10 && h > 0 ? "0" : "") + m + "m");
  partes.push((s < 10 ? "0" : "") + s + "s");
  return partes.join(" ");
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
    $espera.removeClass("oculto").text("Espera " + espera + "s antes de otro comando.");
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
    var restanteSegundos = Math.max(0, Math.round((inicio + duracionMs - Date.now()) / 1000));

    $("#cronometroInactivo").addClass("oculto");
    $("#cronometroActivoBox").removeClass("oculto");
    $("#cronometroTexto").text("Faltan " + bombaFormatoReloj(restanteSegundos));
  } else if (activa) {
    $("#cronometroInactivo").addClass("oculto");
    $("#cronometroActivoBox").removeClass("oculto");
    $("#cronometroTexto").text("La bomba ya esta encendida (" + (activa.origen === "automatico" ? "automatica" : "manual") + ")");
    $("#btnCancelarCronometro").addClass("oculto");
  } else {
    $("#cronometroInactivo").removeClass("oculto");
    $("#cronometroActivoBox").addClass("oculto");
    $("#btnCancelarCronometro").removeClass("oculto");
  }
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
  bombaPollTimer = setInterval(bombaRefrescarEstado, 4000);

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
