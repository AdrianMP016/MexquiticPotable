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
    var partes = ['<i class="fas fa-thermometer-half"></i> ' + data.temperatura.temperatura_c.toFixed(1) + ' &deg;C'];
    if (typeof data.temperatura.humedad_pct === "number") {
      partes.push('<i class="fas fa-tint"></i> ' + data.temperatura.humedad_pct.toFixed(0) + '% humedad');
    }
    $("#bombaTemperatura").html(partes.join(' &nbsp;&middot;&nbsp; '));

    if (data.temperatura.actualizado_at) {
      $("#bombaSensorActualizado").text("Ultimo reporte del sensor: " + bombaFormatoFecha12h(data.temperatura.actualizado_at) + " (el sensor no reporta en vivo, avisa cada varios minutos)").removeClass("oculto");
    } else {
      $("#bombaSensorActualizado").addClass("oculto");
    }
  } else {
    $("#bombaTemperatura").html('<i class="fas fa-thermometer-half"></i> sensor no disponible');
    $("#bombaSensorActualizado").addClass("oculto");
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

  var restanteSegundos = Math.round((bombaCronometroFinTs - Date.now()) / 1000);

  if (restanteSegundos <= 0) {
    $("#cronometroTexto").html('<i class="fas fa-spinner fa-spin"></i> Terminando, apagando la bomba...');
    return;
  }

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

function bombaVerificarConexion(callback) {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.verificarConexion" },
    success: function (response) {
      callback(response.data || {}, null);
    },
    error: function (xhr) {
      callback(null, bombaExtraerMensaje(xhr, "No se pudo verificar la conexion."));
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
        $feedback.removeClass("oculto exito").addClass("peligro").text(bombaExtraerMensaje(xhr, "No se pudo enviar el comando."));
        bombaRefrescarEstado();
      }
    });
  });

  $("#btnVerificarConexion").on("click", function () {
    $("#modalConexionContenido").html('<i class="fas fa-spinner fa-spin"></i> Consultando...');
    $("#modalConexion").addClass("abierto");

    bombaVerificarConexion(function (data, error) {
      if (error || !data) {
        $("#modalConexionContenido").html('<p style="color:var(--agua-red);"><i class="fas fa-times-circle"></i> No se pudo consultar: ' + (error || "error desconocido") + '</p>');
        return;
      }

      var icono = data.conectado
        ? '<i class="fas fa-check-circle" style="color:var(--agua-green);"></i> Conectado a internet ahora mismo.'
        : '<i class="fas fa-times-circle" style="color:var(--agua-red);"></i> NO conectado a internet ahora mismo.';

      var html = '<p style="font-size:18px;">' + icono + '</p>';
      if (data.actualizado_at) {
        html += '<p style="color:var(--agua-muted);">Ultimo reporte del Shelly: ' + bombaFormatoFecha12h(data.actualizado_at) + '</p>';
      }
      if (!data.conectado) {
        html += '<p style="color:var(--agua-muted);">Si mandaste un comando y esto sale "NO conectado", es probable que no le haya llegado. Revisa el cable de red / la energia del Shelly en el sitio.</p>';
      }

      $("#modalConexionContenido").html(html);
    });
  });

  $("#btnCerrarModalConexion").on("click", function () {
    $("#modalConexion").removeClass("abierto");
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
