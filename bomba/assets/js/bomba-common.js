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

function bombaActualizarWidgetCronometro() {
  var $widget = $("#widgetCronometro");
  if (!$widget.length) {
    return;
  }

  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.estado" },
    success: function (response) {
      var data = response.data || {};
      var activa = data.activacion_actual;

      if (activa && activa.origen === "cronometro") {
        var inicio = new Date(activa.inicio_at.replace(" ", "T")).getTime();
        var duracionMs = (activa.cronometro_duracion_segundos || 0) * 1000;
        var restante = Math.max(0, Math.round((inicio + duracionMs - Date.now()) / 1000));
        var h = Math.floor(restante / 3600);
        var m = Math.floor((restante % 3600) / 60);
        var s = restante % 60;

        $("#widgetCronometroTexto").text(
          h + "h " + m + "m " + s + "s"
        );
        $widget.removeClass("oculto");
      } else {
        $widget.addClass("oculto");
      }
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
    setInterval(bombaActualizarWidgetCronometro, 5000);
  }
});
