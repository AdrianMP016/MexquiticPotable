window.bombaDashboardActivo = true;

let bombaEstadoActual = null;
let bombaPeriodoActividad = "dia";
let bombaCronometroFinTs = null;
let bombaCronometroOtroMotivo = null;
let bombaCronometroCierreSolicitado = false;

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

function bombaPintarEmergencia(data) {
  var emergencia = data.emergencia || { activa: false };
  var mantenimiento = data.mantenimiento || { activo: false };
  var bloqueado = emergencia.activa || mantenimiento.activo;

  $("#emergenciaBanner").toggleClass("oculto", !emergencia.activa);
  $("#mantenimientoBanner").toggleClass("oculto", !mantenimiento.activo);
  $("#btnEncenderApagar").prop("disabled", bloqueado);
  $("#btnIniciarCronometro").prop("disabled", bloqueado);
  $("#btnApagadoEmergencia").toggleClass("oculto", bloqueado);

  if (emergencia.activa) {
    $("#emergenciaBannerTexto").text(
      "Activado por " + (emergencia.activada_por || "alguien") + " el " + bombaFormatoFecha12h(emergencia.activada_en) +
      ". La bomba no va a encender (ni manual ni por programacion) hasta que reanudes la operacion normal."
    );
  }

  if (mantenimiento.activo) {
    $("#mantenimientoBannerTexto").text(
      "Activado por " + (mantenimiento.activado_por || "alguien") + " el " + bombaFormatoFecha12h(mantenimiento.activado_en) +
      ". No va a encender hasta que se reactive desde Mantenimiento."
    );
  }
}

function bombaPintarEstado(data) {
  bombaEstadoActual = data;
  bombaPintarWidgetCronometro(data);

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
  // Al final, para que gane sobre cualquier "disabled: false" de arriba: si
  // hay una emergencia activa, nada de lo anterior debe dejar el boton usable.
  bombaPintarEmergencia(data);
}

function bombaPintarCronometro(data) {
  var activa = data.activacion_actual;

  if (activa && activa.origen === "cronometro") {
    var inicio = new Date(activa.inicio_at.replace(" ", "T")).getTime();
    var duracionMs = (activa.cronometro_duracion_segundos || 0) * 1000;

    bombaCronometroFinTs = inicio + duracionMs;
    bombaCronometroOtroMotivo = null;
    bombaCronometroCierreSolicitado = false;

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
    if (!bombaCronometroCierreSolicitado) {
      // No esperamos al siguiente ciclo de 10s ni al cron: en cuanto la cuenta
      // regresiva local llega a cero pedimos el estado ya mismo, para que se
      // sienta instantaneo mientras alguien tiene la pantalla abierta.
      bombaCronometroCierreSolicitado = true;
      bombaRefrescarEstado();
    }
    return;
  }

  $("#cronometroTexto").text("Faltan " + bombaFormatoRelojCompleto(restanteSegundos));
}

var bombaEstadoEnCurso = false;

function bombaRefrescarEstado() {
  // Si una consulta anterior todavia no responde (por ejemplo, se colgo
  // hablando con Shelly), no lanzamos otra encima: solo se acumularian
  // peticiones y nunca se libera la pantalla.
  if (bombaEstadoEnCurso) {
    return;
  }

  bombaEstadoEnCurso = true;

  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.estado" },
    timeout: 25000,
    success: function (response) {
      bombaPintarEstado(response.data || {});
    },
    complete: function () {
      bombaEstadoEnCurso = false;
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
      $("#actividadHoras").text(bombaFormatoHorasMinutos(data.segundos || 0));
      $("#actividadVeces").text(data.veces || 0);
    }
  });
}

$(function () {
  bombaRefrescarEstado();
  bombaRefrescarActividad();
  setInterval(bombaRefrescarEstado, 10000);
  setInterval(bombaActualizarCronometroLocal, 1000);

  function bombaEjecutarEncenderApagar() {
    var accion = bombaEstadoActual && bombaEstadoActual.encendido ? "activaciones.apagar" : "activaciones.encender";
    var $feedback = $("#estadoFeedback");
    var $btn = $("#btnEncenderApagar");

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: accion },
      timeout: 25000,
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
        $btn.prop("disabled", false);
        bombaRefrescarEstado();
      }
    });
  }

  $("#btnEncenderApagar").on("click", function () {
    var encendiendo = !(bombaEstadoActual && bombaEstadoActual.encendido);

    $("#modalConfirmarEncendidoTitulo").text(encendiendo ? "Confirmar encendido" : "Confirmar apagado");
    $("#modalConfirmarEncendidoTexto").text(
      encendiendo
        ? "¿Seguro que quieres encender la bomba?"
        : "¿Seguro que quieres apagar la bomba?"
    );
    $("#btnConfirmarEncendido").removeClass("peligro primario").addClass(encendiendo ? "primario" : "peligro");
    $("#modalConfirmarEncendido").addClass("abierto");
  });

  $("#btnCancelarConfirmarEncendido").on("click", function () {
    $("#modalConfirmarEncendido").removeClass("abierto");
  });

  $("#btnApagadoEmergencia").on("click", function () {
    $("#modalConfirmarEmergencia").addClass("abierto");
  });

  $("#btnCancelarEmergencia").on("click", function () {
    $("#modalConfirmarEmergencia").removeClass("abierto");
  });

  $("#btnConfirmarEmergencia").on("click", function () {
    $("#modalConfirmarEmergencia").removeClass("abierto");

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.apagadoEmergencia" },
      timeout: 25000,
      success: function (response) {
        bombaPintarEstado(response.data || {});
        bombaRefrescarActividad();
      },
      error: function (xhr) {
        $("#estadoFeedback").removeClass("oculto exito").addClass("peligro").text(bombaExtraerMensaje(xhr, "No se pudo activar el apagado de emergencia."));
        bombaRefrescarEstado();
      }
    });
  });

  $("#btnReanudarOperacion").on("click", function () {
    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.reanudarOperacion" },
      success: function (response) {
        bombaPintarEstado(response.data || {});
        bombaRefrescarActividad();
      },
      error: function (xhr) {
        $("#estadoFeedback").removeClass("oculto exito").addClass("peligro").text(bombaExtraerMensaje(xhr, "No se pudo reanudar la operacion."));
      }
    });
  });

  $("#btnConfirmarEncendido").on("click", function () {
    $("#modalConfirmarEncendido").removeClass("abierto");
    bombaEjecutarEncenderApagar();
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
      timeout: 25000,
      beforeSend: function () {
        $feedback.addClass("oculto");
      },
      success: function (response) {
        bombaPintarEstado(response.data || {});
        bombaRefrescarActividad();
      },
      error: function (xhr) {
        $feedback.removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudo iniciar el cronometro."));
        bombaRefrescarEstado();
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
