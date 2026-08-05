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

// "X horas Y minutos", completo, sin redondear a decimales de hora.
function bombaFormatoHorasMinutos(segundos) {
  segundos = Math.max(0, Math.round(segundos || 0));
  var horas = Math.floor(segundos / 3600);
  var minutos = Math.floor((segundos % 3600) / 60);

  if (horas <= 0 && minutos <= 0) {
    return "0 minutos";
  }

  var partes = [];
  if (horas > 0) {
    partes.push(horas + " " + bombaPluralComun(horas, "hora", "horas"));
  }
  if (minutos > 0 || horas <= 0) {
    partes.push(minutos + " " + bombaPluralComun(minutos, "minuto", "minutos"));
  }
  return partes.join(" ");
}

// Version corta ("2h 18m") para espacios chicos, como las celdas del calendario.
function bombaFormatoHorasMinutosCorto(segundos) {
  segundos = Math.max(0, Math.round(segundos || 0));
  var horas = Math.floor(segundos / 3600);
  var minutos = Math.floor((segundos % 3600) / 60);
  return horas + "h " + minutos + "m";
}

// Convierte "YYYY-MM-DD" a "DD/MM/AAAA", sin hora.
function bombaFormatoFechaSolo(fechaTexto) {
  if (!fechaTexto) { return ""; }
  var partes = fechaTexto.split("-");
  if (partes.length < 3) { return fechaTexto; }
  return partes[2] + "/" + partes[1] + "/" + partes[0];
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

// Igual que bombaFormatoFecha12h pero sin segundos, para listas donde el
// segundo exacto no aporta y solo genera ruido visual (ej. la Bitacora).
function bombaFormatoFechaCorta12h(fechaTexto) {
  if (!fechaTexto) { return ""; }
  var partes = fechaTexto.split(/[- :]/);
  if (partes.length < 5) { return fechaTexto; }

  var anio = partes[0];
  var mes = partes[1];
  var dia = partes[2];
  var horas24 = parseInt(partes[3], 10);
  var minutos = partes[4];
  var ampm = horas24 >= 12 ? "p.m." : "a.m.";
  var h12 = horas24 % 12;
  if (h12 === 0) { h12 = 12; }

  return dia + "/" + mes + "/" + anio + " " + h12 + ":" + String(minutos).padStart(2, "0") + " " + ampm;
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

function bombaUrlBase64ToUint8Array(base64String) {
  var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  var rawData = window.atob(base64);
  var outputArray = new Uint8Array(rawData.length);

  for (var i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }

  return outputArray;
}

function bombaPushSoportado() {
  return "serviceWorker" in navigator && "PushManager" in window;
}

function bombaPushActualizarBoton(activo) {
  $("#btnPushToggle").toggleClass("activo", activo);
  $("#btnPushToggleTexto").text(activo ? "Notificaciones activadas" : "Activar notificaciones");
}

function bombaPushInicializar() {
  if (!bombaPushSoportado() || !$("#btnPushToggle").length) {
    return;
  }

  $("#btnPushToggle").removeClass("oculto");

  navigator.serviceWorker.register("sw-push.js").then(function (registro) {
    registro.pushManager.getSubscription().then(function (suscripcion) {
      bombaPushActualizarBoton(!!suscripcion);
    });
  }).catch(function () {
    $("#btnPushToggle").addClass("oculto");
  });
}

function bombaPushActivar() {
  navigator.serviceWorker.ready.then(function (registro) {
    return registro.pushManager.getSubscription().then(function (existente) {
      if (existente) {
        return existente;
      }

      return $.ajax({
        url: bombaAjaxUrl,
        method: "POST",
        dataType: "json",
        data: { accion: "push.clavePublica" }
      }).then(function (response) {
        var clave = (response.data || {}).clave_publica;
        return registro.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: bombaUrlBase64ToUint8Array(clave)
        });
      });
    });
  }).then(function (suscripcion) {
    var json = suscripcion.toJSON();
    return $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "push.suscribir",
        endpoint: json.endpoint,
        p256dh: json.keys.p256dh,
        auth: json.keys.auth
      }
    });
  }).then(function () {
    bombaPushActualizarBoton(true);
  }).catch(function () {
    bombaPushActualizarBoton(false);
    alert("No se pudo activar las notificaciones. Revisa que el navegador tenga permiso para mostrarlas.");
  });
}

function bombaPushDesactivar() {
  navigator.serviceWorker.ready.then(function (registro) {
    return registro.pushManager.getSubscription();
  }).then(function (suscripcion) {
    if (!suscripcion) {
      bombaPushActualizarBoton(false);
      return;
    }

    var endpoint = suscripcion.endpoint;
    suscripcion.unsubscribe().then(function () {
      $.ajax({
        url: bombaAjaxUrl,
        method: "POST",
        dataType: "json",
        data: { accion: "push.desuscribir", endpoint: endpoint }
      });
      bombaPushActualizarBoton(false);
    });
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

  function bombaActualizarEstadoCortinilla() {
    var abierto = $("body").hasClass("bomba-sidebar-open");
    var etiqueta = abierto ? "Contraer menu lateral" : "Expandir menu lateral";

    $("#btnCurtainToggle").attr("aria-label", etiqueta).attr("title", etiqueta);
    $("#bombaIconoCerrar").toggleClass("oculto", !abierto);
    $("#bombaIconoAbrir").toggleClass("oculto", abierto);
  }

  $("#btnCurtainToggle").on("click", function () {
    $("body").toggleClass("bomba-sidebar-open");
    bombaActualizarEstadoCortinilla();
  });

  $("#drawerBackdrop").on("click", function () {
    $("body").removeClass("bomba-sidebar-open");
    bombaActualizarEstadoCortinilla();
  });

  $(".bomba-drawer-links a").on("click", function () {
    if (window.innerWidth < 992) {
      $("body").removeClass("bomba-sidebar-open");
      bombaActualizarEstadoCortinilla();
    }
  });

  if ($("#widgetCronometro").length) {
    bombaActualizarWidgetCronometro();
    setInterval(bombaActualizarWidgetCronometro, 15000);
  }

  $("#btnPushToggle").on("click", function () {
    if ($(this).hasClass("activo")) {
      bombaPushDesactivar();
    } else {
      bombaPushActivar();
    }
  });

  bombaPushInicializar();
});
