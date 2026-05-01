$(function () {
  const ajaxUrl = "ajax/peticiones.php";
  const bootstrapVerificador = window.__mexquiticVerificadorBootstrap || {};
  let usuarioActual = null;
  let gpsActual = null;
  let lecturaAnterior = 0;

  function inicializarSesion() {
    if (!window.MexquiticSession) {
      return $.Deferred().resolve().promise();
    }

    window.MexquiticSession.bindLogout("#btnLogoutVerificador", "verificador");

    return window.MexquiticSession.ensure("verificador", {
      panelSelector: "#sessionPanelVerificador",
      nameSelector: "#sessionVerificadorNombre",
      roleSelector: "#sessionVerificadorRol"
    });
  }

  function escapeHtml(value) {
    return $("<div>").text(value || "").html();
  }

  function formatDateLabel(value) {
    if (!value) {
      return "";
    }

    const parts = String(value).split("-");
    if (parts.length !== 3) {
      return value;
    }

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

  function inicializarSelectRuta() {
    if (!$.fn.select2) {
      return;
    }

    $("#selRutaVerificador").select2({
      width: "100%",
      placeholder: "Selecciona una ruta",
      allowClear: true,
      minimumInputLength: 0,
      language: {
        noResults: function () {
          return "No hay rutas coincidentes";
        },
        searching: function () {
          return "Buscando...";
        }
      }
    });
  }

  function cargarRutasVerificador() {
    hidratarRutasVerificador();

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "rutas.catalogo",
        comunidad_id: 0
      },
      success: function (response) {
        const rutas = response.data && response.data.rutas ? response.data.rutas : [];
        const options = ['<option value=""></option>'].concat(rutas.map(function (ruta) {
          const codigo = String(ruta.codigo || "").trim();
          const nombre = String(ruta.nombre || "").trim();
          const label = nombre ? codigo + " - " + nombre : codigo;
          return '<option value="' + escapeHtml(codigo) + '">' + escapeHtml(label) + "</option>";
        })).join("");
        const $select = $("#selRutaVerificador");
        $select.html(options);
        if ($.fn.select2 && $select.hasClass("select2-hidden-accessible")) {
          $select.trigger("change.select2");
        }
      }
    });
  }

  function hidratarRutasVerificador() {
    const rutas = bootstrapVerificador.rutas || [];
    if (!rutas.length) {
      return;
    }

    const options = ['<option value=""></option>'].concat(rutas.map(function (ruta) {
      const codigo = String(ruta.codigo || "").trim();
      const nombre = String(ruta.nombre || "").trim();
      const label = nombre ? codigo + " - " + nombre : codigo;
      return '<option value="' + escapeHtml(codigo) + '">' + escapeHtml(label) + "</option>";
    })).join("");

    const $select = $("#selRutaVerificador");
    $select.html(options);
    if ($.fn.select2 && $select.hasClass("select2-hidden-accessible")) {
      $select.trigger("change.select2");
    }
  }

  function renderResultados(usuarios) {
    if (!usuarios.length) {
      $("#listaResultados").html(
        '<div class="empty-state"><i class="fas fa-search"></i><p>No se encontraron usuarios activos para esa ruta.</p></div>'
      );
      return;
    }

    const cards = usuarios.map(function (usuario) {
      const rutaDetalle = [usuario.ruta || "Sin ruta", usuario.ruta_nombre || ""].filter(Boolean).join(" | ");
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
    const termino = $.trim($("#selRutaVerificador").val());

    if (!termino) {
      showFeedback("warning", "Selecciona una ruta para consultar.");
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      cache: false,
      dataType: "json",
      data: {
        accion: "verificador.buscarUsuarios",
        termino: termino
      },
      beforeSend: function () {
        showFeedback("info", "Buscando usuarios por ruta...");
        $("#listaResultados").html(
          '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Consultando ruta...</p></div>'
        );
      },
      success: function (response) {
        hideFeedback();
        const usuarios = response.data && response.data.coincidencias ? response.data.coincidencias : [];
        renderResultados(usuarios);
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "No se pudo realizar la busqueda.";
        showFeedback("warning", message);
      }
    });
  }

  function cargarUsuario(usuarioId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      cache: false,
      dataType: "json",
      data: {
        accion: "verificador.obtenerUsuario",
        usuario_id: usuarioId
      },
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
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "No se pudo cargar el usuario.";
        showFeedback("warning", message);
      }
    });
  }

  function llenarDetalle(usuario) {
    const direccion = [
      usuario.calle,
      usuario.numero_domicilio,
      usuario.colonia
    ].filter(Boolean).join(" ");

    lecturaAnterior = Number(usuario.lectura_anterior || 0);
    const periodoRango = [formatDateLabel(usuario.periodo_fecha_inicio), formatDateLabel(usuario.periodo_fecha_fin)].filter(Boolean).join(" al ");
    const lecturaActualGuardada = usuario.lectura_actual_guardada !== null && usuario.lectura_actual_guardada !== undefined
      ? Number(usuario.lectura_actual_guardada)
      : null;

    $("#detalleNombre").text(usuario.nombre || "Sin usuario");
    $("#detalleEstado")
      .text(Number(usuario.activo) === 1 ? "Activo" : "Baja")
      .toggleClass("is-inactive", Number(usuario.activo) !== 1);
    $("#detalleMedidor").text(usuario.medidor || "Sin medidor");
    $("#detalleRuta").text(usuario.ruta || "Sin ruta");
    $("#detalleDireccion").text(direccion || "Sin direccion");
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
    const actual = Number($("#lecturaActual").val() || 0);
    const consumo = Math.max(actual - lecturaAnterior, 0);
    $("#consumoCalculado").text(consumo.toFixed(2) + " m3");
  }

  $("#btnBuscarVerificador").on("click", buscarUsuarios);

  $("#selRutaVerificador").on("change", function () {
    hideFeedback();
  });

  $.when(inicializarSesion()).always(function () {
    inicializarSelectRuta();
    cargarRutasVerificador();
  });

  $(document).on("click", ".result-card", function () {
    cargarUsuario($(this).data("id"));
  });

  $("#btnIrLectura").on("click", function () {
    showScreen("screenLectura");
  });

  $(".back-button, .bottom-nav button").on("click", function () {
    const target = $(this).data("target");

    if ((target === "screenDetalle" || target === "screenLectura") && !usuarioActual) {
      showScreen("screenBuscar");
      showFeedback("warning", "Primero selecciona un usuario.");
      return;
    }

    showScreen(target);
  });

  $("#lecturaActual").on("input", calcularConsumo);

  $("#btnFotoMedidor").on("click", function () {
    $("#fotoMedidor").trigger("click");
  });

  $("#fotoMedidor").on("change", function () {
    const file = this.files && this.files[0];
    if (!file) {
      return;
    }

    const imageUrl = URL.createObjectURL(file);
    $("#previewFotoMedidor")
      .removeClass("d-none")
      .css("background-image", "url('" + imageUrl + "')");
    $("#btnFotoMedidor").html('<i class="fas fa-check mr-1"></i> Foto capturada');
  });

  $("#btnCapturarGps").on("click", function () {
    if (!navigator.geolocation) {
      $("#gpsLat").text("No disponible");
      $("#gpsLng").text("No disponible");
      return;
    }

    $("#btnCapturarGps").html('<i class="fas fa-spinner fa-spin mr-1"></i> Capturando...');

    navigator.geolocation.getCurrentPosition(function (position) {
      gpsActual = {
        latitud: position.coords.latitude,
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
    }, {
      enableHighAccuracy: true,
      timeout: 10000
    });
  });

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

    if (!$("#fotoMedidor")[0].files.length) {
      alert("Toma la foto del medidor.");
      return;
    }

    if (!gpsActual) {
      alert("Captura la ubicacion GPS.");
      return;
    }

    const formData = new FormData();
    formData.append("accion", "verificador.guardarMedicion");
    formData.append("usuario_id", usuarioActual.usuario_id);
    formData.append("domicilio_id", usuarioActual.domicilio_id);
    formData.append("medidor_id", usuarioActual.medidor_id);
    formData.append("periodo_id", usuarioActual.periodo_id);
    formData.append("lectura_anterior", lecturaAnterior);
    formData.append("medicion", $("#lecturaActual").val());
    formData.append("latitud", gpsActual.latitud);
    formData.append("longitud", gpsActual.longitud);
    formData.append("observaciones", $("#observacionesLectura").val());
    formData.append("foto_medidor", $("#fotoMedidor")[0].files[0]);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      cache: false,
      dataType: "json",
      data: formData,
      processData: false,
      contentType: false,
      beforeSend: function () {
        $("#formLecturaDemo button[type='submit']")
          .prop("disabled", true)
          .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
      },
      success: function (response) {
        $("#modalLecturaGuardada .modal-body p").text("Lectura ID: " + response.data.lectura_id + " | Periodo: " + (response.data.periodo_nombre || usuarioActual.periodo_nombre || "-") + " | Consumo: " + response.data.consumo_m3 + " m3");
        if (usuarioActual) {
          usuarioActual.lectura_actual_guardada = Number($("#lecturaActual").val() || 0);
        }
        $("#modalLecturaGuardada").modal("show");
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "No se pudo guardar la medicion.";
        alert(message);
      },
      complete: function () {
        $("#formLecturaDemo button[type='submit']")
          .prop("disabled", false)
          .html('<i class="fas fa-save mr-1"></i> Guardar lectura');
      }
    });
  });
});
