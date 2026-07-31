const bombaAjaxUrl = "ajax/peticiones.php";

function bombaExtraerMensaje(xhr, mensajeDefault) {
  if (xhr && xhr.responseJSON) {
    var errores = xhr.responseJSON.errors;
    if (errores && Object.keys(errores).length) {
      return Object.values(errores).join(" ");
    }
    if (xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }
  }
  return mensajeDefault;
}

function bombaPluralComun(cantidad, singular, plural) {
  return cantidad === 1 ? singular : plural;
}

function bombaHora12(horas24, minutos) {
  var ampm = horas24 >= 12 ? "p.m." : "a.m.";
  var h12 = horas24 % 12;
  if (h12 === 0) { h12 = 12; }
  var mm = String(minutos).padStart(2, "0");
  return h12 + ":" + mm + " " + ampm;
}

// Convierte "HH:MM" o "HH:MM:SS" (24h) a "h:mm a.m./p.m."
function bombaFormatoHora12h(horaTexto) {
  if (!horaTexto) { return ""; }
  var partes = horaTexto.split(":");
  return bombaHora12(parseInt(partes[0], 10), parseInt(partes[1], 10));
}

// Convierte "YYYY-MM-DD HH:MM:SS" (hora de pared, ya en tiempo de Mexico) a
// "DD/MM/AAAA h:mm:ss a.m./p.m." sin reinterpretar zona horaria del navegador.
function bombaFormatoFecha12h(fechaTexto) {
  if (!fechaTexto) { return ""; }
  var partes = fechaTexto.split(/[- :]/);
  if (partes.length < 5) { return fechaTexto; }

  var anio = partes[0];
  var mes = partes[1];
  var dia = partes[2];
  var horas24 = parseInt(partes[3], 10);
  var minutos = parseInt(partes[4], 10);
  var segundos = partes[5] !== undefined ? String(partes[5]).padStart(2, "0") : "00";
  var ampm = horas24 >= 12 ? "p.m." : "a.m.";
  var h12 = horas24 % 12;
  if (h12 === 0) { h12 = 12; }

  return dia + "/" + mes + "/" + anio + " " + h12 + ":" + String(minutos).padStart(2, "0") + ":" + segundos + " " + ampm;
}

function bombaPintarWidgetCronometro(data) {
  var $widget = $("#widgetCronometro");
  if (!$widget.length) {
    return;
  }

  var activa = (data || {}).activacion_actual;

  if (activa && activa.origen === "cronometro") {
    var inicio = new Date(activa.inicio_at.replace(" ", "T")).getTime();
    var duracionMs = (activa.cronometro_duracion_segundos || 0) * 1000;
    var restante = Math.round((inicio + duracionMs - Date.now()) / 1000);

    if (restante <= 0) {
      $("#widgetCronometroTexto").html('<i class="fas fa-spinner fa-spin"></i> Terminando...');
    } else {
      var h = Math.floor(restante / 3600);
      var m = Math.floor((restante % 3600) / 60);
      var s = restante % 60;
      $("#widgetCronometroTexto").text(h + "h " + m + "m " + s + "s");
    }

    $widget.removeClass("oculto");
  } else {
    $widget.addClass("oculto");
  }
}

function bombaActualizarWidgetCronometro() {
  if (!$("#widgetCronometro").length) {
    return;
  }

  // Si el panel principal ya esta consultando el estado por su cuenta (mismo
  // dato), no lo volvemos a pedir aqui — evita duplicar peticiones a Shelly.
  if (window.bombaDashboardActivo) {
    return;
  }

  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.estado" },
    success: function (response) {
      bombaPintarWidgetCronometro(response.data || {});
    }
  });
}

$(function () {
  $("#btnSalir").on("click", function () {
    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "auth.logout" },
      complete: function () {
        window.location.href = "login.php";
      }
    });
  });

  $("#btnMenuToggle").on("click", function () {
    $("#drawer").addClass("abierto");
    $("#drawerBackdrop").addClass("abierto");
  });

  $("#btnCerrarDrawer, #drawerBackdrop").on("click", function () {
    $("#drawer").removeClass("abierto");
    $("#drawerBackdrop").removeClass("abierto");
  });

  if ($("#widgetCronometro").length) {
    bombaActualizarWidgetCronometro();
    setInterval(bombaActualizarWidgetCronometro, 15000);
  }
});
