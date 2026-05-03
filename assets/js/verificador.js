$(function () {
  var ajaxUrl = "ajax/peticiones.php";
  var bootstrapVerificador = window.__mexquiticVerificadorBootstrap || {};
  var usuarioActual = null;
  var gpsActual = null;
  var lecturaAnterior = 0;
  var _sincronizando = false;

  // ── Sesión ────────────────────────────────────────────────────────────────

  function inicializarSesion() {
    if (!window.MexquiticSession) {
      return $.Deferred().resolve().promise();
    }
    window.MexquiticSession.bindLogout("#btnLogoutVerificador", "verificador");
    return window.MexquiticSession.ensure("verificador", {
      panelSelector: "#sessionPanelVerificador",
      nameSelector:  "#sessionVerificadorNombre",
      roleSelector:  "#sessionVerificadorRol"
    });
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  function escapeHtml(value) {
    return $("<div>").text(value || "").html();
  }

  function formatDateLabel(value) {
    if (!value) { return ""; }
    var parts = String(value).split("-");
    if (parts.length !== 3) { return value; }
    return parts[2] + "/" + parts[1] + "/" + parts[0];
  }

  function showScreen(screenId) {
    $(".mobile-screen").removeClass("active");
    $("#" + screenId).addClass("active");
    $(".bottom-nav button").removeClass("active");
    $('.bottom-nav button[data-target="' + screenId + '"]').addClass("active");
  }

  function showFeedback(type, message) {
    $("#verificadorFeedback")
      .removeClass("d-none info warning")
      .addClass(type || "info")
      .text(message);
  }

  function hideFeedback() {
    $("#verificadorFeedback").addClass("d-none").text("");
  }

  // ── Conexión / Offline ────────────────────────────────────────────────────

  function actualizarEstadoConexion() {
    var online = navigator.onLine;
    var $bar   = $("#connectionBar");

    $bar.removeClass("d-none connection-bar--online connection-bar--offline connection-bar--syncing");
    $bar.addClass(online ? "connection-bar--online" : "connection-bar--offline");

    $("#connectionIcon").attr("class", online ? "fas fa-wifi mr-1" : "fas fa-exclamation-triangle mr-1");
    $("#connectionLabel").text(online ? "En línea" : "Sin conexión");

    if (typeof VerificadorQueue === "undefined") {
      $("#syncInfo").addClass("d-none");
      return;
    }

    VerificadorQueue.contar().then(function (total) {
      if (total > 0) {
        $("#syncCount").text(total);
        $("#syncInfo").removeClass("d-none");
        if (online) {
          $("#btnSincronizarAhora").removeClass("d-none");
        } else {
          $("#btnSincronizarAhora").addClass("d-none");
        }
      } else {
        $("#syncInfo").addClass("d-none");
      }
    }).catch(function () {
      $("#syncInfo").addClass("d-none");
    });
  }

  // ── Cola offline ──────────────────────────────────────────────────────────

  function guardarEnCola() {
    if (typeof VerificadorQueue === "undefined") {
      alert("Tu dispositivo no soporta almacenamiento local. Necesitas conexión para guardar.");
      return;
    }

    var $btn = $("#formLecturaDemo button[type='submit']");
    $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    var registro = {
      usuario_id:       usuarioActual.usuario_id,
      domicilio_id:     usuarioActual.domicilio_id,
      medidor_id:       usuarioActual.medidor_id,
      periodo_id:       usuarioActual.periodo_id,
      lectura_anterior: lecturaAnterior,
      medicion:         parseFloat($("#lecturaActual").val()),
      latitud:          gpsActual.latitud,
      longitud:         gpsActual.longitud,
      observaciones:    $("#observacionesLectura").val(),
      usuario_nombre:   usuarioActual.nombre,
      periodo_nombre:   usuarioActual.periodo_nombre
    };

    VerificadorQueue.agregar(registro).then(function () {
      $btn.prop("disabled", false).html('<i class="fas fa-save mr-1"></i> Guardar lectura');
      actualizarEstadoConexion();
      $("#modalLecturaTitulo").text("Guardado sin conexión");
      $("#modalLecturaMsg").text(
        "La lectura se guardó localmente. Se enviará al servidor cuando recuperes señal. (Sin foto)"
      );
      $("#modalLecturaGuardada").modal("show");
    }).catch(function () {
      $btn.prop("disabled", false).html('<i class="fas fa-save mr-1"></i> Guardar lectura');
      alert("No se pudo guardar localmente. Intenta de nuevo.");
    });
  }

  function sincronizarPendientes() {
    if (!navigator.onLine || _sincronizando || typeof VerificadorQueue === "undefined") {
      return;
    }

    VerificadorQueue.obtenerTodos().then(function (pendientes) {
      if (!pendientes.length) { return; }

      _sincronizando = true;
      var $bar = $("#connectionBar");
      $bar.removeClass("connection-bar--online connection-bar--offline").addClass("connection-bar--syncing");
      $("#connectionIcon").attr("class", "fas fa-sync-alt fa-spin mr-1");
      $("#connectionLabel").text("Sincronizando " + pendientes.length + " lectura(s)...");
      $("#btnSincronizarAhora").prop("disabled", true);

      function enviar(i) {
        if (i >= pendientes.length) {
          _sincronizando = false;
          $("#btnSincronizarAhora").prop("disabled", false);
          actualizarEstadoConexion();
          return;
        }

        var reg = pendientes[i];
        var formData = new FormData();
        formData.append("accion",            "verificador.guardarMedicion");
        formData.append("usuario_id",        reg.usuario_id);
        formData.append("domicilio_id",      reg.domicilio_id);
        formData.append("medidor_id",        reg.medidor_id);
        formData.append("periodo_id",        reg.periodo_id);
        formData.append("lectura_anterior",  reg.lectura_anterior);
        formData.append("medicion",          reg.medicion);
        formData.append("latitud",           reg.latitud);
        formData.append("longitud",          reg.longitud);
        formData.append("observaciones",     reg.observaciones || "");

        $.ajax({
          url:         ajaxUrl,
          method:      "POST",
          data:        formData,
          processData: false,
          contentType: false,
          success: function () {
            VerificadorQueue.eliminar(reg.id).then(function () {
              enviar(i + 1);
            });
          },
          error: function () {
            enviar(i + 1);
          }
        });
      }

      enviar(0);
    });
  }

  // ── Select de rutas ───────────────────────────────────────────────────────

  function inicializarSelectRuta() {
    if (!$.fn.select2) { return; }
    $("#selRutaVerificador").select2({
      width: "100%",
      placeholder: "Selecciona una ruta",
      allowClear: true,
      minimumInputLength: 0,
      language: {
        noResults:  function () { return "No hay rutas coincidentes"; },
        searching:  function () { return "Buscando..."; }
      }
    });
  }

  function llenarOpcionesRuta(rutas) {
    var options = ['<option value=""></option>'].concat(rutas.map(function (ruta) {
      var codigo = String(ruta.codigo || "").trim();
      var nombre = String(ruta.nombre || "").trim();
      var label  = nombre ? codigo + " - " + nombre : codigo;
      return '<option value="' + escapeHtml(codigo) + '">' + escapeHtml(label) + "</option>";
    })).join("");
    var $select = $("#selRutaVerificador");
    $select.html(options);
    if ($.fn.select2 && $select.hasClass("select2-hidden-accessible")) {
      $select.trigger("change.select2");
    }
  }

  function cargarRutasVerificador() {
    var rutasBootstrap = bootstrapVerificador.rutas || [];
    if (rutasBootstrap.length) {
      llenarOpcionesRuta(rutasBootstrap);
    }

    if (!navigator.onLine) { return; }

    $.ajax({
      url:      ajaxUrl,
      method:   "POST",
      dataType: "json",
      data:     { accion: "rutas.catalogo", comunidad_id: 0 },
      success:  function (response) {
        var rutas = response.data && response.data.rutas ? response.data.rutas : [];
        if (rutas.length) { llenarOpcionesRuta(rutas); }
      }
    });
  }

  // ── Resultados ────────────────────────────────────────────────────────────

  function renderResultados(usuarios) {
    if (!usuarios.length) {
      $("#listaResultados").html(
        '<div class="empty-state"><i class="fas fa-search"></i><p>No se encontraron usuarios activos para esa ruta.</p></div>'
      );
      return;
    }

    var cards = usuarios.map(function (usuario) {
      var rutaDetalle = [usuario.ruta || "Sin ruta", usuario.ruta_nombre || ""].filter(Boolean).join(" | ");
      return [
        '<div class="result-card" data-id="' + usuario.usuario_id + '">',
        '<div class="result-icon"><i class="fas fa-user-check"></i></div>',
        '<div>',
        '<h3>' + escapeHtml(usuario.nombre) + '</h3>',
        '<p>Ruta: ' + escapeHtml(rutaDetalle) + '</p>',
        '<p>Medidor: ' + escapeHtml(usuario.medidor || "Sin medidor") + '</p>',
        '<p>WhatsApp: ' + escapeHtml(usuario.whatsapp || "Sin WhatsApp") + '</p>',
        '</div>',
        '</div>'
      ].join("");
    }).join("");

    $("#listaResultados").html(cards);
  }

  function buscarUsuarios() {
    var termino = $.trim($("#selRutaVerificador").val());

    if (!termino) {
      showFeedback("warning", "Selecciona una ruta para consultar.");
      return;
    }

    if (!navigator.onLine) {
      showFeedback("warning", "Sin conexión. La búsqueda requiere internet. Conéctate y vuelve a intentar.");
      return;
    }

    $.ajax({
      url:      ajaxUrl,
      method:   "POST",
      cache:    false,
      dataType: "json",
      data:     { accion: "verificador.buscarUsuarios", termino: termino },
      beforeSend: function () {
        showFeedback("info", "Buscando usuarios por ruta...");
        $("#listaResultados").html(
          '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Consultando ruta...</p></div>'
        );
      },
      success: function (response) {
        hideFeedback();
        var usuarios = response.data && response.data.coincidencias ? response.data.coincidencias : [];
        renderResultados(usuarios);
      },
      error: function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : "No se pudo realizar la búsqueda.";
        showFeedback("warning", message);
      }
    });
  }

  function cargarUsuario(usuarioId) {
    if (!navigator.onLine) {
      showFeedback("warning", "Sin conexión. No se puede cargar el detalle del usuario.");
      return;
    }

    $.ajax({
      url:      ajaxUrl,
      method:   "POST",
      cache:    false,
      dataType: "json",
      data:     { accion: "verificador.obtenerUsuario", usuario_id: usuarioId },
      beforeSend: function () {
        showFeedback("info", "Cargando datos del domicilio...");
      },
      success: function (response) {
        hideFeedback();
        usuarioActual = response.data || {};
        llenarDetalle(usuarioActual);
        showScreen("screenDetalle");
      },
      error: function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : "No se pudo cargar el usuario.";
        showFeedback("warning", message);
      }
    });
  }

  // ── Detalle ───────────────────────────────────────────────────────────────

  function llenarDetalle(usuario) {
    var direccion = [usuario.calle, usuario.numero_domicilio, usuario.colonia].filter(Boolean).join(" ");
    lecturaAnterior = Number(usuario.lectura_anterior || 0);
    var periodoRango = [
      formatDateLabel(usuario.periodo_fecha_inicio),
      formatDateLabel(usuario.periodo_fecha_fin)
    ].filter(Boolean).join(" al ");
    var lecturaActualGuardada = usuario.lectura_actual_guardada !== null && usuario.lectura_actual_guardada !== undefined
      ? Number(usuario.lectura_actual_guardada)
      : null;

    $("#detalleNombre").text(usuario.nombre || "Sin usuario");
    $("#detalleEstado")
      .text(Number(usuario.activo) === 1 ? "Activo" : "Baja")
      .toggleClass("is-inactive", Number(usuario.activo) !== 1);
    $("#detalleMedidor").text(usuario.medidor || "Sin medidor");
    $("#detalleRuta").text(usuario.ruta || "Sin ruta");
    $("#detalleDireccion").text(direccion || "Sin dirección");
    $("#detalleLecturaAnterior").text(lecturaAnterior);
    $("#detallePeriodo").text(usuario.periodo_nombre || "Sin periodo");
    $("#detallePeriodoRango").text(periodoRango || "Sin rango");
    $("#lecturaTituloUsuario").text(usuario.nombre || "Usuario");
    $("#lecturaAnterior").text(lecturaAnterior);
    $("#lecturaPeriodoId").val(usuario.periodo_id || "");
    $("#lecturaPeriodoNombre").text(usuario.periodo_nombre || "Sin periodo");
    $("#lecturaPeriodoRango").text(periodoRango || "Sin rango disponible");
    $("#lecturaActual").val(lecturaActualGuardada !== null ? lecturaActualGuardada : "");
    $("#consumoCalculado").text("0.00 m3");
    $("#observacionesLectura").val("");
    $("#previewFotoMedidor").addClass("d-none").css("background-image", "");
    $("#btnFotoMedidor").html('<i class="fas fa-camera mr-1"></i> Tomar foto');
    gpsActual = null;
    $("#gpsLat").text("Pendiente");
    $("#gpsLng").text("Pendiente");
    $("#btnCapturarGps")
      .removeClass("btn-success")
      .addClass("btn-outline-primary")
      .html('<i class="fas fa-location-arrow mr-1"></i> Capturar GPS');

    if (usuario.fachada_path) {
      $("#detalleFachada").html('<img src="' + escapeHtml(usuario.fachada_path) + '" alt="Foto de fachada">');
    } else {
      $("#detalleFachada").html(
        '<div class="facade-placeholder"><i class="fas fa-home"></i><span>Sin foto de fachada</span></div>'
      );
    }

    calcularConsumo();
  }

  function calcularConsumo() {
    var actual  = Number($("#lecturaActual").val() || 0);
    var consumo = Math.max(actual - lecturaAnterior, 0);
    $("#consumoCalculado").text(consumo.toFixed(2) + " m3");
  }

  // ── GPS ───────────────────────────────────────────────────────────────────

  $("#btnCapturarGps").on("click", function () {
    if (!navigator.geolocation) {
      $("#gpsLat").text("No disponible");
      $("#gpsLng").text("No disponible");
      return;
    }

    $("#btnCapturarGps").html('<i class="fas fa-spinner fa-spin mr-1"></i> Capturando...');

    navigator.geolocation.getCurrentPosition(function (position) {
      gpsActual = {
        latitud:  position.coords.latitude,
        longitud: position.coords.longitude
      };
      $("#gpsLat").text(gpsActual.latitud.toFixed(8));
      $("#gpsLng").text(gpsActual.longitud.toFixed(8));
      $("#btnCapturarGps")
        .removeClass("btn-outline-primary")
        .addClass("btn-success")
        .html('<i class="fas fa-check mr-1"></i> GPS capturado');
    }, function () {
      $("#gpsLat").text("Permiso denegado");
      $("#gpsLng").text("Permiso denegado");
      $("#btnCapturarGps").html('<i class="fas fa-location-arrow mr-1"></i> Reintentar GPS');
    }, { enableHighAccuracy: true, timeout: 10000 });
  });

  // ── Foto ──────────────────────────────────────────────────────────────────

  $("#btnFotoMedidor").on("click", function () {
    $("#fotoMedidor").trigger("click");
  });

  $("#fotoMedidor").on("change", function () {
    var file = this.files && this.files[0];
    if (!file) { return; }
    var imageUrl = URL.createObjectURL(file);
    $("#previewFotoMedidor")
      .removeClass("d-none")
      .css("background-image", "url('" + imageUrl + "')");
    $("#btnFotoMedidor").html('<i class="fas fa-check mr-1"></i> Foto capturada');
  });

  // ── Formulario ────────────────────────────────────────────────────────────

  $("#formLecturaDemo").on("submit", function (event) {
    event.preventDefault();

    if (!usuarioActual) {
      showScreen("screenBuscar");
      showFeedback("warning", "Selecciona un usuario antes de guardar lectura.");
      return;
    }

    if (!$("#lecturaActual").val()) {
      alert("Captura la lectura actual.");
      return;
    }

    if (!gpsActual) {
      alert("Captura la ubicación GPS.");
      return;
    }

    // Sin conexión: guardar en cola local (sin foto)
    if (!navigator.onLine) {
      guardarEnCola();
      return;
    }

    // Con conexión: foto requerida
    if (!$("#fotoMedidor")[0].files.length) {
      alert("Toma la foto del medidor.");
      return;
    }

    var formData = new FormData();
    formData.append("accion",           "verificador.guardarMedicion");
    formData.append("usuario_id",       usuarioActual.usuario_id);
    formData.append("domicilio_id",     usuarioActual.domicilio_id);
    formData.append("medidor_id",       usuarioActual.medidor_id);
    formData.append("periodo_id",       usuarioActual.periodo_id);
    formData.append("lectura_anterior", lecturaAnterior);
    formData.append("medicion",         $("#lecturaActual").val());
    formData.append("latitud",          gpsActual.latitud);
    formData.append("longitud",         gpsActual.longitud);
    formData.append("observaciones",    $("#observacionesLectura").val());
    formData.append("foto_medidor",     $("#fotoMedidor")[0].files[0]);

    $.ajax({
      url:         ajaxUrl,
      method:      "POST",
      cache:       false,
      dataType:    "json",
      data:        formData,
      processData: false,
      contentType: false,
      beforeSend: function () {
        $("#formLecturaDemo button[type='submit']")
          .prop("disabled", true)
          .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
      },
      success: function (response) {
        if (usuarioActual) {
          usuarioActual.lectura_actual_guardada = Number($("#lecturaActual").val() || 0);
        }
        $("#modalLecturaTitulo").text("Lectura guardada");
        $("#modalLecturaMsg").text(
          "Lectura ID: " + response.data.lectura_id +
          " | Periodo: " + (response.data.periodo_nombre || usuarioActual.periodo_nombre || "-") +
          " | Consumo: " + response.data.consumo_m3 + " m3"
        );
        $("#modalLecturaGuardada").modal("show");
      },
      error: function (xhr) {
        var message = xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : "No se pudo guardar la medición.";
        alert(message);
      },
      complete: function () {
        $("#formLecturaDemo button[type='submit']")
          .prop("disabled", false)
          .html('<i class="fas fa-save mr-1"></i> Guardar lectura');
      }
    });
  });

  // ── Navegación ────────────────────────────────────────────────────────────

  $("#btnBuscarVerificador").on("click", buscarUsuarios);

  $("#selRutaVerificador").on("change", function () { hideFeedback(); });

  $(document).on("click", ".result-card", function () {
    cargarUsuario($(this).data("id"));
  });

  $("#btnIrLectura").on("click", function () { showScreen("screenLectura"); });

  $(".back-button, .bottom-nav button").on("click", function () {
    var target = $(this).data("target");
    if ((target === "screenDetalle" || target === "screenLectura") && !usuarioActual) {
      showScreen("screenBuscar");
      showFeedback("warning", "Primero selecciona un usuario.");
      return;
    }
    showScreen(target);
  });

  $("#lecturaActual").on("input", calcularConsumo);

  // ── Sincronizador ─────────────────────────────────────────────────────────

  $("#btnSincronizarAhora").on("click", function () {
    sincronizarPendientes();
  });

  window.addEventListener("online", function () {
    actualizarEstadoConexion();
    sincronizarPendientes();
  });

  window.addEventListener("offline", function () {
    actualizarEstadoConexion();
  });

  // ── Inicio ────────────────────────────────────────────────────────────────

  $.when(inicializarSesion()).always(function () {
    inicializarSelectRuta();
    cargarRutasVerificador();
    actualizarEstadoConexion();
    if (navigator.onLine) {
      sincronizarPendientes();
    }
  });
});
