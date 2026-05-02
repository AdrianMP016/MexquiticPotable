$(function () {
  const ajaxUrl = "ajax/peticiones.php";
  let lecturaReciboActual = null;
  let reciboPagoActual = null;
  let usuariosPaginaActual = 1;
  let usuariosPorPaginaActual = 25;
  let usuariosTotalPaginas = 1;
  let usuariosRequestToken = 0;
  let medidoresPaginaActual = 1;
  let medidoresPorPaginaActual = 25;
  let medidoresTotalPaginas = 1;
  let medidoresRequestToken = 0;
  let catalogoMedidoresAlta = [];
  let normalizandoMedidorAlta = false;
  let rutasPaginaActual = 1;
  let rutasPorPaginaActual = 25;
  let rutasTotalPaginas = 1;
  let rutasRequestToken = 0;
  let periodosPaginaActual = 1;
  let periodosPorPaginaActual = 25;
  let periodosTotalPaginas = 1;
  let periodosRequestToken = 0;
  let lecturasPaginaActual = 1;
  let lecturasPorPaginaActual = 25;
  let lecturasTotalPaginas = 1;
  let lecturasRequestToken = 0;
  let pagosPaginaActual = 1;
  let pagosPorPaginaActual = 25;
  let pagosTotalPaginas = 1;
  let pagosRequestToken = 0;
  let colaNotificacionesMasivas = [];
  let indiceNotificacionMasiva = 0;
  let envioMasivoActivo = false;
  let temporizadorNotificacionMasiva = null;
  let colaPreviewRecibosPeriodo = [];
  let sistemaUsuariosPaginaActual = 1;
  let sistemaUsuariosPorPaginaActual = 25;
  let sistemaUsuariosTotalPaginas = 1;
  let bitacoraPaginaActual = 1;
  let bitacoraPorPaginaActual = 25;
  let bitacoraTotalPaginas = 1;
  let sessionDataActual = null;
  const bootstrapAdminData = window.__mexquiticBootstrapData || {};
  const defaultCobroAguaConfig = {
    nombre: "DOMESTICA",
    limite_tramo_base_m3: 15,
    precio_tramo_base_m3: 10,
    precio_excedente_m3: 15,
    cooperacion_default: 10,
    multa_default: 0,
    recargo_default: 0,
    descripcion: "Primeros 15 m3 a $10.00 y excedente a $15.00 por m3.",
    descripcion_corta: "DOMESTICA 15m3 x $10 | Exc. $15"
  };
  const bootstrapAdminConsumido = {
    consulta: false,
    medidores: false,
    rutas: false,
    periodos: false,
    lecturas: false,
    pagos: false,
    sistema: false
  };
  const mapsState = {
    apiReady: false,
    geocoder: null,
    controllers: {}
  };
  const mapsConfig = {
    alta: {
      mapId: "mapAlta",
      searchId: "searchDireccionMapa",
      latId: "latitud",
      lngId: "longitud",
      placeIdId: "googlePlaceId",
      modeId: "modoUbicacion",
      referenceId: "referenciaUbicacion",
      panelId: "locationModePanel",
      panelTextId: "locationModeText",
      calleId: "calle",
      numeroId: "numero",
      coloniaId: "colonia"
    },
    edit: {
      mapId: "mapEdit",
      searchId: "editSearchDireccionMapa",
      latId: "editLatitud",
      lngId: "editLongitud",
      placeIdId: "editGooglePlaceId",
      modeId: "editModoUbicacion",
      referenceId: "editReferenciaUbicacion",
      panelId: "editLocationModePanel",
      panelTextId: "editLocationModeText",
      calleId: "editCalle",
      numeroId: "editNumero",
      coloniaId: "editColonia"
    }
  };
  const vistaStorageKey = "mexquitic.view.actual";
  const vistaDefault = "consulta";
  const vistasValidas = ["alta", "consulta", "medidores", "rutas", "periodos", "lecturas", "pagos", "whatsapp", "sistema"];

  function normalizarVista(view) {
    const vista = String(view || "").toLowerCase().trim();
    return vistasValidas.indexOf(vista) >= 0 ? vista : vistaDefault;
  }

  function guardarVistaActual(view) {
    try {
      window.localStorage.setItem(vistaStorageKey, normalizarVista(view));
    } catch (error) {
      // Sin almacenamiento disponible, dejamos el flujo sin persistencia.
    }
  }

  function vistaDefinidaEnPagina() {
    const bodyView = $("body").attr("data-admin-view");
    return bodyView ? normalizarVista(bodyView) : "";
  }

  function obtenerVistaGuardada() {
    const vistaPagina = vistaDefinidaEnPagina();
    if (vistaPagina) {
      return vistaPagina;
    }

    try {
      const vista = normalizarVista(window.localStorage.getItem(vistaStorageKey));
      return vista === "alta" ? vistaDefault : vista;
    } catch (error) {
      return vistaDefault;
    }
  }

  function menuIdPorVista(view) {
    const mapa = {
      alta: "menuAlta",
      consulta: "menuConsulta",
      medidores: "menuMedidores",
      rutas: "menuRutas",
      periodos: "menuPeriodos",
      lecturas: "menuLecturas",
      pagos: "menuPagos",
      whatsapp: "menuWhatsapp",
      sistema: "menuSistema"
    };

    return mapa[normalizarVista(view)] || "menuAlta";
  }

  function actualizarEstadoCortinillaSidebar() {
    const abierto = $("body").hasClass("sidebar-overlay-open");
    const $toggle = $("#sidebarCurtainToggle");
    const label = abierto ? "Contraer menu lateral" : "Expandir menu lateral";

    $toggle.attr("aria-label", label).attr("title", label);
    $("#icono-abrir").toggleClass("hidden", abierto);
    $("#icono-cerrar").toggleClass("hidden", !abierto);
  }

  function defaultMapCenter() {
    return { lat: 22.0874, lng: -101.0172 };
  }

  function setMapPreviewCoords(lat, lng) {
    const coords = lat && lng
      ? Number(lat).toFixed(6) + ", " + Number(lng).toFixed(6)
      : "Pendientes";
    $("#mapPreviewCoords").text(coords);
  }

  function formatCoordinate(value) {
    return Number(value).toFixed(14);
  }

  function parseCoordinates(config) {
    const lat = Number($("#" + config.latId).val());
    const lng = Number($("#" + config.lngId).val());

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      return { lat: lat, lng: lng };
    }

    return null;
  }

  function setCoordinates(config, position) {
    $("#" + config.latId).val(formatCoordinate(position.lat));
    $("#" + config.lngId).val(formatCoordinate(position.lng));

    if (config.mapId === "mapAlta") {
      setMapPreviewCoords(position.lat, position.lng);
    }
  }

  function setPlaceId(config, placeId) {
    $("#" + config.placeIdId).val(placeId || "");
  }

  function getLocationMode(config) {
    return $("#" + config.modeId).val() || "manual";
  }

  function setLocationMode(config, mode) {
    const validMode = mode || "manual";
    const $select = $("#" + config.modeId);

    if ($select.val() !== validMode) {
      $select.val(validMode);
    }

    renderLocationModeState(config);
  }

  function renderLocationModeState(config) {
    const mode = getLocationMode(config);
    const $panel = $("#" + config.panelId);
    const $text = $("#" + config.panelTextId);
    const content = {
      google_maps: {
        className: "is-google",
        text: "La ubicacion se tomo desde una coincidencia de Google Maps. Si el punto no corresponde, puedes mover el pin y cambiar a modo manual."
      },
      manual: {
        className: "is-manual",
        text: "Mueve el pin hasta el domicilio correcto y agrega una referencia corta para ayudar al verificador a llegar sin depender de Google."
      },
      aproximada: {
        className: "is-aprox",
        text: "Guarda la zona general cuando el domicilio no aparezca exacto. En este modo la referencia del domicilio es obligatoria."
      }
    };
    const state = content[mode] || content.manual;

    $panel.removeClass("is-google is-manual is-aprox").addClass(state.className);
    $text.text(state.text);
  }

  function fillAddressFields(config, components) {
    const values = {
      calle: "",
      numero: "",
      colonia: ""
    };

    (components || []).forEach(function (component) {
      const types = component.types || [];

      if (types.indexOf("route") >= 0) {
        values.calle = component.long_name;
      }
      if (types.indexOf("street_number") >= 0) {
        values.numero = component.long_name;
      }
      if (types.indexOf("sublocality_level_1") >= 0 || types.indexOf("neighborhood") >= 0) {
        values.colonia = component.long_name;
      }
      if (!values.colonia && (types.indexOf("locality") >= 0 || types.indexOf("political") >= 0)) {
        values.colonia = component.long_name;
      }
    });

    if (values.calle) {
      $("#" + config.calleId).val(uppercaseValue(values.calle)).trigger("input");
    }
    if (values.numero) {
      $("#" + config.numeroId).val(uppercaseValue(values.numero)).trigger("input");
    }
    if (values.colonia) {
      $("#" + config.coloniaId).val(uppercaseValue(values.colonia)).trigger("input");
    }
  }

  function updateMapPosition(mode, position, reverseGeocode, interactionMode) {
    const config = mapsConfig[mode];
    const controller = mapsState.controllers[mode];

    if (!config || !controller) {
      return;
    }

    controller.marker.setVisible(true);
    controller.marker.setPosition(position);
    controller.map.setCenter(position);
    controller.map.setZoom(17);
    setCoordinates(config, position);

    if (interactionMode === "google_maps") {
      setLocationMode(config, "google_maps");
    } else if (interactionMode === "manual") {
      setPlaceId(config, "");
      if (getLocationMode(config) !== "aproximada") {
        setLocationMode(config, "manual");
      } else {
        renderLocationModeState(config);
      }
    }

    if (reverseGeocode && mapsState.geocoder) {
      mapsState.geocoder.geocode({ location: position }, function (results, status) {
        if (status !== "OK" || !results || !results.length) {
          return;
        }

        const result = results[0];
        $("#" + config.searchId).val(result.formatted_address || "");
        if (interactionMode === "google_maps") {
          setPlaceId(config, result.place_id || "");
        }
        fillAddressFields(config, result.address_components || []);
      });
    }
  }

  function syncMapFromFields(mode) {
    if (!mapsState.apiReady) {
      return;
    }

    const config = mapsConfig[mode];
    const controller = ensureMapController(mode);
    const position = parseCoordinates(config);

    if (!controller || !position) {
      if (controller) {
        controller.marker.setVisible(false);
        controller.map.setCenter(defaultMapCenter());
        controller.map.setZoom(12);
      }
      if (mode === "alta") {
        setMapPreviewCoords("", "");
      }
      return;
    }

    controller.marker.setVisible(true);
    controller.marker.setPosition(position);
    controller.map.setCenter(position);
    controller.map.setZoom(17);

    if (mode === "alta") {
      setMapPreviewCoords(position.lat, position.lng);
    }
  }

  function createAutocomplete(mode, controller) {
    const config = mapsConfig[mode];
    const input = document.getElementById(config.searchId);

    if (!input) {
      return null;
    }

    const autocomplete = new google.maps.places.Autocomplete(input, {
      componentRestrictions: { country: "mx" },
      fields: ["address_components", "formatted_address", "geometry", "name", "place_id"],
      types: ["geocode"]
    });

    autocomplete.addListener("place_changed", function () {
      const place = autocomplete.getPlace();

      if (!place.geometry || !place.geometry.location) {
        return;
      }

      const position = {
        lat: place.geometry.location.lat(),
        lng: place.geometry.location.lng()
      };

      $("#" + config.searchId).val(place.formatted_address || place.name || "");
      setPlaceId(config, place.place_id || "");
      fillAddressFields(config, place.address_components || []);
      updateMapPosition(mode, position, false, "google_maps");
    });

    return autocomplete;
  }

  function geocodeSearchInput(mode) {
    if (!mapsState.apiReady || !mapsState.geocoder) {
      return;
    }

    const config = mapsConfig[mode];
    const address = $.trim($("#" + config.searchId).val() || "");

    if (!address) {
      return;
    }

    ensureMapController(mode);

    mapsState.geocoder.geocode({ address: address, region: "mx" }, function (results, status) {
      if (status !== "OK" || !results || !results.length) {
        return;
      }

      const result = results[0];
      const location = result.geometry && result.geometry.location ? result.geometry.location : null;

      if (!location) {
        return;
      }

      $("#" + config.searchId).val(result.formatted_address || address);
      setPlaceId(config, result.place_id || "");
      fillAddressFields(config, result.address_components || []);
      updateMapPosition(mode, { lat: location.lat(), lng: location.lng() }, false, "google_maps");
    });
  }

  function ensureMapController(mode) {
    if (!mapsState.apiReady || mapsState.controllers[mode]) {
      return mapsState.controllers[mode] || null;
    }

    const config = mapsConfig[mode];
    const element = document.getElementById(config.mapId);

    if (!element || !window.google || !google.maps) {
      return null;
    }

    const position = parseCoordinates(config) || defaultMapCenter();
    const map = new google.maps.Map(element, {
      center: position,
      zoom: parseCoordinates(config) ? 17 : 12,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false
    });
    const marker = new google.maps.Marker({
      map: map,
      position: position,
      draggable: true,
      visible: !!parseCoordinates(config)
    });

    map.addListener("click", function (event) {
      updateMapPosition(mode, event.latLng.toJSON(), true, "manual");
    });

    marker.addListener("dragend", function (event) {
      updateMapPosition(mode, event.latLng.toJSON(), true, "manual");
    });

    mapsState.controllers[mode] = {
      map: map,
      marker: marker,
      autocomplete: createAutocomplete(mode, { map: map, marker: marker })
    };

    if (mode === "alta") {
      setMapPreviewCoords(position.lat, position.lng);
    }

    renderLocationModeState(config);

    return mapsState.controllers[mode];
  }

  window.__mexquiticMapsBootstrap = function () {
    if (!window.google || !google.maps) {
      return;
    }

    mapsState.apiReady = true;
    mapsState.geocoder = new google.maps.Geocoder();
    ensureMapController("alta");

    $("#modalEditarUsuario").on("shown.bs.modal", function () {
      const controller = ensureMapController("edit");

      if (controller) {
        google.maps.event.trigger(controller.map, "resize");
        syncMapFromFields("edit");
      }
    });
  };

  if (window.__mexquiticMapsRequested) {
    window.__mexquiticMapsBootstrap();
  }

  function valueOrFallback(value, fallback) {
    const cleanValue = $.trim(value || "");
    return cleanValue.length ? cleanValue : fallback;
  }

  function syncSummary($input) {
    const targetId = $input.data("summary");
    const fallback = targetId === "summaryNombre"
      ? "Sin nombre"
      : targetId === "summaryRuta"
        ? "Sin ruta"
        : targetId === "summaryMedidor"
          ? "Sin medidor"
          : "";

    $("#" + targetId).text(valueOrFallback($input.val(), fallback));
  }

  function uppercaseValue(value) {
    return (value || "").toLocaleUpperCase("es-MX");
  }

  function cleanNumbers(value) {
    return (value || "").replace(/\D/g, "").slice(0, 10);
  }

  function cleanCode(value, maxLength) {
    return uppercaseValue(value)
      .replace(/[^A-Z0-9-]/g, "")
      .slice(0, maxLength);
  }

  function cleanCoordinate(value) {
    let cleanValue = (value || "").replace(/[^0-9.-]/g, "");

    cleanValue = cleanValue.replace(/(?!^)-/g, "");

    const parts = cleanValue.split(".");
    if (parts.length > 2) {
      cleanValue = parts.shift() + "." + parts.join("");
    }

    return cleanValue.slice(0, 100);
  }

  function renderMedidorAltaInfo() {
    const codigo = cleanCode($("#medidor").val(), 60);
    const $info = $("#medidorAltaInfo");

    if (!codigo) {
      $info.addClass("d-none").removeClass("is-warning is-ok").empty();
      return;
    }

    const medidor = catalogoMedidoresAlta.find(function (item) {
      return String(item.medidor || "").toUpperCase() === codigo;
    });

    if (!medidor) {
      $info
        .removeClass("d-none is-warning")
        .addClass("is-ok")
        .html('<i class="fas fa-check-circle mr-1"></i>No existe un medidor previo con ese numero. Puedes registrarlo como nuevo.');
      return;
    }

    $info
      .removeClass("d-none is-ok")
      .addClass("is-warning")
      .html(
        '<i class="fas fa-exclamation-triangle mr-1"></i>' +
        'Este medidor ya existe y esta ligado a <strong>' + escapeHtml(medidor.usuario || "Sin usuario") +
        '</strong> | Ruta: <strong>' + escapeHtml(medidor.ruta || "Sin ruta") +
        '</strong> | Estado: <strong>' + escapeHtml(estadoMedidorTexto(medidor.estado || "activo")) +
        '</strong>. Si necesitas moverlo, conviene editar el registro actual.'
      );
  }

  function renderListaMedidoresAlta() {
    const $lista = $("#medidorAltaLista");

    $lista.addClass("d-none").empty();
  }

  function medidorAltaPorCodigo(codigo) {
    const normalized = cleanCode(codigo, 60);

    if (!normalized) {
      return null;
    }

    return catalogoMedidoresAlta.find(function (item) {
      return String(item.medidor || "").toUpperCase() === normalized;
    }) || null;
  }

  function ensureMedidorAltaOption(value) {
    const codigo = cleanCode(value, 60);
    const $select = $("#medidor");

    if (!$select.length || !codigo) {
      return "";
    }

    const exists = $select.find("option").filter(function () {
      return String($(this).attr("value") || "").toUpperCase() === codigo;
    }).length > 0;

    if (!exists) {
      const option = new Option(codigo, codigo, true, true);
      $select.append(option);
    }

    return codigo;
  }

  function medidorAltaTemplate(item) {
    const value = cleanCode(item.id || item.text || "", 60);

    if (!value) {
      return item.text || "";
    }

    const medidor = medidorAltaPorCodigo(value);

    if (!medidor) {
      return $(
        '<div class="select2-medidor-option">' +
          '<div><strong>' + escapeHtml(value) + '</strong></div>' +
          '<small class="text-muted">Nuevo medidor para registrar</small>' +
        '</div>'
      );
    }

    return $(
      '<div class="select2-medidor-option">' +
        '<div><strong>' + escapeHtml(medidor.medidor || value) + '</strong></div>' +
        '<small class="text-muted">' + escapeHtml(medidor.usuario || "Sin usuario") +
        ' | Ruta: ' + escapeHtml(medidor.ruta || "Sin ruta") +
        ' | Estado: ' + escapeHtml(estadoMedidorTexto(medidor.estado || "activo")) + '</small>' +
      '</div>'
    );
  }

  function inicializarMedidorAltaSelect2() {
    const $select = $("#medidor");

    if (!$select.length || !$.fn.select2) {
      return;
    }

    const currentValue = cleanCode($select.val(), 60);

    if ($select.hasClass("select2-hidden-accessible")) {
      $select.select2("destroy");
    }

    $select.select2({
      width: "100%",
      placeholder: $select.data("placeholder") || "Selecciona o escribe un medidor",
      allowClear: true,
      tags: true,
      dropdownParent: $(document.body),
      createTag: function (params) {
        const term = cleanCode(params.term, 60);

        if (!term) {
          return null;
        }

        return {
          id: term,
          text: term,
          newTag: !medidorAltaPorCodigo(term)
        };
      },
      templateResult: medidorAltaTemplate,
      templateSelection: function (item) {
        return cleanCode(item.id || item.text || "", 60) || item.text || "";
      },
      escapeMarkup: function (markup) {
        return markup;
      },
      language: {
        noResults: function () {
          return "No hay medidores coincidentes";
        },
        searching: function () {
          return "Buscando...";
        }
      }
    });

    if (currentValue) {
      ensureMedidorAltaOption(currentValue);
      $select.val(currentValue).trigger("change.select2");
    } else {
      $select.val("").trigger("change.select2");
    }
  }

  function normalizarMedidorAlta() {
    const $select = $("#medidor");
    const codigo = cleanCode($select.val(), 60);

    if (!$select.length) {
      return "";
    }

    if (normalizandoMedidorAlta) {
      return codigo;
    }

    normalizandoMedidorAlta = true;

    if (!codigo) {
      $select.val("").trigger("change.select2");
      syncSummary($select);
      renderMedidorAltaInfo();
      renderListaMedidoresAlta();
      normalizandoMedidorAlta = false;
      return "";
    }

    ensureMedidorAltaOption(codigo);
    $select.val(codigo).trigger("change.select2");
    syncSummary($select);
    renderMedidorAltaInfo();
    renderListaMedidoresAlta();
    normalizandoMedidorAlta = false;

    return codigo;
  }

  function validateClientFields() {
    const errors = {};
    const whatsapp = $("#whatsapp").val();
    const telefono = $("#telefono").val();
    const latitud = $("#latitud").val();
    const longitud = $("#longitud").val();
    const ruta = $("#ruta").val();
    const medidor = $("#medidor").val();
    const modoUbicacion = $("#modoUbicacion").val();
    const referenciaUbicacion = $.trim($("#referenciaUbicacion").val() || "");

    if (!$("#nombre").val()) {
      errors.nombre = "Captura el nombre completo.";
    }

    if (!whatsapp) {
      errors.whatsapp = "Captura el WhatsApp donde se enviaran los recibos.";
    } else if (whatsapp.length !== 10) {
      errors.whatsapp = "El WhatsApp debe tener 10 digitos.";
    }

    if (telefono && telefono.length !== 10) {
      errors.telefono = "El telefono alternativo debe tener 10 digitos.";
    }

    if (!ruta) {
      errors.ruta_id = "Selecciona la ruta del usuario.";
    }

    if (!medidor) {
      errors.medidor = "Captura el numero de medidor.";
    }

    if (latitud && isNaN(Number(latitud))) {
      errors.latitud = "La latitud debe ser numerica.";
    }

    if (longitud && isNaN(Number(longitud))) {
      errors.longitud = "La longitud debe ser numerica.";
    }

    if (modoUbicacion === "aproximada" && !referenciaUbicacion) {
      errors.referencia_ubicacion = "Agrega una referencia para ubicar el domicilio cuando la zona sea aproximada.";
    }

    return errors;
  }

  function showFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#statusFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#statusFeedbackText").text(message);
  }

  function showConsultaFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#consultaFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#consultaFeedbackText").text(message);
  }

  function showModalFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalFeedbackText").text(message);
  }

  function showMedidoresFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#medidoresFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#medidoresFeedbackText").text(message);
  }

  function showRutasFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#rutasFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#rutasFeedbackText").text(message);
  }

  function showModalMedidorFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalMedidorFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalMedidorFeedbackText").text(message);
  }

  function showModalRutaFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalRutaFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalRutaFeedbackText").text(message);
  }

  function restablecerBotonRuta() {
    const $button = $("#btnGuardarRuta");
    const accion = $("#rutaAccion").val();

    $button.prop("disabled", false);
    if (accion === "rutas.actualizar") {
      $button.html('<i class="fas fa-save mr-1"></i> Guardar cambios');
      return;
    }

    $button.html('<i class="fas fa-save mr-1"></i> Guardar ruta');
  }

  function comunidadIdPorNombre(nombre) {
    const normalized = $.trim(nombre || "").toLowerCase();
    const map = {
      "bella vista": "1",
      "centro 1": "2",
      "playa": "3",
      "ejidal": "4",
      "llano": "5",
      "pedregal": "6"
    };

    return map[normalized] || "";
  }

  function helperRutaSelector($select) {
    const selectId = $select.attr("id");

    if (selectId === "ruta") {
      return $("#rutaHelp");
    }

    if (selectId === "editRuta") {
      return $("#editRutaHelp");
    }

    return $();
  }

  function inicializarRutaSelect2($select) {
    if (!$select || !$select.length || !$.fn.select2) {
      return;
    }

    if ($select.hasClass("select2-hidden-accessible")) {
      $select.select2("destroy");
    }

    const $modal = $select.closest(".modal");
    const placeholder = $select.attr("id") === "editRuta"
      ? "Selecciona o busca una ruta"
      : "Selecciona una ruta";

    $select.select2({
      width: "100%",
      placeholder: placeholder,
      allowClear: true,
      dropdownParent: $modal.length ? $modal : $(document.body),
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

  function actualizarAyudaRuta($select, comunidadId, rutas, mostrandoCatalogoGeneral) {
    const $helper = helperRutaSelector($select);

    if (!$helper.length) {
      return;
    }

    if (mostrandoCatalogoGeneral) {
      $helper
        .removeClass("text-danger")
        .addClass("text-muted")
        .text("No hay rutas para esa comunidad; se muestran las registradas en el catalogo general.");
      return;
    }

    if (Array.isArray(rutas) && rutas.length) {
      $helper
        .removeClass("text-danger")
        .addClass("text-muted")
        .text("Selecciona la ruta del usuario.");
      return;
    }

    if (comunidadId) {
      $helper
        .removeClass("text-muted")
        .addClass("text-danger")
        .text("No hay rutas activas para esta comunidad. Usa Nueva para agregar una.");
      return;
    }

    $helper
      .removeClass("text-danger")
      .addClass("text-muted")
      .text("Primero selecciona una comunidad para cargar rutas.");
  }

  function cargarComboRutas($select, comunidadId, selectedRutaId, fallbackRutaCodigo, mostrarCatalogoGeneral) {
    $select.html('<option value="">Cargando rutas...</option>');
    inicializarRutaSelect2($select);
    actualizarAyudaRuta($select, comunidadId, [], false);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $.param({
        accion: "rutas.catalogo",
        comunidad_id: comunidadId || ""
      }),
      success: function (response) {
        const rutas = response.data && response.data.rutas ? response.data.rutas : [];

        if (!rutas.length) {
          if (comunidadId && !mostrarCatalogoGeneral) {
            cargarComboRutas($select, "", selectedRutaId, fallbackRutaCodigo, true);
            return;
          }

          $select.html('<option value="">' + (comunidadId ? "Sin rutas activas para esta comunidad" : "Sin rutas disponibles") + '</option>');
          actualizarAyudaRuta($select, comunidadId, rutas, false);
          inicializarRutaSelect2($select);
          $select.trigger("change");
          return;
        }

        let selectedFound = false;
        const options = ['<option value="">Selecciona ruta</option>'].concat(rutas.map(function (ruta) {
          const selected = String(ruta.ruta_id) === String(selectedRutaId) ? " selected" : "";
          if (selected) {
            selectedFound = true;
          }
          const label = [
            escapeHtml(ruta.codigo || "SIN CODIGO"),
            escapeHtml(ruta.nombre || "Sin nombre"),
            escapeHtml(ruta.comunidad || "Sin comunidad")
          ].join(" | ");
          return '<option value="' + ruta.ruta_id + '"' + selected + ' data-codigo="' + escapeHtml(ruta.codigo || "") + '">' +
            label +
            '</option>';
        }));

        if (!selectedFound && fallbackRutaCodigo) {
          options.push('<option value="" selected data-codigo="' + escapeHtml(fallbackRutaCodigo) + '">' +
            escapeHtml(fallbackRutaCodigo) + ' | Ruta actual</option>');
        }

        $select.html(options.join(""));
        actualizarAyudaRuta($select, comunidadId, rutas, Boolean(mostrarCatalogoGeneral));
        inicializarRutaSelect2($select);
        $select.trigger("change");
      },
      error: function (xhr) {
        $select.html('<option value="">No se pudieron cargar rutas</option>');
        inicializarRutaSelect2($select);
        const $helper = helperRutaSelector($select);
        if ($helper.length) {
          $helper
            .removeClass("text-muted")
            .addClass("text-danger")
            .text(extractAjaxMessage(xhr, "No se pudieron cargar las rutas. Intenta de nuevo."));
        }
      }
    });
  }

  function showPeriodosFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#periodosFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#periodosFeedbackText").text(message);
  }

  function showModalPeriodoFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalPeriodoFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalPeriodoFeedbackText").text(message);
  }

  function showLecturasFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#lecturasFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#lecturasFeedbackText").text(message);
  }

  function showModalReciboFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalReciboFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalReciboFeedbackText").text(message);
  }

  function showPreviewRecibosPeriodoFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#previewRecibosPeriodoFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#previewRecibosPeriodoFeedbackText").text(message);
  }

  function showNotificacionesMasivasFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#notificacionesMasivasFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#notificacionesMasivasFeedbackText").text(message);
  }

  function showPagosFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#pagosFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#pagosFeedbackText").text(message);
  }

  function showModalPagoFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalPagoFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalPagoFeedbackText").text(message);
  }

  function showWhatsappFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#whatsappFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#whatsappFeedbackText").text(message);
  }

  function showSistemaFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#sistemaFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#sistemaFeedbackText").text(message);
  }

  function showModalSistemaUsuarioFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalSistemaUsuarioFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalSistemaUsuarioFeedbackText").text(message);
  }

  function showModalResetSistemaFeedback(type, message) {
    const classes = {
      success: "alert-success",
      info: "alert-info",
      warning: "alert-warning",
      danger: "alert-danger"
    };

    $("#modalResetSistemaFeedback")
      .removeClass("d-none alert-success alert-info alert-warning alert-danger")
      .addClass(classes[type] || classes.info);
    $("#modalResetSistemaFeedbackText").text(message);
  }

  function inicializarSesionAdmin() {
    if (!window.MexquiticSession) {
      return $.Deferred().resolve().promise();
    }

    window.MexquiticSession.bindLogout("#btnLogoutAdmin", "plataforma");

    return window.MexquiticSession.ensure("plataforma", {
      panelSelector: "#sessionPanelAdmin",
      nameSelector: "#sessionAdminNombre",
      roleSelector: "#sessionAdminRol",
      userSelector: "#sessionAdminUsuario"
    }).done(function (response) {
      sessionDataActual = response && response.data ? response.data : null;

      const user = sessionDataActual && sessionDataActual.user ? sessionDataActual.user : {};
      if (String(user.rol || "").toLowerCase() !== "admin") {
        $("#menuSistema").addClass("d-none");
        if (obtenerVistaGuardada() === "sistema") {
          guardarVistaActual("alta");
        }
      }
    });
  }

  function finalizarCargaAplicacion() {
    $("body").removeClass("app-loading");
  }

  function abrirVistaSegura(view, menuId) {
    try {
      switchView(view, menuId);
    } catch (error) {
      console.error("No se pudo abrir la vista solicitada, usando Consulta como respaldo.", error);
      try {
        switchView("consulta", "menuConsulta");
      } catch (fallbackError) {
        console.error("Tampoco se pudo restaurar la vista Consulta.", fallbackError);
        $("#viewConsulta").removeClass("d-none");
        $("#menuConsulta").addClass("active");
        $("#pageEyebrow").text("Usuarios / Consulta");
        $("#pageTitle").text("Consulta de usuarios");
        $("#pageBreadcrumb").text("Consulta");
      }
    }
  }

  function inicializarAplicacionAdmin() {
    const vistaInicial = vistaDefinidaEnPagina() || obtenerVistaGuardada();
    abrirVistaSegura(vistaInicial, menuIdPorVista(vistaInicial));
    finalizarCargaAplicacion();

    // El resto de la inicializacion no debe bloquear la vista principal.
    if ($("#modoUbicacion").length) {
      renderLocationModeState(mapsConfig.alta);
    }
    if ($("#editModoUbicacion").length) {
      renderLocationModeState(mapsConfig.edit);
    }
    if ($("#countAdeudo").length) {
      actualizarResumenCobro({});
    }
    if ($("#tablaNotificacionesMasivas").length) {
      renderNotificacionesMasivas([]);
      actualizarBotonesEnvioMasivo();
    }
    if ($("#ruta").length) {
      inicializarRutaSelect2($("#ruta"));
      cargarComboRutas($("#ruta"), comunidadIdPorNombre($("#comunidad").val()));
    }
    if ($("#editRuta").length) {
      inicializarRutaSelect2($("#editRuta"));
    }
    if ($("#medidor").length) {
      inicializarMedidorAltaSelect2();
      cargarSugerenciasMedidoresAlta();
    }
    if ($("#sidebarCurtainToggle").length) {
      actualizarEstadoCortinillaSidebar();
    }
  }

  window.addEventListener("error", function () {
    finalizarCargaAplicacion();
  });

  window.addEventListener("unhandledrejection", function () {
    finalizarCargaAplicacion();
  });

  function extractAjaxMessage(xhr, fallback) {
    if (xhr.responseJSON && xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }

    return fallback;
  }

  function renderFieldErrors(errors) {
    $(".is-invalid").removeClass("is-invalid");
    $(".invalid-feedback.dynamic-error").remove();

    $.each(errors || {}, function (field, message) {
      const $field = $('[name="' + field + '"]');

      if (!$field.length) {
        return;
      }

      $field.addClass("is-invalid");
      $('<div class="invalid-feedback dynamic-error"></div>')
        .text(message)
        .insertAfter($field);
    });
  }

  function escapeHtml(value) {
    return $("<div>").text(value || "").html();
  }

  function estadoMedidorTexto(value) {
    const map = {
      activo: "Activo",
      inactivo: "Inactivo",
      reemplazado: "Reemplazado",
      sin_medidor: "Sin medidor"
    };

    return map[value] || value || "Activo";
  }

  function estadoMedidorBadge(value) {
    const estado = estadoMedidorTexto(value);
    const badge = estado === "Activo"
      ? "badge-success"
      : estado === "Reemplazado"
        ? "badge-info"
        : estado === "Sin medidor"
          ? "badge-warning"
          : "badge-secondary";

    return '<span class="badge ' + badge + '">' + escapeHtml(estado) + '</span>';
  }

  function estadoPeriodoBadge(value) {
    const estado = value || "abierto";
    const badge = estado === "abierto"
      ? "badge-success"
      : estado === "cerrado"
        ? "badge-info"
        : "badge-secondary";

    return '<span class="badge ' + badge + '">' + escapeHtml(estado.toUpperCase()) + '</span>';
  }

  function money(value) {
    return "$" + Number(value || 0).toLocaleString("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function numberFormat(value) {
    return Number(value || 0).toLocaleString("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function addDays(date, days) {
    const copy = new Date(date.getTime());
    copy.setDate(copy.getDate() + days);
    return copy;
  }

  function dateInputValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return year + "-" + month + "-" + day;
  }

  function switchView(view, sourceMenuId) {
    view = normalizarVista(view);
    guardarVistaActual(view);
    $(".app-view").addClass("d-none");
    $(".app-menu-link").removeClass("active");

    if (view === "consulta") {
      $("#viewConsulta").removeClass("d-none");
      $("#" + (sourceMenuId || "menuConsulta")).addClass("active");
      $("#pageEyebrow").text("Usuarios / Consulta");
      $("#pageTitle").text("Consulta de usuarios");
      $("#pageBreadcrumb").text("Consulta");
      hidratarVistaDesdeBootstrap("consulta");
      cargarUsuarios();
      return;
    }

    if (view === "medidores") {
      $("#viewMedidores").removeClass("d-none");
      $("#" + (sourceMenuId || "menuMedidores")).addClass("active");
      $("#pageEyebrow").text("Medidores / Consulta");
      $("#pageTitle").text("Medidores");
      $("#pageBreadcrumb").text("Medidores");
      hidratarVistaDesdeBootstrap("medidores");
      cargarMedidores();
      return;
    }

    if (view === "rutas") {
      $("#viewRutas").removeClass("d-none");
      $("#" + (sourceMenuId || "menuRutas")).addClass("active");
      $("#pageEyebrow").text("Rutas / Catalogo");
      $("#pageTitle").text("Rutas");
      $("#pageBreadcrumb").text("Rutas");
      hidratarVistaDesdeBootstrap("rutas");
      cargarRutas();
      return;
    }

    if (view === "periodos") {
      $("#viewPeriodos").removeClass("d-none");
      $("#" + (sourceMenuId || "menuPeriodos")).addClass("active");
      $("#pageEyebrow").text("Periodos / Cobro");
      $("#pageTitle").text("Periodos bimestrales");
      $("#pageBreadcrumb").text("Periodos");
      hidratarVistaDesdeBootstrap("periodos");
      cargarPeriodos();
      return;
    }

    if (view === "lecturas") {
      $("#viewLecturas").removeClass("d-none");
      $("#" + (sourceMenuId || "menuLecturas")).addClass("active");
      $("#pageEyebrow").text("Lecturas / Recibos");
      $("#pageTitle").text("Lecturas y recibos");
      $("#pageBreadcrumb").text("Recibos");
      hidratarVistaDesdeBootstrap("lecturas");
      cargarFiltroPeriodosLectura();
      cargarLecturas();
      return;
    }

    if (view === "pagos") {
      $("#viewPagos").removeClass("d-none");
      $("#" + (sourceMenuId || "menuPagos")).addClass("active");
      $("#pageEyebrow").text("Pagos / Caja");
      $("#pageTitle").text("Registro manual de pagos");
      $("#pageBreadcrumb").text("Pagos");
      hidratarVistaDesdeBootstrap("pagos");
      cargarUsuariosPago($("#buscarPagoUsuario").val(), $("#selectUsuarioPago").val());
      if ($("#selectUsuarioPago").val()) {
        cargarRecibosPago();
      }
      return;
    }

    if (view === "whatsapp") {
      $("#viewWhatsapp").removeClass("d-none");
      $("#" + (sourceMenuId || "menuWhatsapp")).addClass("active");
      $("#pageEyebrow").text("WhatsApp / UltraMsg");
      $("#pageTitle").text("Enlace y monitoreo de WhatsApp");
      $("#pageBreadcrumb").text("WhatsApp");
      cargarPanelWhatsapp();
      return;
    }

    if (view === "sistema") {
      $("#viewSistema").removeClass("d-none");
      $("#" + (sourceMenuId || "menuSistema")).addClass("active");
      $("#pageEyebrow").text("Sistema / Seguridad");
      $("#pageTitle").text("Usuarios del sistema");
      $("#pageBreadcrumb").text("Seguridad");
      hidratarVistaDesdeBootstrap("sistema");
      cargarUsuariosSistema();
      cargarBitacora();
      return;
    }

    $("#viewAlta").removeClass("d-none");
    $("#" + (sourceMenuId || "menuAlta")).addClass("active");
    $("#pageEyebrow").text("Usuarios / Alta");
    $("#pageTitle").text("Alta de usuario");
    $("#pageBreadcrumb").text("Alta");
  }

  function hidratarVistaDesdeBootstrap(view) {
    if (bootstrapAdminConsumido[view]) {
      return;
    }

    const data = bootstrapAdminData[view];
    if (!data) {
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "consulta") {
      renderUsuarios(data.usuarios || []);
      renderUsuariosPaginacion(data.pagination || {});
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "medidores") {
      renderMedidores(data.medidores || []);
      renderMedidoresPaginacion(data.pagination || {});
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "rutas") {
      renderRutas(data.rutas || []);
      renderRutasPaginacion(data.pagination || {});
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "periodos") {
      renderPeriodos(data.periodos || []);
      renderPeriodosPaginacion(data.pagination || {});
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "lecturas") {
      renderLecturas(data.lecturas || []);
      actualizarResumenCobro(data.summary || {});
      renderLecturasPaginacion(data.pagination || {});
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "pagos") {
      renderUsuariosPagoOptions(data.usuarios || [], data.selected_usuario_id || "");
      renderRecibosPago(data.recibos || []);
      renderPagosPaginacion(data.pagination || {});
      bootstrapAdminConsumido[view] = true;
      return;
    }

    if (view === "sistema") {
      renderSistemaUsuarios(data.usuarios || []);
      actualizarPaginacionSistema(data.pagination || {});
      renderBitacora(data.bitacora || []);
      renderBitacoraPaginacion(data.bitacoraPagination || {});
      actualizarCatalogosBitacora(data.catalogos || {});
      bootstrapAdminConsumido[view] = true;
    }
  }

  function renderUsuarios(usuarios) {
    const $tbody = $("#tablaUsuarios tbody");

    if (!usuarios.length) {
      $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">No se encontraron usuarios.</td></tr>');
      return;
    }

    const rows = usuarios.map(function (usuario) {
      const estadoBadge = Number(usuario.activo) === 1
        ? '<span class="badge badge-success">Activo</span>'
        : '<span class="badge badge-secondary">Baja</span>';
      const fachadaIcon = usuario.fachada_path
        ? ' <i class="fas fa-image text-info" title="Tiene foto de fachada"></i>'
        : "";

      const idSistema = Number(usuario.usuario_id || 0);
      const padronId = usuario.padron_id !== null && usuario.padron_id !== undefined && String(usuario.padron_id) !== ""
        ? String(usuario.padron_id)
        : null;
      const idLabel = padronId
        ? "ID " + idSistema + " | Padron " + padronId
        : "ID " + idSistema;

      return [
        '<tr>',
        '<td><strong>' + escapeHtml(usuario.nombre) + '</strong>' + fachadaIcon + '<br><small class="text-muted">' + escapeHtml(idLabel) + '</small></td>',
        '<td>' + escapeHtml(usuario.whatsapp || "Sin WhatsApp") + '</td>',
        '<td><span class="badge badge-light">' + escapeHtml(usuario.ruta || "Sin ruta") + '</span></td>',
        '<td>' + escapeHtml(usuario.comunidad || "Sin comunidad") + '</td>',
        '<td>' + escapeHtml(usuario.medidor || "Sin medidor") + '<br><small class="text-muted">' + escapeHtml(estadoMedidorTexto(usuario.estado_medidor)) + '</small></td>',
        '<td>' + estadoBadge + '</td>',
        '<td><div class="table-actions">',
        '<button class="btn btn-sm btn-primary btn-editar-usuario" data-id="' + usuario.usuario_id + '"><i class="fas fa-edit mr-1"></i> Editar</button>',
        '<button class="btn btn-sm btn-outline-danger btn-baja-tabla" data-id="' + usuario.usuario_id + '"><i class="fas fa-user-slash mr-1"></i> Baja</button>',
        '</div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderUsuariosPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    usuariosPaginaActual = page;
    usuariosTotalPaginas = totalPages;
    $("#usuariosPorPagina").val(String(data.per_page === 0 ? 0 : (data.effective_per_page || data.per_page || usuariosPorPaginaActual || 25)));

    let resumen = "Sin registros para mostrar";

    if (total > 0) {
      resumen = "Total " + total + " registros | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#usuariosResumenPaginacionInferior").text(resumen);
    $("#btnUsuariosAnterior").prop("disabled", page <= 1 || total === 0);
    $("#btnUsuariosSiguiente").prop("disabled", page >= totalPages || total === 0);
  }

  function cargarUsuarios(page) {
    const targetPage = page || usuariosPaginaActual || 1;
    const perPage = Number($("#usuariosPorPagina").val() || usuariosPorPaginaActual || 25);
    const requestToken = ++usuariosRequestToken;

    usuariosPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "usuarios.listar",
        nombre: $("#buscarNombreConsulta").val(),
        page: targetPage,
        per_page: perPage
      },
      beforeSend: function () {
        $("#tablaUsuarios").addClass("is-loading");
        $("#usuariosPorPagina").prop("disabled", true);
        $("#btnUsuariosAnterior").prop("disabled", true);
        $("#btnUsuariosSiguiente").prop("disabled", true);
      },
      success: function (response) {
        if (requestToken !== usuariosRequestToken) {
          return;
        }
        const usuarios = response.data && response.data.usuarios ? response.data.usuarios : [];
        const pagination = response.data && response.data.pagination ? response.data.pagination : null;
        renderUsuarios(usuarios);
        renderUsuariosPaginacion(pagination);
      },
      error: function (xhr) {
        if (requestToken !== usuariosRequestToken) {
          return;
        }
        showConsultaFeedback("danger", extractAjaxMessage(xhr, "No se pudieron consultar los usuarios."));
        $("#tablaUsuarios tbody").html('<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar usuarios.</td></tr>');
        $("#usuariosResumenPaginacionInferior").text("No fue posible cargar la paginacion");
      },
      complete: function () {
        if (requestToken !== usuariosRequestToken) {
          return;
        }
        $("#tablaUsuarios").removeClass("is-loading");
        $("#usuariosPorPagina").prop("disabled", false);
      }
    });
  }

  function llenarModal(usuario) {
    $("#formEditarUsuario")[0].reset();
    $("#modalFeedback").addClass("d-none");
    $("#editUsuarioId").val(usuario.usuario_id);
    $("#editDomicilioId").val(usuario.domicilio_id);
    $("#editMedidorId").val(usuario.medidor_id);
    $("#editNombre").val(usuario.nombre || "");
    $("#editEstadoUsuario").val(Number(usuario.activo) === 1 ? "Activo" : "Inactivo");
    $("#editWhatsapp").val(usuario.whatsapp || "");
    $("#editTelefono").val(usuario.telefono || "");
    $("#editCalle").val(usuario.calle || "");
    $("#editNumero").val(usuario.numero_domicilio || "");
    $("#editColonia").val(usuario.colonia || "");
    $("#editComunidad").val(usuario.comunidad || "Centro 1");
    cargarComboRutas($("#editRuta"), comunidadIdPorNombre(usuario.comunidad), usuario.ruta_id, usuario.ruta || "");
    $("#editLatitud").val(usuario.latitud || "");
    $("#editLongitud").val(usuario.longitud || "");
    $("#editGooglePlaceId").val(usuario.google_place_id || "");
    $("#editModoUbicacion").val(usuario.modo_ubicacion || "manual");
    $("#editReferenciaUbicacion").val(usuario.referencia_ubicacion || "");
    $("#editSearchDireccionMapa").val([usuario.calle, usuario.numero_domicilio, usuario.colonia].filter(Boolean).join(" "));
    renderLocationModeState(mapsConfig.edit);
    cargarComboMedidoresUsuario(usuario.medidor_id);
    $("#editEstadoMedidor").val(estadoMedidorTexto(usuario.estado_medidor));

    if (usuario.fachada_path) {
      $("#fachadaPreview").html('<img src="' + escapeHtml(usuario.fachada_path) + '" alt="Foto de fachada"><span>Foto actual de fachada.</span>');
    } else {
      $("#fachadaPreview").html('<i class="fas fa-home mr-2"></i><span>Sin foto de fachada registrada.</span>');
    }

    $('#tabsEditarUsuario a[href="#tabDatosUsuario"]').tab("show");
    $("#modalEditarUsuario").modal("show");
  }

  function abrirEditarUsuario(usuarioId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "usuarios.obtener",
        usuario_id: usuarioId
      },
      beforeSend: function () {
        showConsultaFeedback("info", "Cargando usuario...");
      },
      success: function (response) {
        $("#consultaFeedback").addClass("d-none");
        llenarModal(response.data || {});
      },
      error: function (xhr) {
        showConsultaFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar el usuario."));
      }
    });
  }

  function bajaUsuario(usuarioId, source) {
    if (!confirm("¿Deseas dar de baja este usuario?")) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "usuarios.baja",
        usuario_id: usuarioId
      },
      beforeSend: function () {
        if (source === "modal") {
          showModalFeedback("info", "Dando de baja usuario...");
        } else if (source === "duplicados") {
          $("#resultadoDuplicado")
            .removeClass("d-none warning")
            .html('<i class="fas fa-spinner fa-spin mr-2"></i><span>Dando de baja usuario...</span>');
        } else {
          showConsultaFeedback("info", "Dando de baja usuario...");
        }
      },
      success: function (response) {
        if (source === "modal") {
          showModalFeedback("success", response.message || "Usuario dado de baja.");
          $("#editEstadoUsuario").val("Inactivo");
        } else if (source === "duplicados") {
          $("#resultadoDuplicado")
            .removeClass("d-none")
            .addClass("warning")
            .html('<i class="fas fa-check-circle mr-2"></i><span>' + escapeHtml(response.message || "Usuario dado de baja.") + '</span>');
          revisarDuplicadosAlta();
        } else {
          showConsultaFeedback("success", response.message || "Usuario dado de baja.");
        }
        cargarUsuarios();
      },
      error: function (xhr) {
        const message = extractAjaxMessage(xhr, "No se pudo dar de baja el usuario.");
        if (source === "modal") {
          showModalFeedback("danger", message);
        } else if (source === "duplicados") {
          $("#resultadoDuplicado")
            .removeClass("d-none")
            .addClass("warning")
            .html('<i class="fas fa-exclamation-circle mr-2"></i><span>' + escapeHtml(message) + '</span>');
        } else {
          showConsultaFeedback("danger", message);
        }
      }
    });
  }

  function renderMedidores(medidores) {
    const $tbody = $("#tablaMedidores tbody");

    if (!medidores.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron medidores.</td></tr>');
      return;
    }

    const rows = medidores.map(function (medidor) {
      return [
        '<tr>',
        '<td><strong>' + escapeHtml(medidor.medidor) + '</strong><br><small class="text-muted">ID ' + medidor.medidor_id + '</small></td>',
        '<td>' + escapeHtml(medidor.usuario || "Sin usuario") + '</td>',
        '<td><span class="badge badge-light">' + escapeHtml(medidor.ruta || "Sin ruta") + '</span></td>',
        '<td>' + escapeHtml(medidor.comunidad || "Sin comunidad") + '</td>',
        '<td>' + estadoMedidorBadge(medidor.estado) + '</td>',
        '<td><div class="table-actions">',
        '<button class="btn btn-sm btn-primary btn-editar-medidor" data-id="' + medidor.medidor_id + '"><i class="fas fa-edit mr-1"></i> Editar</button>',
        '</div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderMedidoresPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    medidoresPaginaActual = page;
    medidoresTotalPaginas = totalPages;
    $("#medidoresPorPagina").val(String(data.per_page === 0 ? 0 : (data.effective_per_page || medidoresPorPaginaActual)));

    let resumen = "Sin registros para mostrar";

    if (medidoresPorPaginaActual === 0 && total > 0) {
      resumen = "Total " + total + " registros";
    } else if (total > 0) {
      resumen = "Total " + total + " registros | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#medidoresResumenPaginacionInferior").text(resumen);
    $("#btnMedidoresAnterior").prop("disabled", page <= 1 || total === 0 || medidoresPorPaginaActual === 0);
    $("#btnMedidoresSiguiente").prop("disabled", page >= totalPages || total === 0 || medidoresPorPaginaActual === 0);
  }

  function cargarMedidores(page) {
    const targetPage = page || medidoresPaginaActual || 1;
    const perPage = Number($("#medidoresPorPagina").val() || medidoresPorPaginaActual || 25);
    const buscar = $.trim($("#buscarMedidorListado").val() || "");
    const campo = $("#medidoresCampoBusqueda").val() || "todos";
    const requestToken = ++medidoresRequestToken;

    medidoresPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "medidores.listar",
        page: targetPage,
        per_page: perPage,
        buscar: buscar,
        campo: campo
      },
      beforeSend: function () {
        $("#tablaMedidores").addClass("is-loading");
        $("#medidoresPorPagina").prop("disabled", true);
        $("#btnMedidoresAnterior").prop("disabled", true);
        $("#btnMedidoresSiguiente").prop("disabled", true);
      },
      success: function (response) {
        if (requestToken !== medidoresRequestToken) {
          return;
        }
        const medidores = response.data && response.data.medidores ? response.data.medidores : [];
        const pagination = response.data && response.data.pagination ? response.data.pagination : null;
        renderMedidores(medidores);
        renderMedidoresPaginacion(pagination);
      },
      error: function (xhr) {
        if (requestToken !== medidoresRequestToken) {
          return;
        }
        showMedidoresFeedback("danger", extractAjaxMessage(xhr, "No se pudieron consultar los medidores."));
        $("#tablaMedidores tbody").html('<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar medidores.</td></tr>');
        $("#medidoresResumenPaginacionInferior").text("No fue posible cargar la paginacion");
      },
      complete: function () {
        if (requestToken !== medidoresRequestToken) {
          return;
        }
        $("#tablaMedidores").removeClass("is-loading");
        $("#medidoresPorPagina").prop("disabled", false);
      }
    });
  }

  function cargarComboMedidoresUsuario(selectedMedidorId) {
    $("#editMedidor").html('<option value="">Cargando medidores...</option>');

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "medidores.listar",
        per_page: 0
      },
      success: function (response) {
        const medidores = response.data && response.data.medidores ? response.data.medidores : [];
        const options = ['<option value="">Selecciona medidor</option>'].concat(medidores.map(function (medidor) {
          const selected = String(medidor.medidor_id) === String(selectedMedidorId) ? " selected" : "";
          return '<option value="' + medidor.medidor_id + '"' + selected + ' data-estado="' + escapeHtml(medidor.estado || "activo") + '">' +
            escapeHtml(medidor.medidor) + ' | ' + escapeHtml(medidor.usuario || "Sin usuario") + ' | Ruta: ' + escapeHtml(medidor.ruta || "Sin ruta") +
            '</option>';
        }));

        $("#editMedidor").html(options.join(""));
      },
      error: function (xhr) {
        $("#editMedidor").html('<option value="">No se pudieron cargar medidores</option>');
        showModalFeedback("danger", extractAjaxMessage(xhr, "No se pudieron cargar los medidores."));
      }
    });
  }

  function cargarSugerenciasMedidoresAlta() {
    const selectedValue = cleanCode($("#medidor").val(), 60);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "medidores.listar",
        per_page: 0
      },
      success: function (response) {
        const medidores = response.data && response.data.medidores ? response.data.medidores : [];
        catalogoMedidoresAlta = medidores;
        const options = ['<option value=""></option>'].concat(medidores.map(function (medidor) {
          const value = escapeHtml(cleanCode(medidor.medidor || "", 60));
          return '<option value="' + value + '">' + value + '</option>';
        }));

        $("#medidor").html(options.join(""));
        if (selectedValue) {
          ensureMedidorAltaOption(selectedValue);
        }
        inicializarMedidorAltaSelect2();
        if (selectedValue) {
          $("#medidor").val(selectedValue).trigger("change.select2");
        }
        renderMedidorAltaInfo();
        renderListaMedidoresAlta();
      }
    });
  }

  function cargarUsuariosMedidor(selectedUsuarioId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "medidores.usuariosDisponibles"
      },
      beforeSend: function () {
        $("#modalMedidorUsuario").html('<option value="">Cargando usuarios...</option>');
      },
      success: function (response) {
        const usuarios = response.data && response.data.usuarios ? response.data.usuarios : [];
        const options = ['<option value="">Todos los usuarios</option>'].concat(usuarios.map(function (usuario) {
          const selected = String(usuario.usuario_id) === String(selectedUsuarioId) ? " selected" : "";
          return '<option value="' + usuario.usuario_id + '"' + selected + '>' + escapeHtml(usuario.usuario) + ' | Ruta: ' + escapeHtml(usuario.ruta || "Sin ruta") + '</option>';
        }));
        $("#modalMedidorUsuario").html(options.join(""));
      },
      error: function (xhr) {
        $("#modalMedidorUsuario").html('<option value="">No se pudieron cargar usuarios</option>');
        showModalMedidorFeedback("danger", extractAjaxMessage(xhr, "No se pudieron cargar usuarios."));
      }
    });
  }

  function abrirAgregarMedidor() {
    $("#formMedidor")[0].reset();
    $("#modalMedidorFeedback").addClass("d-none");
    $("#medidorAccion").val("medidores.guardar");
    $("#modalMedidorId").val("");
    $("#modalMedidorTitulo").html('<i class="fas fa-tachometer-alt text-primary mr-2"></i>Agregar medidor');
    $("#grupoUsuarioMedidor").removeClass("d-none");
    $("#btnGuardarMedidor").html('<i class="fas fa-save mr-1"></i> Guardar medidor');
    cargarUsuariosMedidor();
    $("#modalMedidor").modal("show");
  }

  function abrirEditarMedidor(medidorId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "medidores.obtener",
        medidor_id: medidorId
      },
      beforeSend: function () {
        showMedidoresFeedback("info", "Cargando medidor...");
      },
      success: function (response) {
        const medidor = response.data || {};
        $("#formMedidor")[0].reset();
        $("#medidoresFeedback").addClass("d-none");
        $("#modalMedidorFeedback").addClass("d-none");
        $("#medidorAccion").val("medidores.actualizar");
        $("#modalMedidorId").val(medidor.medidor_id);
        $("#modalMedidorNombre").val(medidor.medidor || "");
        $("#modalMedidorEstado").val(estadoMedidorTexto(medidor.estado));
        $("#grupoUsuarioMedidor").addClass("d-none");
        $("#modalMedidorTitulo").html('<i class="fas fa-edit text-primary mr-2"></i>Editar medidor');
        $("#btnGuardarMedidor").html('<i class="fas fa-save mr-1"></i> Guardar cambios');
        $("#modalMedidor").modal("show");
      },
      error: function (xhr) {
        showMedidoresFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar el medidor."));
      }
    });
  }

  function renderRutas(rutas) {
    const $tbody = $("#tablaRutas tbody");

    if (!rutas.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron rutas.</td></tr>');
      return;
    }

    const rows = rutas.map(function (ruta) {
      const estadoBadge = Number(ruta.activo) === 1
        ? '<span class="badge badge-success">Activa</span>'
        : '<span class="badge badge-secondary">Baja</span>';

      return [
        '<tr>',
        '<td><strong>' + escapeHtml(ruta.codigo) + '</strong><br><small class="text-muted">ID ' + ruta.ruta_id + '</small></td>',
        '<td>' + escapeHtml(ruta.nombre || "Sin nombre") + '</td>',
        '<td>' + escapeHtml(ruta.comunidad || "Sin comunidad") + '</td>',
        '<td>' + escapeHtml(ruta.descripcion || "Sin descripcion") + '</td>',
        '<td>' + estadoBadge + '</td>',
        '<td><div class="table-actions">',
        '<button class="btn btn-sm btn-primary btn-editar-ruta" data-id="' + ruta.ruta_id + '"><i class="fas fa-edit mr-1"></i> Editar</button>',
        '<button class="btn btn-sm btn-outline-danger btn-baja-ruta-tabla" data-id="' + ruta.ruta_id + '"><i class="fas fa-route mr-1"></i> Baja</button>',
        '</div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderRutasPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    rutasPaginaActual = page;
    rutasTotalPaginas = totalPages;
    $("#rutasPorPagina").val(String(data.per_page === 0 ? 0 : (data.effective_per_page || rutasPorPaginaActual)));

    let resumen = "Sin registros para mostrar";

    if (rutasPorPaginaActual === 0 && total > 0) {
      resumen = "Total " + total + " registros";
    } else if (total > 0) {
      resumen = "Total " + total + " registros | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#rutasResumenPaginacionInferior").text(resumen);
    $("#btnRutasAnterior").prop("disabled", page <= 1 || total === 0 || rutasPorPaginaActual === 0);
    $("#btnRutasSiguiente").prop("disabled", page >= totalPages || total === 0 || rutasPorPaginaActual === 0);
  }

  function cargarRutas(page) {
    const targetPage = page || rutasPaginaActual || 1;
    const perPage = Number($("#rutasPorPagina").val() || rutasPorPaginaActual || 25);
    const buscar = $.trim($("#buscarRutaListado").val() || "");
    const campo = $("#rutasCampoBusqueda").val() || "todos";
    const requestToken = ++rutasRequestToken;

    rutasPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "rutas.listar",
        page: targetPage,
        per_page: perPage,
        buscar: buscar,
        campo: campo
      },
      beforeSend: function () {
        $("#tablaRutas").addClass("is-loading");
        $("#rutasPorPagina").prop("disabled", true);
        $("#btnRutasAnterior").prop("disabled", true);
        $("#btnRutasSiguiente").prop("disabled", true);
      },
      success: function (response) {
        if (requestToken !== rutasRequestToken) {
          return;
        }
        const rutas = response.data && response.data.rutas ? response.data.rutas : [];
        const pagination = response.data && response.data.pagination ? response.data.pagination : null;
        renderRutas(rutas);
        renderRutasPaginacion(pagination);
      },
      error: function (xhr) {
        if (requestToken !== rutasRequestToken) {
          return;
        }
        showRutasFeedback("danger", extractAjaxMessage(xhr, "No se pudieron consultar las rutas."));
        $("#tablaRutas tbody").html('<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar rutas.</td></tr>');
        $("#rutasResumenPaginacionInferior").text("No fue posible cargar la paginacion");
      },
      complete: function () {
        if (requestToken !== rutasRequestToken) {
          return;
        }
        $("#tablaRutas").removeClass("is-loading");
        $("#rutasPorPagina").prop("disabled", false);
      }
    });
  }

  function abrirAgregarRuta(comunidadId) {
    $("#formRuta")[0].reset();
    $("#modalRutaFeedback").addClass("d-none");
    $("#rutaAccion").val("rutas.guardar");
    $("#modalRutaId").val("");
    $("#modalRutaTitulo").html('<i class="fas fa-route text-primary mr-2"></i>Agregar ruta');
    $("#boxBajaRuta").addClass("d-none");
    restablecerBotonRuta();
    if (comunidadId) {
      $("#modalRutaComunidad").val(String(comunidadId));
    }
    $("#modalRuta").modal("show");
  }

  function abrirEditarRuta(rutaId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "rutas.obtener",
        ruta_id: rutaId
      },
      beforeSend: function () {
        showRutasFeedback("info", "Cargando ruta...");
      },
      success: function (response) {
        const ruta = response.data || {};
        $("#formRuta")[0].reset();
        $("#rutasFeedback").addClass("d-none");
        $("#modalRutaFeedback").addClass("d-none");
        $("#rutaAccion").val("rutas.actualizar");
        $("#modalRutaId").val(ruta.ruta_id);
        $("#modalRutaCodigo").val(ruta.codigo || "");
        $("#modalRutaNombre").val(ruta.nombre || "");
        $("#modalRutaComunidad").val(ruta.comunidad_id || "");
        $("#modalRutaDescripcion").val(ruta.descripcion || "");
        $("#modalRutaTitulo").html('<i class="fas fa-edit text-primary mr-2"></i>Editar ruta');
        $("#boxBajaRuta").removeClass("d-none");
        restablecerBotonRuta();
        $("#modalRuta").modal("show");
      },
      error: function (xhr) {
        showRutasFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar la ruta."));
      }
    });
  }

  function bajaRuta(rutaId, source) {
    if (!confirm("Deseas dar de baja esta ruta?")) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "rutas.baja",
        ruta_id: rutaId
      },
      beforeSend: function () {
        if (source === "modal") {
          showModalRutaFeedback("info", "Dando de baja ruta...");
        } else {
          showRutasFeedback("info", "Dando de baja ruta...");
        }
      },
      success: function (response) {
        if (source === "modal") {
          showModalRutaFeedback("success", response.message || "Ruta dada de baja.");
        } else {
          showRutasFeedback("success", response.message || "Ruta dada de baja.");
        }
        cargarRutas();
      },
      error: function (xhr) {
        const message = extractAjaxMessage(xhr, "No se pudo dar de baja la ruta.");
        if (source === "modal") {
          showModalRutaFeedback("danger", message);
        } else {
          showRutasFeedback("danger", message);
        }
      }
    });
  }

  function renderPeriodos(periodos) {
    const $tbody = $("#tablaPeriodos tbody");

    if (!periodos.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron periodos.</td></tr>');
      return;
    }

    const rows = periodos.map(function (periodo) {
      return [
        '<tr>',
        '<td><strong>' + escapeHtml(periodo.nombre) + '</strong><br><small class="text-muted">ID ' + periodo.periodo_id + '</small></td>',
        '<td>' + escapeHtml(periodo.fecha_inicio) + '</td>',
        '<td>' + escapeHtml(periodo.fecha_fin) + '</td>',
        '<td>' + escapeHtml(periodo.anio + ' / Bimestre ' + periodo.bimestre) + '</td>',
        '<td>' + estadoPeriodoBadge(periodo.estado) + '</td>',
        '<td><div class="table-actions">',
        '<button class="btn btn-sm btn-primary btn-editar-periodo" data-id="' + periodo.periodo_id + '"><i class="fas fa-edit mr-1"></i> Editar</button>',
        '<button class="btn btn-sm btn-outline-danger btn-baja-periodo-tabla" data-id="' + periodo.periodo_id + '"><i class="fas fa-calendar-times mr-1"></i> Baja</button>',
        '</div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderPeriodosPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    periodosPaginaActual = page;
    periodosTotalPaginas = totalPages;
    $("#periodosPorPagina").val(String(data.per_page === 0 ? 0 : (data.effective_per_page || periodosPorPaginaActual)));

    let resumen = "Sin registros para mostrar";

    if (periodosPorPaginaActual === 0 && total > 0) {
      resumen = "Total " + total + " registros";
    } else if (total > 0) {
      resumen = "Total " + total + " registros | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#periodosResumenPaginacionInferior").text(resumen);
    $("#btnPeriodosAnterior").prop("disabled", page <= 1 || total === 0 || periodosPorPaginaActual === 0);
    $("#btnPeriodosSiguiente").prop("disabled", page >= totalPages || total === 0 || periodosPorPaginaActual === 0);
  }

  function cargarPeriodos(page) {
    const targetPage = page || periodosPaginaActual || 1;
    const perPage = Number($("#periodosPorPagina").val() || periodosPorPaginaActual || 25);
    const requestToken = ++periodosRequestToken;

    periodosPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "periodos.listar",
        page: targetPage,
        per_page: perPage
      },
      beforeSend: function () {
        $("#tablaPeriodos").addClass("is-loading");
        $("#periodosPorPagina").prop("disabled", true);
        $("#btnPeriodosAnterior").prop("disabled", true);
        $("#btnPeriodosSiguiente").prop("disabled", true);
      },
      success: function (response) {
        if (requestToken !== periodosRequestToken) {
          return;
        }
        const periodos = response.data && response.data.periodos ? response.data.periodos : [];
        const pagination = response.data && response.data.pagination ? response.data.pagination : null;
        renderPeriodos(periodos);
        renderPeriodosPaginacion(pagination);
      },
      error: function (xhr) {
        if (requestToken !== periodosRequestToken) {
          return;
        }
        showPeriodosFeedback("danger", extractAjaxMessage(xhr, "No se pudieron consultar los periodos."));
        $("#tablaPeriodos tbody").html('<tr><td colspan="6" class="text-center text-danger py-4">Error al cargar periodos.</td></tr>');
        $("#periodosResumenPaginacionInferior").text("No fue posible cargar la paginacion");
      },
      complete: function () {
        if (requestToken !== periodosRequestToken) {
          return;
        }
        $("#tablaPeriodos").removeClass("is-loading");
        $("#periodosPorPagina").prop("disabled", false);
      }
    });
  }

  function cargarCatalogoPeriodos($select, selectedPeriodoId) {
    hidratarCatalogoPeriodos($select, selectedPeriodoId);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "periodos.listar",
        per_page: 0
      },
      success: function (response) {
        const periodos = response.data && response.data.periodos ? response.data.periodos : [];
        const selectedValue = String(selectedPeriodoId || $select.val() || "");
        const options = ['<option value="">Todos los periodos</option>'].concat(periodos
          .filter(function (periodo) {
            return String(periodo.estado || "").toLowerCase() !== "cancelado";
          })
          .map(function (periodo) {
            const selected = String(periodo.periodo_id) === selectedValue ? " selected" : "";
            return '<option value="' + periodo.periodo_id + '"' + selected + '>' + escapeHtml(periodo.nombre || "Sin periodo") + '</option>';
          }));

        $select.html(options.join(""));
      },
      error: function () {
        $select.html('<option value="">No se pudieron cargar periodos</option>');
      }
    });
  }

  function hidratarCatalogoPeriodos($select, selectedPeriodoId) {
    const periodosBootstrap = bootstrapAdminData.periodos && bootstrapAdminData.periodos.periodos
      ? bootstrapAdminData.periodos.periodos
      : [];

    if (!$select || !$select.length || !periodosBootstrap.length) {
      return;
    }

    const selectedValue = String(selectedPeriodoId || $select.val() || "");
    const options = ['<option value="">Todos los periodos</option>'].concat(periodosBootstrap
      .filter(function (periodo) {
        return String(periodo.estado || "").toLowerCase() !== "cancelado";
      })
      .map(function (periodo) {
        const selected = String(periodo.periodo_id) === selectedValue ? " selected" : "";
        return '<option value="' + periodo.periodo_id + '"' + selected + '>' + escapeHtml(periodo.nombre || "Sin periodo") + '</option>';
      }));

    $select.html(options.join(""));
  }

  function cargarFiltroPeriodosLectura(selectedPeriodoId) {
    cargarCatalogoPeriodos($("#filtroPeriodoLectura"), selectedPeriodoId);
  }

  function cargarFiltroPeriodoNotificacion(selectedPeriodoId) {
    cargarCatalogoPeriodos($("#filtroPeriodoNotificacionMasiva"), selectedPeriodoId);
  }

  function abrirAgregarPeriodo() {
    $("#formPeriodo")[0].reset();
    $("#modalPeriodoFeedback").addClass("d-none");
    $("#periodoAccion").val("periodos.guardar");
    $("#modalPeriodoId").val("");
    $("#modalPeriodoTitulo").html('<i class="fas fa-calendar-plus text-primary mr-2"></i>Agregar periodo');
    $("#boxBajaPeriodo").addClass("d-none");
    $("#btnGuardarPeriodo").html('<i class="fas fa-save mr-1"></i> Guardar periodo');
    $("#modalPeriodo").modal("show");
  }

  function abrirEditarPeriodo(periodoId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "periodos.obtener",
        periodo_id: periodoId
      },
      beforeSend: function () {
        showPeriodosFeedback("info", "Cargando periodo...");
      },
      success: function (response) {
        const periodo = response.data || {};
        $("#formPeriodo")[0].reset();
        $("#periodosFeedback").addClass("d-none");
        $("#modalPeriodoFeedback").addClass("d-none");
        $("#periodoAccion").val("periodos.actualizar");
        $("#modalPeriodoId").val(periodo.periodo_id);
        $("#modalPeriodoNombre").val(periodo.nombre || "");
        $("#modalPeriodoInicio").val(periodo.fecha_inicio || "");
        $("#modalPeriodoFin").val(periodo.fecha_fin || "");
        $("#modalPeriodoTitulo").html('<i class="fas fa-edit text-primary mr-2"></i>Editar periodo');
        $("#boxBajaPeriodo").removeClass("d-none");
        $("#btnGuardarPeriodo").html('<i class="fas fa-save mr-1"></i> Guardar cambios');
        $("#modalPeriodo").modal("show");
      },
      error: function (xhr) {
        showPeriodosFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar el periodo."));
      }
    });
  }

  function bajaPeriodo(periodoId, source) {
    if (!confirm("¿Deseas dar de baja este periodo?")) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "periodos.baja",
        periodo_id: periodoId
      },
      beforeSend: function () {
        if (source === "modal") {
          showModalPeriodoFeedback("info", "Dando de baja periodo...");
        } else {
          showPeriodosFeedback("info", "Dando de baja periodo...");
        }
      },
      success: function (response) {
        if (source === "modal") {
          showModalPeriodoFeedback("success", response.message || "Periodo dado de baja.");
        } else {
          showPeriodosFeedback("success", response.message || "Periodo dado de baja.");
        }
        cargarPeriodos();
      },
      error: function (xhr) {
        const message = extractAjaxMessage(xhr, "No se pudo dar de baja el periodo.");
        if (source === "modal") {
          showModalPeriodoFeedback("danger", message);
        } else {
          showPeriodosFeedback("danger", message);
        }
      }
    });
  }

  function renderLecturas(lecturas) {
    const $tbody = $("#tablaLecturas tbody");

    if (!lecturas.length) {
      $tbody.html('<tr><td colspan="9" class="text-center text-muted py-4">No se encontraron lecturas.</td></tr>');
      return;
    }

    const rows = lecturas.map(function (lectura) {
      const reciboBadge = lectura.recibo_id
        ? '<span class="badge badge-success">Generado</span><br><small class="text-muted">' + escapeHtml(lectura.folio || "") + '</small>'
        : '<span class="badge badge-warning">Pendiente</span>';
      const entregaBadge = lectura.recibo_id
        ? estadoEntregaBadge(lectura.recibo_entregado, lectura.fecha_entrega)
        : '<span class="badge badge-secondary">Sin recibo</span>';
        const imageButton = lectura.recibo_id
          ? '<button type="button" class="btn btn-sm btn-outline-info btn-ver-recibo" data-lectura-id="' + lectura.lectura_id + '"><i class="fas fa-image mr-1"></i> Ver</button>'
          : "";
      const printButton = lectura.recibo_id
        ? '<button class="btn btn-sm btn-outline-primary btn-imprimir-recibo" data-lectura-id="' + lectura.lectura_id + '" data-folio="' + escapeHtml(lectura.folio || "") + '" data-usuario="' + escapeHtml(lectura.usuario || "") + '"><i class="fas fa-print mr-1"></i> Imprimir</button>'
        : "";
      const paymentButton = lectura.recibo_id
        ? '<button class="btn btn-sm btn-outline-success btn-registrar-pago" data-id="' + lectura.recibo_id + '"><i class="fas fa-cash-register mr-1"></i> Pago</button>'
        : "";

      return [
        '<tr>',
        '<td><strong>' + escapeHtml(lectura.usuario) + '</strong><br><small class="text-muted">WhatsApp: ' + escapeHtml(lectura.whatsapp || "Sin WhatsApp") + '</small></td>',
        '<td>' + escapeHtml(lectura.medidor || "Sin medidor") + '<br><span class="badge badge-light">' + escapeHtml(lectura.ruta || "Sin ruta") + '</span></td>',
        '<td>' + escapeHtml(lectura.periodo || "Sin periodo") + '<br><small class="text-muted">' + escapeHtml(lectura.fecha_inicio || "") + ' / ' + escapeHtml(lectura.fecha_fin || "") + '</small></td>',
        '<td><small>Anterior</small> <strong>' + numberFormat(lectura.lectura_anterior) + '</strong><br><small>Actual</small> <strong>' + numberFormat(lectura.lectura_actual) + '</strong><br><span class="badge badge-info">' + numberFormat(lectura.consumo_m3) + ' m3</span></td>',
        '<td>' + escapeHtml(lectura.fecha_captura || "") + '<br><small class="text-muted">' + escapeHtml(lectura.latitud || "Sin GPS") + '</small></td>',
        '<td>' + reciboBadge + '</td>',
        '<td>' + entregaBadge + '</td>',
        '<td>' + estadoCobroBadge(lectura.estado_cobro) + '<br><small class="text-muted">Saldo ' + money(lectura.saldo || 0) + '</small></td>',
        '<td><div class="table-actions">',
        imageButton,
        printButton,
        paymentButton,
        '<button class="btn btn-sm btn-primary btn-generar-recibo" data-id="' + lectura.lectura_id + '"><i class="fas fa-file-invoice-dollar mr-1"></i> Recibo</button>',
        '</div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderLecturasPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    lecturasPaginaActual = page;
    lecturasTotalPaginas = totalPages;
    $("#lecturasPorPagina").val(String(data.per_page === 0 ? 0 : (data.effective_per_page || lecturasPorPaginaActual || 25)));

    let resumen = "Sin registros para mostrar";

    if (Number(data.per_page || 0) === 0 && total > 0) {
      resumen = "Total " + total + " registros";
    } else if (total > 0) {
      resumen = "Total " + total + " registros | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#lecturasResumenPaginacionInferior").text(resumen);
    const allMode = Number(data.per_page || 0) === 0;
    $("#btnLecturasAnterior").prop("disabled", page <= 1 || total === 0 || allMode);
    $("#btnLecturasSiguiente").prop("disabled", page >= totalPages || total === 0 || allMode);
  }

  function cargarLecturas(page) {
    const targetPage = page || lecturasPaginaActual || 1;
    const perPage = Number($("#lecturasPorPagina").val() || lecturasPorPaginaActual || 25);
    const requestToken = ++lecturasRequestToken;

    lecturasPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "lecturas.listar",
        page: targetPage,
        per_page: perPage,
        termino: $("#buscarLectura").val(),
        estado_cobro: $("#filtroEstadoRecibo").val(),
        periodo_id: $("#filtroPeriodoLectura").val()
      },
      beforeSend: function () {
        $("#lecturasPorPagina").prop("disabled", true);
        $("#btnLecturasAnterior").prop("disabled", true);
        $("#btnLecturasSiguiente").prop("disabled", true);
        $("#tablaLecturas tbody").html('<tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando lecturas...</td></tr>');
      },
      success: function (response) {
        if (requestToken !== lecturasRequestToken) {
          return;
        }
        const lecturas = response.data && response.data.lecturas ? response.data.lecturas : [];
        actualizarResumenCobro(response.data && response.data.summary ? response.data.summary : {});
        renderLecturas(lecturas);
        renderLecturasPaginacion(response.data && response.data.pagination ? response.data.pagination : {});
      },
      error: function (xhr) {
        if (requestToken !== lecturasRequestToken) {
          return;
        }
        showLecturasFeedback("danger", extractAjaxMessage(xhr, "No se pudieron consultar las lecturas."));
        $("#tablaLecturas tbody").html('<tr><td colspan="9" class="text-center text-danger py-4">Error al cargar lecturas.</td></tr>');
        $("#lecturasResumenPaginacionInferior").text("No fue posible cargar la paginacion");
      },
      complete: function () {
        if (requestToken !== lecturasRequestToken) {
          return;
        }
        $("#lecturasPorPagina").prop("disabled", false);
      }
    });
  }

  function estadoCobroBadge(estado) {
    const config = {
      adeudo: { clase: "badge-danger", texto: "Adeudo" },
      pendiente: { clase: "badge-warning", texto: "Pendiente" },
      parcial: { clase: "badge-info", texto: "Parcial" },
      pagado: { clase: "badge-success", texto: "Pagado" },
      sin_recibo: { clase: "badge-secondary", texto: "Sin recibo" },
      cancelado: { clase: "badge-dark", texto: "Cancelado" }
    };
    const item = config[estado] || config.pendiente;
    return '<span class="badge ' + item.clase + ' payment-status-badge">' + item.texto + '</span>';
  }

  function estadoEntregaBadge(entregado, fechaEntrega) {
    const esEntregado = Number(entregado || 0) === 1;
    if (!esEntregado) {
      return '<span class="badge badge-secondary payment-status-badge">No entregado</span>';
    }

    const detalle = fechaEntrega
      ? '<br><small class="text-muted">' + escapeHtml(fechaEntrega) + '</small>'
      : "";

    return '<span class="badge badge-success payment-status-badge">Entregado</span>' + detalle;
  }

  function actualizarResumenCobro(summary) {
    $("#countAdeudo").text(summary.adeudo || 0);
    $("#countPendiente").text(summary.pendiente || 0);
    $("#countParcial").text(summary.parcial || 0);
    $("#countPagado").text(summary.pagado || 0);
  }

  function renderNotificacionesMasivas(items) {
    const $tbody = $("#tablaNotificacionesMasivas tbody");

    if (!items.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No hay envios preparados.</td></tr>');
      return;
    }

    const rows = items.map(function (item) {
      const resultado = item.resultado || "pendiente";
      const resultadoBadge = {
        pendiente: '<span class="badge badge-secondary">Pendiente</span>',
        bloqueado: '<span class="badge badge-warning">Bloqueado</span>',
        omitido: '<span class="badge badge-dark">Omitido</span>',
        enviando: '<span class="badge badge-info">Enviando</span>',
        enviado: '<span class="badge badge-success">Enviado</span>',
        error: '<span class="badge badge-danger">Error</span>'
      };
      const contacto = item.whatsapp || item.destino_contacto || "";
      const contactoTexto = item.whatsapp
        ? item.whatsapp
        : (contacto ? contacto + " (sin confirmar como WhatsApp)" : "Sin WhatsApp");

      return [
        '<tr>',
        '<td><strong>' + escapeHtml(item.usuario || "") + '</strong><br><small class="text-muted">' + escapeHtml(item.medidor || "Sin medidor") + ' | ' + escapeHtml(item.ruta || "Sin ruta") + '</small></td>',
        '<td><strong>' + escapeHtml(item.folio || "Sin folio") + '</strong><br><small class="text-muted">' + escapeHtml(item.periodo || "Sin periodo") + '</small></td>',
        '<td>' + estadoCobroBadge(item.estado_cobro) + '<br><small class="text-muted">Saldo ' + money(item.saldo || 0) + '</small></td>',
        '<td>' + escapeHtml(contactoTexto) + '</td>',
        '<td><small>' + escapeHtml(item.mensaje_sugerido || "") + '</small></td>',
        '<td>' + (resultadoBadge[resultado] || resultadoBadge.pendiente) + '<br><small class="text-muted">' + escapeHtml(item.detalle || "") + '</small></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function pausaNotificacionMs() {
    const seconds = Math.max(5, Math.min(60, Number($("#pausaNotificacionMasiva").val() || 8)));
    $("#pausaNotificacionMasiva").val(seconds);
    return seconds * 1000;
  }

  function opcionesEstadoPorTipoNotificacion(tipoMensaje) {
    const opciones = {
      recordatorio: [
        { value: "pendiente", label: "Pendientes" },
        { value: "adeudo", label: "Con adeudo" },
        { value: "todos", label: "Todos coincidentes" }
      ],
      adeudo: [
        { value: "adeudo", label: "Con adeudo" }
      ],
      agradecimiento: [
        { value: "pagado", label: "Pagados" }
      ],
      aviso: [
        { value: "todos", label: "Todos coincidentes" },
        { value: "pendiente", label: "Pendientes" },
        { value: "adeudo", label: "Con adeudo" },
        { value: "pagado", label: "Pagados" }
      ]
    };

    return opciones[tipoMensaje] || opciones.recordatorio;
  }

  function sincronizarTipoNotificacionMasiva() {
    const tipoMensaje = $("#tipoNotificacionMasiva").val() || "recordatorio";
    const opciones = opcionesEstadoPorTipoNotificacion(tipoMensaje);
    const actual = String($("#estadoNotificacionMasiva").val() || "");
    const optionsHtml = opciones.map(function (opcion) {
      const selected = opcion.value === actual ? " selected" : "";
      return '<option value="' + opcion.value + '"' + selected + '>' + opcion.label + '</option>';
    }).join("");

    $("#estadoNotificacionMasiva").html(optionsHtml);

    if (!opciones.some(function (opcion) { return opcion.value === actual; })) {
      $("#estadoNotificacionMasiva").val(opciones[0].value);
    }
  }

  function actualizarEstadoEnvioMasivo(texto) {
    $("#statusNotificacionMasiva").text(texto);
  }

  function totalNotificacionesEnviables() {
    return colaNotificacionesMasivas.filter(function (item) {
      return Boolean(item.puede_enviar);
    }).length;
  }

  function actualizarBotonesEnvioMasivo() {
    const enviables = totalNotificacionesEnviables();
    $("#btnIniciarNotificacionMasiva").prop("disabled", enviables <= 0 || envioMasivoActivo);
    $("#btnDetenerNotificacionMasiva").prop("disabled", !envioMasivoActivo);
    $("#btnPrepararNotificacionMasiva").prop("disabled", envioMasivoActivo);
  }

  function prepararNotificacionesMasivas() {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "recibos.notificaciones",
        estado_cobro: $("#estadoNotificacionMasiva").val(),
        tipo_mensaje: $("#tipoNotificacionMasiva").val(),
        periodo_id: $("#filtroPeriodoNotificacionMasiva").val(),
        limit: $("#limiteNotificacionMasiva").val()
      },
      beforeSend: function () {
        showNotificacionesMasivasFeedback("info", "Preparando lista de notificaciones...");
        $("#tablaNotificacionesMasivas tbody").html('<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Preparando envios...</td></tr>');
      },
      success: function (response) {
        colaNotificacionesMasivas = ((response.data && response.data.notificaciones) || []).map(function (item) {
          item.resultado = item.puede_enviar ? "pendiente" : "bloqueado";
          item.detalle = item.detalle_preparacion || (item.puede_enviar ? "" : "No se puede enviar.");
          return item;
        });
        indiceNotificacionMasiva = 0;
        renderNotificacionesMasivas(colaNotificacionesMasivas);
        actualizarEstadoEnvioMasivo("Lista preparada con " + colaNotificacionesMasivas.length + " registros. " + totalNotificacionesEnviables() + " listos para envio.");
        actualizarBotonesEnvioMasivo();
        showNotificacionesMasivasFeedback("success", response.message || "Lista preparada correctamente.");
      },
      error: function (xhr) {
        colaNotificacionesMasivas = [];
        indiceNotificacionMasiva = 0;
        renderNotificacionesMasivas([]);
        actualizarEstadoEnvioMasivo("No se pudo preparar la lista de envios.");
        actualizarBotonesEnvioMasivo();
        showNotificacionesMasivasFeedback("danger", extractAjaxMessage(xhr, "No se pudo preparar la lista de notificaciones."));
      }
    });
  }

  function detenerNotificacionesMasivas(mensaje) {
    envioMasivoActivo = false;
    if (temporizadorNotificacionMasiva) {
      clearTimeout(temporizadorNotificacionMasiva);
      temporizadorNotificacionMasiva = null;
    }
    actualizarBotonesEnvioMasivo();
    if (mensaje) {
      actualizarEstadoEnvioMasivo(mensaje);
    }
  }

  function procesarSiguienteNotificacionMasiva() {
    if (!envioMasivoActivo) {
      return;
    }

    if (indiceNotificacionMasiva >= colaNotificacionesMasivas.length) {
      detenerNotificacionesMasivas("Envio masivo terminado. Se procesaron " + colaNotificacionesMasivas.length + " registros.");
      showNotificacionesMasivasFeedback("success", "El envio masivo termino correctamente.");
      cargarPanelWhatsapp();
      return;
    }

    const item = colaNotificacionesMasivas[indiceNotificacionMasiva];
    if (!item.puede_enviar) {
      item.resultado = item.resultado === "enviado" ? "enviado" : "omitido";
      item.detalle = item.detalle || "No se puede enviar sin WhatsApp registrado.";
      renderNotificacionesMasivas(colaNotificacionesMasivas);
      indiceNotificacionMasiva += 1;
      procesarSiguienteNotificacionMasiva();
      return;
    }

    item.resultado = "enviando";
    item.detalle = "En cola";
    renderNotificacionesMasivas(colaNotificacionesMasivas);
    actualizarEstadoEnvioMasivo("Enviando " + (indiceNotificacionMasiva + 1) + " de " + colaNotificacionesMasivas.length + "...");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "recibos.notificarWhatsApp",
        recibo_id: item.recibo_id,
        tipo_mensaje: $("#tipoNotificacionMasiva").val()
      },
      success: function (response) {
        const data = response.data || {};
        item.resultado = "enviado";
        item.detalle = "Enviado a " + (data.whatsapp || item.whatsapp || "");
        renderNotificacionesMasivas(colaNotificacionesMasivas);
      },
      error: function (xhr) {
        item.resultado = "error";
        item.detalle = extractAjaxMessage(xhr, "No se pudo enviar.");
        renderNotificacionesMasivas(colaNotificacionesMasivas);
      },
      complete: function () {
        indiceNotificacionMasiva += 1;
        if (!envioMasivoActivo) {
          actualizarBotonesEnvioMasivo();
          return;
        }
        temporizadorNotificacionMasiva = setTimeout(function () {
          procesarSiguienteNotificacionMasiva();
        }, pausaNotificacionMs());
      }
    });
  }

  function estadoPagoBadge(estado) {
    const config = {
      pagado: { clase: "badge-success", texto: "Pagado" },
      parcial: { clase: "badge-info", texto: "Parcial" },
      pendiente: { clase: "badge-warning", texto: "Pendiente" },
      cancelado: { clase: "badge-danger", texto: "Cancelado" }
    };
    const item = config[estado] || config.pendiente;
    return '<span class="badge ' + item.clase + ' payment-status-badge">' + item.texto + '</span>';
  }

  function metodoPagoTexto(metodo) {
    const map = {
      efectivo: "Efectivo",
      spei: "SPEI",
      transferencia: "SPEI",
      tarjeta: "Tarjeta",
      otro: "Otro"
    };

    return map[metodo] || metodo || "Sin metodo";
  }

  function cargarUsuariosPago(termino, selectedUsuarioId) {
    if (!$.trim(termino || "")) {
      hidratarUsuariosPago(selectedUsuarioId);
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "pagos.usuarios",
        termino: termino || ""
      },
      beforeSend: function () {
        $("#selectUsuarioPago").html('<option value="">Buscando usuarios...</option>');
      },
      success: function (response) {
        const usuarios = response.data && response.data.usuarios ? response.data.usuarios : [];
        const options = ['<option value="">Selecciona usuario</option>'].concat(usuarios.map(function (usuario) {
          const selected = String(usuario.usuario_id) === String(selectedUsuarioId || "") ? " selected" : "";
          const label = [
            usuario.usuario,
            usuario.ruta ? "Ruta " + usuario.ruta : "",
            usuario.medidor ? "Medidor " + usuario.medidor : ""
          ].filter(Boolean).join(" | ");

          return '<option value="' + usuario.usuario_id + '"' + selected + '>' + escapeHtml(label) + '</option>';
        }));

        $("#selectUsuarioPago").html(options.join(""));
      },
      error: function (xhr) {
        $("#selectUsuarioPago").html('<option value="">No se pudieron cargar usuarios</option>');
        showPagosFeedback("danger", extractAjaxMessage(xhr, "No se pudo consultar el catalogo de usuarios."));
      }
    });
  }

  function renderUsuariosPagoOptions(usuarios, selectedUsuarioId) {
    const options = ['<option value="">Todos los usuarios</option>'].concat((usuarios || []).map(function (usuario) {
      const selected = String(usuario.usuario_id) === String(selectedUsuarioId || "") ? " selected" : "";
      const label = [
        usuario.usuario,
        usuario.ruta ? "Ruta " + usuario.ruta : "",
        usuario.medidor ? "Medidor " + usuario.medidor : ""
      ].filter(Boolean).join(" | ");

      return '<option value="' + usuario.usuario_id + '"' + selected + '>' + escapeHtml(label) + '</option>';
    }));

    $("#selectUsuarioPago").html(options.join(""));
  }

  function hidratarUsuariosPago(selectedUsuarioId) {
    const pagosBootstrap = bootstrapAdminData.pagos || {};
    const usuarios = pagosBootstrap.usuarios || [];

    if (!usuarios.length) {
      return;
    }

    renderUsuariosPagoOptions(usuarios, selectedUsuarioId || pagosBootstrap.selected_usuario_id || "");

    if (!selectedUsuarioId && pagosBootstrap.recibos && pagosBootstrap.recibos.length) {
      renderRecibosPago(pagosBootstrap.recibos);
    }
  }

  function renderRecibosPago(recibos) {
    const $tbody = $("#tablaPagosRecibos tbody");

    if (!recibos.length) {
      $tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">No se encontraron recibos para los filtros actuales.</td></tr>');
      return;
    }

    const rows = recibos.map(function (recibo) {
      return [
        '<tr>',
        '<td><strong>' + escapeHtml(recibo.folio || "Sin folio") + '</strong><br><small class="text-muted">' + escapeHtml(recibo.usuario || "") + '</small><br><small class="text-muted">' + escapeHtml(recibo.medidor || "Sin medidor") + ' | ' + escapeHtml(recibo.ruta || "Sin ruta") + '</small></td>',
        '<td>' + escapeHtml(recibo.periodo || "Sin periodo") + '<br><small class="text-muted">Vence ' + escapeHtml(recibo.fecha_vencimiento || "Sin fecha") + '</small></td>',
        '<td>' + estadoEntregaBadge(recibo.recibo_entregado, recibo.fecha_entrega) + '</td>',
        '<td>' + estadoPagoBadge(recibo.estado_pago) + '<br><small class="text-muted text-uppercase">' + escapeHtml(recibo.estado_recibo || "generado") + '</small></td>',
        '<td><div class="table-actions"><button class="btn btn-sm btn-primary btn-registrar-pago" data-id="' + recibo.recibo_id + '"><i class="fas fa-hand-holding-usd mr-1"></i> Registrar</button></div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderPagosPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    pagosPaginaActual = page;
    pagosTotalPaginas = totalPages;
    $("#pagosPorPagina").val(String(data.per_page === 0 ? 0 : (data.effective_per_page || pagosPorPaginaActual || 25)));

    let resumen = "Sin registros para mostrar";

    if (Number(data.per_page || 0) === 0 && total > 0) {
      resumen = "Total " + total + " registros";
    } else if (total > 0) {
      resumen = "Total " + total + " registros | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#pagosResumenPaginacionInferior").text(resumen);
    const allMode = Number(data.per_page || 0) === 0;
    $("#btnPagosAnterior").prop("disabled", page <= 1 || total === 0 || allMode);
    $("#btnPagosSiguiente").prop("disabled", page >= totalPages || total === 0 || allMode);
  }

  function cargarRecibosPago(page) {
    const usuarioId = $("#selectUsuarioPago").val();
    const targetPage = page || pagosPaginaActual || 1;
    const perPage = Number($("#pagosPorPagina").val() || pagosPorPaginaActual || 25);
    const requestToken = ++pagosRequestToken;

    pagosPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "pagos.recibos",
        usuario_id: usuarioId || 0,
        page: targetPage,
        per_page: perPage
      },
      beforeSend: function () {
        $("#pagosPorPagina").prop("disabled", true);
        $("#btnPagosAnterior").prop("disabled", true);
        $("#btnPagosSiguiente").prop("disabled", true);
        $("#tablaPagosRecibos tbody").html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando recibos...</td></tr>');
      },
      success: function (response) {
        if (requestToken !== pagosRequestToken) {
          return;
        }
        const recibos = response.data && response.data.recibos ? response.data.recibos : [];
        renderRecibosPago(recibos);
        renderPagosPaginacion(response.data && response.data.pagination ? response.data.pagination : {});
      },
      error: function (xhr) {
        if (requestToken !== pagosRequestToken) {
          return;
        }
        showPagosFeedback("danger", extractAjaxMessage(xhr, "No se pudieron consultar los recibos."));
        $("#tablaPagosRecibos tbody").html('<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar los recibos.</td></tr>');
        $("#pagosResumenPaginacionInferior").text("No fue posible cargar la paginacion");
      },
      complete: function () {
        if (requestToken !== pagosRequestToken) {
          return;
        }
        $("#pagosPorPagina").prop("disabled", false);
      }
    });
  }

  function renderHistorialPagos(pagos) {
    const $tbody = $("#tablaHistorialPagos tbody");

    if (!pagos.length) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Sin pagos registrados para este recibo.</td></tr>');
      return;
    }

    const rows = pagos.map(function (pago) {
      return [
        '<tr>',
        '<td>' + escapeHtml(pago.fecha_pago || "") + '</td>',
        '<td>' + escapeHtml(metodoPagoTexto(pago.metodo)) + '</td>',
        '<td><strong>' + money(pago.monto) + '</strong></td>',
        '<td>' + escapeHtml(pago.referencia || "Sin referencia") + '</td>',
        '<td>' + escapeHtml(pago.observaciones || "-") + '</td>',
        '<td><div class="table-actions"><button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-pago" data-id="' + pago.pago_id + '"><i class="fas fa-trash-alt mr-1"></i> Eliminar</button></div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function llenarModalPago(recibo) {
    reciboPagoActual = recibo;
    $("#formRegistrarPago")[0].reset();
    $("#modalPagoFeedback").addClass("d-none");
    $("#pagoReciboId").val(recibo.recibo_id);
    $("#pagoMonto").val(Number(recibo.saldo || 0).toFixed(2));
    $("#pagoFecha").val(dateInputValue(new Date()) + "T12:00");
    $("#pagoMetodo").val("efectivo");
    $("#pagoReferencia").val("");
    $("#pagoObservaciones").val("");
    $("#pagoReciboEntregado").prop("checked", Number(recibo.recibo_entregado || 0) === 1);

    $("#pagoResumenRecibo").html([
      '<div class="recibo-summary-title">' + escapeHtml(recibo.usuario || "Sin usuario") + '</div>',
      '<div class="recibo-summary-grid">',
      '<span>Folio <strong>' + escapeHtml(recibo.folio || "Sin folio") + '</strong></span>',
      '<span>Periodo <strong>' + escapeHtml(recibo.periodo || "Sin periodo") + '</strong></span>',
      '<span>Ruta <strong>' + escapeHtml(recibo.ruta || "Sin ruta") + '</strong></span>',
      '<span>Medidor <strong>' + escapeHtml(recibo.medidor || "Sin medidor") + '</strong></span>',
      '<span>Entrega <strong>' + (Number(recibo.recibo_entregado || 0) === 1 ? "ENTREGADO" : "NO ENTREGADO") + '</strong></span>',
      '</div>'
    ].join(""));

    $("#pagoTotalRecibo").text(money(recibo.total));
    $("#pagoTotalAcumulado").text(money(recibo.total_pagado));
    $("#pagoSaldoPendiente").text(money(recibo.saldo));
    $("#pagoEstadoActual").html(estadoPagoBadge(recibo.estado_pago));
    renderHistorialPagos(recibo.pagos || []);

    const bloqueado = String(recibo.estado_pago) === "pagado" || String(recibo.estado_pago) === "cancelado" || Number(recibo.saldo || 0) <= 0;
    $("#btnGuardarPago").prop("disabled", bloqueado);
    if (bloqueado) {
      showModalPagoFeedback("info", "Este recibo ya no tiene saldo disponible para registrar.");
    }

    $('#tabsPagoModal a[href="#tabRegistrarPago"]').tab("show");
    $("#modalRegistrarPago").modal("show");
  }

  function abrirRegistrarPago(reciboId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "pagos.obtenerRecibo",
        recibo_id: reciboId
      },
      beforeSend: function () {
        showPagosFeedback("info", "Cargando recibo...");
      },
      success: function (response) {
        $("#pagosFeedback").addClass("d-none");
        llenarModalPago(response.data || {});
      },
      error: function (xhr) {
        showPagosFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar el recibo."));
      }
    });
  }

  function whatsappStatusBadge(status) {
    const config = {
      authenticated: { clase: "badge-success", texto: "AUTHENTICATED" },
      standby: { clase: "badge-success", texto: "STANDBY" },
      qr: { clase: "badge-warning", texto: "QR" },
      initialize: { clase: "badge-secondary", texto: "INITIALIZE" },
      retrying: { clase: "badge-info", texto: "RETRYING" },
      loading: { clase: "badge-info", texto: "LOADING" },
      disconnected: { clase: "badge-danger", texto: "DISCONNECTED" },
      unknown: { clase: "badge-secondary", texto: "UNKNOWN" }
    };
    const item = config[status] || config.unknown;
    return '<span class="badge ' + item.clase + '">' + item.texto + '</span>';
  }

  function whatsappMessageDate(message) {
    return message.time || message.timestamp || message.created_at || message.datetime || "-";
  }

  function whatsappMessageText(message) {
    return message.body || message.caption || message.text || message.msg || message.type || "Sin contenido";
  }

  function whatsappMessageTarget(message) {
    return message.to || message.chatId || message.chat_id || message.receiver || "-";
  }

  function whatsappMessageId(message) {
    return message.id || message.msgId || message.message_id || "-";
  }

  function renderWhatsappMessages(messages) {
    const $tbody = $("#tablaWhatsappMensajes tbody");

    if (!messages.length) {
      $tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">No hay mensajes para este filtro.</td></tr>');
      return;
    }

    const rows = messages.map(function (message) {
      const content = String(whatsappMessageText(message));
      return [
        '<tr>',
        '<td><small class="text-muted">' + escapeHtml(String(whatsappMessageId(message))) + '</small></td>',
        '<td>' + escapeHtml(String(whatsappMessageTarget(message))) + '</td>',
        '<td>' + escapeHtml(content.length > 180 ? content.slice(0, 180) + "..." : content) + '</td>',
        '<td>' + escapeHtml(String(message.status || message.ack || $("#whatsappMessageStatus").val() || "-")).toUpperCase() + '</td>',
        '<td>' + escapeHtml(String(whatsappMessageDate(message))) + '</td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
  }

  function renderWhatsappPanel(data) {
    const linked = data.is_linked ? "Si, enlazado" : "Pendiente de enlace";

    $("#whatsappInstanceId").text(data.instance_id || "-");
    $("#whatsappStatusBadge").html(whatsappStatusBadge(data.account_status || "unknown"));
    $("#whatsappSubstatus").text(data.account_substatus || "-");
    $("#whatsappLinkedText").text(linked);
    $("#whatsappCheckedAt").text(data.checked_at || "-");

    if (data.is_linked) {
      $("#whatsappQrBox").html('<div class="recibo-preview-empty text-success"><i class="fas fa-check-circle"></i><span>Celular enlazado. No es necesario escanear QR.</span></div>');
    } else if (data.qr_image) {
      $("#whatsappQrBox").html('<img src="' + data.qr_image + '" alt="QR de WhatsApp">');
    } else {
      $("#whatsappQrBox").html('<div class="recibo-preview-empty"><i class="fas fa-qrcode"></i><span>No se pudo cargar el QR en este momento.</span></div>');
    }

    renderWhatsappMessages(data.messages || []);
  }

  function cargarPanelWhatsapp() {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "whatsapp.panel",
        message_status: $("#whatsappMessageStatus").val() || "sent"
      },
      beforeSend: function () {
        $("#whatsappQrBox").html('<div class="recibo-preview-empty"><i class="fas fa-spinner fa-spin"></i><span>Consultando UltraMsg...</span></div>');
        $("#tablaWhatsappMensajes tbody").html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando mensajes...</td></tr>');
      },
      success: function (response) {
        $("#whatsappFeedback").addClass("d-none");
        renderWhatsappPanel(response.data || {});
      },
      error: function (xhr) {
        showWhatsappFeedback("danger", extractAjaxMessage(xhr, "No se pudo consultar la instancia de WhatsApp."));
        $("#whatsappQrBox").html('<div class="recibo-preview-empty"><i class="fas fa-exclamation-circle"></i><span>Error al cargar el QR.</span></div>');
        $("#tablaWhatsappMensajes tbody").html('<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar mensajes.</td></tr>');
      }
    });
  }

  function obtenerConfiguracionCobroAgua() {
    return $.extend({}, defaultCobroAguaConfig, bootstrapAdminData.cobro_agua || {});
  }

  function calcularSubtotalAgua(consumo) {
    const config = obtenerConfiguracionCobroAgua();
    const limite = Math.max(Number(config.limite_tramo_base_m3 || 0), 0);
    const precioBase = Math.max(Number(config.precio_tramo_base_m3 || 0), 0);
    const precioExcedente = Math.max(Number(config.precio_excedente_m3 || 0), 0);
    const consumoSeguro = Math.max(Number(consumo || 0), 0);
    const consumoBase = Math.min(consumoSeguro, limite);
    const consumoExcedente = Math.max(consumoSeguro - limite, 0);

    return (consumoBase * precioBase) + (consumoExcedente * precioExcedente);
  }

  function aplicarTarifaCobroAguaEnUI() {
    const config = obtenerConfiguracionCobroAgua();
    const nombre = config.nombre || defaultCobroAguaConfig.nombre;
    const descripcion = config.descripcion || defaultCobroAguaConfig.descripcion;

    $("#reciboTarifaNombre").text(nombre);
    $("#reciboTarifaResumen").text(descripcion);
    $("#previewReciboTarifaNombre").text(nombre);
    $("#previewReciboTarifaResumen").text(descripcion);
  }

  function calcularTotalRecibo() {
    const consumo = lecturaReciboActual ? Number(lecturaReciboActual.consumo_m3 || 0) : 0;
    const subtotalAgua = calcularSubtotalAgua(consumo);
    const cooperaciones = Number($("#reciboCooperaciones").val() || 0);
    const multas = Number($("#reciboMultas").val() || 0);
    const recargos = Number($("#reciboRecargos").val() || 0);
    const total = subtotalAgua + cooperaciones + multas + recargos;

    $("#reciboTotalEstimado").text(money(total));
  }

  function actualizarBotonEnvioRecibo() {
    const $button = $("#btnEnviarReciboWhatsapp");
    const tieneImagen = lecturaReciboActual && lecturaReciboActual.recibo_id && lecturaReciboActual.imagen_path;
    const tieneWhatsapp = lecturaReciboActual && $.trim(String(lecturaReciboActual.whatsapp || "")) !== "";

    if (!tieneImagen) {
      $button.prop("disabled", true).removeAttr("data-recibo-id")
        .attr("title", "Primero genera la imagen del recibo");
      return;
    }

    $button
      .prop("disabled", !tieneWhatsapp)
      .attr("data-recibo-id", lecturaReciboActual.recibo_id)
      .attr("title", tieneWhatsapp ? "Enviar recibo al WhatsApp registrado" : "El usuario no tiene WhatsApp registrado");
  }

  function actualizarBotonImprimirReciboActual() {
    const $button = $("#btnImprimirReciboActual");
    const tieneLectura = lecturaReciboActual && Number(lecturaReciboActual.lectura_id || 0) > 0;

    if (!tieneLectura) {
      $button.addClass("d-none").prop("disabled", true).removeAttr("data-lectura-id data-folio data-usuario");
      return;
    }

    $button
      .removeClass("d-none")
      .prop("disabled", false)
      .attr("data-lectura-id", lecturaReciboActual.lectura_id)
      .attr("data-folio", lecturaReciboActual.folio || "")
      .attr("data-usuario", lecturaReciboActual.usuario || "");
  }

  function rutaAbsolutaArchivo(path) {
    if (!path) {
      return "";
    }

    try {
      return new URL(path, window.location.href).href;
    } catch (error) {
      return path;
    }
  }

  function pxPlantillaAPt(value) {
    return ((Number(value || 0) * 72) / 300).toFixed(2);
  }

  function porcentajePlantilla(value, total) {
    if (!total) {
      return "0%";
    }

    return ((Number(value || 0) / Number(total)) * 100).toFixed(4) + "%";
  }

  function qrPublicUrl(token, size, fallback) {
    const encoded = encodeURIComponent(token || "");
    const qrSize = Number(size || 184);

    if (fallback) {
      return "https://quickchart.io/qr?size=" + qrSize + "&text=" + encoded;
    }

    return "https://api.qrserver.com/v1/create-qr-code/?size=" + qrSize + "x" + qrSize + "&data=" + encoded;
  }

  function construirCampoImpresionHtml(canvas, fieldName, config, value, printConfig) {
    const text = String(value || "").trim();
    const fontScale = Math.max(1, Number((printConfig && printConfig.fontScale) || 1));
    const lineHeightScale = Math.max(1, Number((printConfig && printConfig.lineHeightScale) || fontScale));
    const fontFamily = String((printConfig && printConfig.fontFamily) || "Arial, sans-serif");
    const offsetX = Number((printConfig && printConfig.offsetX) || 0);
    const offsetY = Number((printConfig && printConfig.offsetY) || 0);

    if (!text) {
      return "";
    }

    const fontSize = Number(config.fontSize || 18) * fontScale;
    const lineHeightBase = Number(config.lineHeight || ((config.fontSize || 18) + 8));
    const lineHeight = lineHeightBase * lineHeightScale;
    const styles = [
      "left:" + porcentajePlantilla((config.x || 0) + offsetX, canvas.width),
      "top:" + porcentajePlantilla((config.y || 0) + offsetY, canvas.height),
      "width:" + porcentajePlantilla(config.width || 0, canvas.width),
      "font-size:" + pxPlantillaAPt(fontSize) + "pt",
      "line-height:" + pxPlantillaAPt(lineHeight) + "pt",
      "text-align:" + (config.align || "left"),
      "font-weight:" + (config.bold ? "700" : "400"),
      "color:" + (config.color || "#111111"),
      "white-space:" + (config.multiline ? "pre-line" : "nowrap"),
      "font-family:" + fontFamily
    ];

    if (!config.multiline) {
      styles.push("overflow:hidden");
      styles.push("text-overflow:ellipsis");
    }

    return '<div class="print-field" data-field="' + escapeHtml(fieldName) + '" style="' + styles.join(";") + '">' + escapeHtml(text).replace(/\n/g, "<br>") + "</div>";
  }

  function construirHojaImpresionRecibo(item) {
    const impresion = item && item.impresion ? item.impresion : null;

    if (!impresion || !impresion.canvas || !impresion.fields || !impresion.values) {
      return "";
    }

    const canvas = impresion.canvas;
    const fields = impresion.fields || {};
    const values = impresion.values || {};
    const qr = impresion.qr || {};
    const qrToken = impresion.qr_token || "";
    const printConfig = impresion.print || {};
    const offsetX = Number(printConfig.offsetX || 0);
    const offsetY = Number(printConfig.offsetY || 0);
    const htmlCampos = Object.keys(fields).map(function (fieldName) {
      return construirCampoImpresionHtml(canvas, fieldName, fields[fieldName] || {}, values[fieldName] || "", printConfig);
    }).join("");
    const htmlQr = qrToken && Number(qr.size || 0) > 0
      ? '<div class="print-qr" style="left:' + porcentajePlantilla((qr.x || 0) + offsetX, canvas.width) + ";top:" + porcentajePlantilla((qr.y || 0) + offsetY, canvas.height) + ";width:" + porcentajePlantilla(qr.size || 0, canvas.width) + ";height:" + porcentajePlantilla(qr.size || 0, canvas.height) + ';">' +
        '<img src="' + escapeHtml(qrPublicUrl(qrToken, qr.size, false)) + '" alt="QR del recibo" onerror="this.onerror=null;this.src=\'' + escapeHtml(qrPublicUrl(qrToken, qr.size, true)) + '\'">' +
        "</div>"
      : "";

    return [
      '<div class="print-sheet">',
      '<div class="print-blank-receipt">',
      htmlCampos,
      htmlQr,
      '</div>',
      '</div>'
    ].join("");
  }

  function imprimirListaRecibos(items, titulo) {
    if (!items || !items.length) {
      showLecturasFeedback("warning", "No hay recibos listos para imprimir.");
      return;
    }

    const ventana = window.open("", "_blank");

    if (!ventana) {
      showLecturasFeedback("warning", "El navegador bloqueo la ventana de impresion. Permite las ventanas emergentes e intenta de nuevo.");
      return;
    }

    const htmlItems = items.map(construirHojaImpresionRecibo).join("");

    if (!htmlItems) {
      ventana.close();
      showLecturasFeedback("warning", "No se encontraron datos de impresion para los recibos seleccionados.");
      return;
    }

    ventana.document.open();
    ventana.document.write([
      '<!DOCTYPE html>',
      '<html lang="es"><head><meta charset="utf-8"><title>' + escapeHtml(titulo || "Impresion de recibos") + '</title>',
      '<style>',
      '@page { size: 5.5in 8.5in; margin: 0; }',
      'html,body{margin:0;padding:0;background:#fff;height:auto;overflow:visible;}',
        'body{font-family:Arial,sans-serif;}',
        '.print-sheet{width:5.5in;height:8.5in;page-break-after:always;break-after:page;page-break-inside:avoid;break-inside:avoid;overflow:hidden;}',
        '.print-sheet:last-child{page-break-after:auto;break-after:auto;}',
        '.print-blank-receipt{position:relative;width:100%;height:100%;background:#fff;overflow:hidden;}',
        '.print-field{position:absolute;box-sizing:border-box;font-family:Arial,sans-serif;}',
        '.print-qr{position:absolute;box-sizing:border-box;}',
      '.print-qr img{display:block;width:100%;height:100%;object-fit:contain;}',
      '</style>',
      '</head><body>',
      htmlItems,
      '<script>window.onload=function(){setTimeout(function(){window.print();},350);};<\/script>',
      '</body></html>'
    ].join(""));
    ventana.document.close();
  }

  function obtenerConfiguracionReciboActual() {
    return {
      fecha_limite_pago: $("#reciboFechaLimite").val(),
      cooperaciones: $("#reciboCooperaciones").val(),
      multas: $("#reciboMultas").val(),
      recargos: $("#reciboRecargos").val(),
      metodo_pago_caja: $("#reciboMetodoPago").val(),
      referencia_pago: $("#reciboReferenciaPago").val()
    };
  }

  function solicitarImpresionRecibo(lecturaId, options) {
    const settings = options || {};

    if (!lecturaId) {
      showLecturasFeedback("warning", "No se encontro la lectura para preparar la impresion.");
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $.extend({
        accion: "recibos.prepararImpresion",
        lectura_id: lecturaId
      }, settings.formData || {}),
      beforeSend: function () {
        if (settings.feedbackMode === "modal") {
          showModalReciboFeedback("info", "Preparando impresion del recibo...");
          return;
        }
        if (settings.feedbackMode === "preview") {
          showPreviewRecibosPeriodoFeedback("info", "Preparando impresion del recibo...");
          return;
        }
        showLecturasFeedback("info", "Preparando impresion del recibo...");
      },
      success: function (response) {
        const data = response.data || {};
        if (lecturaReciboActual && Number(lecturaReciboActual.lectura_id || 0) === Number(data.lectura_id || 0)) {
          lecturaReciboActual.impresion = data.impresion || null;
        }
        imprimirListaRecibos([data], settings.title || "Impresion de recibo");
      },
      error: function (xhr) {
        const message = extractAjaxMessage(xhr, "No se pudo preparar la impresion del recibo.");
        if (settings.feedbackMode === "modal") {
          showModalReciboFeedback("danger", message);
          return;
        }
        if (settings.feedbackMode === "preview") {
          showPreviewRecibosPeriodoFeedback("danger", message);
          return;
        }
        showLecturasFeedback("danger", message);
      }
    });
  }

  function abrirVistaRecibo(lecturaId) {
    if (!lecturaId) {
      showLecturasFeedback("warning", "No se encontro la lectura para abrir el recibo.");
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "recibos.obtenerImagen",
        lectura_id: lecturaId
      },
      beforeSend: function () {
        showLecturasFeedback("info", "Localizando imagen del recibo...");
      },
      success: function (response) {
        const data = response.data || {};
        const imageUrl = data.imagen_path ? rutaAbsolutaArchivo(data.imagen_path) + "?t=" + Date.now() : "";

        if (!imageUrl) {
          showLecturasFeedback("warning", "No se encontro la imagen del recibo.");
          return;
        }

        const ventana = window.open(imageUrl, "_blank");
        if (!ventana) {
          showLecturasFeedback("warning", "El navegador bloqueo la ventana para abrir el recibo.");
          return;
        }

        showLecturasFeedback("success", response.message || "Recibo abierto correctamente.");
      },
      error: function (xhr) {
        showLecturasFeedback("danger", extractAjaxMessage(xhr, "No se pudo abrir la imagen del recibo."));
      }
    });
  }

  function obtenerConfiguracionPreviewRecibos() {
    return {
      accion: "recibos.previsualizarPeriodo",
      periodo_id: $("#previewPeriodoId").val(),
      fecha_limite_pago: $("#previewReciboFechaLimite").val(),
      cooperaciones: $("#previewReciboCooperaciones").val(),
      multas: $("#previewReciboMultas").val(),
      recargos: $("#previewReciboRecargos").val(),
      metodo_pago_caja: $("#previewReciboMetodoPago").val(),
      referencia_pago: $("#previewReciboReferenciaPago").val()
    };
  }

  function sincronizarConfiguracionPreviewRecibos() {
    $("#previewPeriodoId").val($("#filtroPeriodoLectura").val() || "");
    $("#previewReciboFechaLimite").val($("#reciboFechaLimite").val() || dateInputValue(addDays(new Date(), 7)));
    $("#previewReciboCooperaciones").val($("#reciboCooperaciones").val() || "0.00");
    $("#previewReciboMultas").val($("#reciboMultas").val() || "0.00");
    $("#previewReciboRecargos").val($("#reciboRecargos").val() || "0.00");
    $("#previewReciboMetodoPago").val($("#reciboMetodoPago").val() || "Caja de cobro del sistema de agua");
    $("#previewReciboReferenciaPago").val($("#reciboReferenciaPago").val() || "Presentar este recibo al realizar el pago");
  }

  function renderPreviewRecibosPeriodo(items) {
    const $tbody = $("#tablaPreviewRecibosPeriodo tbody");

    if (!items.length) {
      $tbody.html('<tr><td colspan="4" class="text-center text-muted py-4">Aun no hay recibos preparados para imprimir.</td></tr>');
      $("#btnImprimirTodosRecibosPeriodo").prop("disabled", true);
      return;
    }

    const rows = items.map(function (item, index) {
      const imageUrl = item.imagen_path ? rutaAbsolutaArchivo(item.imagen_path) + "?t=" + Date.now() : "";
      const preview = imageUrl
        ? '<img src="' + escapeHtml(imageUrl) + '" alt="Recibo ' + escapeHtml(item.folio || "") + '" style="max-width:120px;width:100%;height:auto;border:1px solid #d6e2f5;border-radius:8px;">'
        : '<span class="text-muted">Sin imagen</span>';

      return [
        '<tr>',
        '<td><strong>' + escapeHtml(item.usuario || "Sin usuario") + '</strong><br><small class="text-muted">' + escapeHtml(item.medidor || "Sin medidor") + ' | ' + escapeHtml(item.ruta || "Sin ruta") + '</small></td>',
        '<td><strong>' + escapeHtml(item.folio || "Sin folio") + '</strong><br><small class="text-muted">' + escapeHtml(item.periodo || "Sin periodo") + '</small><br><small class="text-muted">Total ' + money(item.total || 0) + '</small></td>',
        '<td>' + preview + '</td>',
        '<td class="text-right"><div class="table-actions justify-content-end"><a class="btn btn-sm btn-outline-info" target="_blank" href="' + escapeHtml(imageUrl || "#") + '"><i class="fas fa-image mr-1"></i> Abrir</a><button type="button" class="btn btn-sm btn-outline-primary btn-imprimir-preview-recibo" data-index="' + index + '" data-lectura-id="' + item.lectura_id + '"><i class="fas fa-print mr-1"></i> Imprimir</button></div></td>',
        '</tr>'
      ].join("");
    }).join("");

    $tbody.html(rows);
    $("#btnImprimirTodosRecibosPeriodo").prop("disabled", false);
  }

  function reiniciarPreviewRecibosPeriodo() {
    colaPreviewRecibosPeriodo = [];
    $("#previewRecibosPeriodoFeedback").addClass("d-none");
    $("#previewRecibosPeriodoEstado").text("Prepara la vista previa para generar o actualizar los recibos del periodo seleccionado.");
    $("#previewRecibosPeriodoResumen").html('<div class="text-muted">Selecciona un periodo en el filtro superior para preparar la impresion masiva.</div>');
    renderPreviewRecibosPeriodo([]);
  }

  function abrirPreviewRecibosPeriodo() {
    aplicarTarifaCobroAguaEnUI();
    sincronizarConfiguracionPreviewRecibos();
    cargarCatalogoPeriodos($("#previewPeriodoId"), $("#filtroPeriodoLectura").val() || $("#previewPeriodoId").val());
    reiniciarPreviewRecibosPeriodo();

    const periodoTexto = $("#filtroPeriodoLectura option:selected").text() || "Sin periodo";
    const periodoId = $("#filtroPeriodoLectura").val() || "";

    if (periodoId) {
      $("#previewRecibosPeriodoResumen").html([
        '<div class="recibo-summary-title">Impresion masiva de recibos</div>',
        '<div class="recibo-summary-grid">',
        '<span>Periodo seleccionado <strong>' + escapeHtml(periodoTexto) + '</strong></span>',
        '<span>Modo <strong>Vista previa + impresion</strong></span>',
        '</div>'
      ].join(""));
    }

    $("#modalPreviewRecibosPeriodo").modal("show");
  }

  function prepararPreviewRecibosPeriodo() {
    const periodoId = $("#previewPeriodoId").val();
    const periodoTexto = $("#previewPeriodoId option:selected").text() || "Sin periodo";

    if (!periodoId) {
      showPreviewRecibosPeriodoFeedback("warning", "Selecciona un periodo para preparar la impresion.");
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: obtenerConfiguracionPreviewRecibos(),
      beforeSend: function () {
        showPreviewRecibosPeriodoFeedback("info", "Preparando vista previa del periodo seleccionado...");
        $("#previewRecibosPeriodoEstado").text("Generando y actualizando los recibos del periodo " + periodoTexto + "...");
        $("#tablaPreviewRecibosPeriodo tbody").html('<tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Preparando recibos...</td></tr>');
        $("#btnPrepararPreviewRecibosPeriodo").prop("disabled", true);
        $("#btnImprimirTodosRecibosPeriodo").prop("disabled", true);
      },
      success: function (response) {
        const data = response.data || {};
        colaPreviewRecibosPeriodo = data.recibos || [];
        renderPreviewRecibosPeriodo(colaPreviewRecibosPeriodo);
        $("#previewRecibosPeriodoResumen").html([
          '<div class="recibo-summary-title">Periodo ' + escapeHtml(data.periodo || periodoTexto) + '</div>',
          '<div class="recibo-summary-grid">',
          '<span>Total listos <strong>' + numberFormat(data.total || 0) + '</strong></span>',
          '<span>Nuevos <strong>' + numberFormat(data.insertados || 0) + '</strong></span>',
          '<span>Actualizados <strong>' + numberFormat(data.actualizados || 0) + '</strong></span>',
          '<span>Omitidos <strong>' + numberFormat(data.omitidos || 0) + '</strong></span>',
          '</div>'
        ].join(""));
        $("#previewRecibosPeriodoEstado").text("Vista previa lista. Puedes imprimir todo el lote o sacar solo los recibos que falten.");

        if (Number(data.omitidos || 0) > 0) {
          showPreviewRecibosPeriodoFeedback("warning", "Se prepararon " + numberFormat(data.total || 0) + " recibos y se omitieron " + numberFormat(data.omitidos || 0) + " registros. Revisa los faltantes antes de imprimir.");
        } else {
          showPreviewRecibosPeriodoFeedback("success", response.message || "Vista previa preparada correctamente.");
        }

        cargarLecturas();
      },
      error: function (xhr) {
        colaPreviewRecibosPeriodo = [];
        renderPreviewRecibosPeriodo([]);
        $("#previewRecibosPeriodoEstado").text("No fue posible preparar la vista previa del periodo seleccionado.");
        showPreviewRecibosPeriodoFeedback("danger", extractAjaxMessage(xhr, "No se pudo preparar la vista previa de recibos."));
      },
      complete: function () {
        $("#btnPrepararPreviewRecibosPeriodo").prop("disabled", false);
      }
    });
  }

  function llenarModalRecibo(lectura) {
    const cobroConfig = obtenerConfiguracionCobroAgua();
    lecturaReciboActual = lectura;
    $("#formGenerarRecibo")[0].reset();
    $("#modalReciboFeedback").addClass("d-none");
    $("#btnAbrirReciboGenerado").addClass("d-none").attr("href", "#");
    $("#btnImprimirReciboActual").addClass("d-none").prop("disabled", true).removeAttr("data-lectura-id data-folio data-usuario");
    $("#btnEnviarReciboWhatsapp").prop("disabled", true).removeAttr("data-recibo-id").attr("title", "Primero genera la imagen del recibo");
    $("#reciboLecturaId").val(lectura.lectura_id);
    $("#reciboCooperaciones").val(numberFormat(Number(lectura.cooperaciones || cobroConfig.cooperacion_default || 0), 2));
    $("#reciboMultas").val(numberFormat(Number(lectura.multas || cobroConfig.multa_default || 0), 2));
    $("#reciboRecargos").val(numberFormat(Number(lectura.recargos || cobroConfig.recargo_default || 0), 2));
    $("#reciboMetodoPago").val("Caja de cobro del sistema de agua");
    $("#reciboReferenciaPago").val("Presentar este recibo al realizar el pago");
    $("#reciboFechaLimite").val(lectura.fecha_vencimiento || dateInputValue(addDays(new Date(), 7)));
    aplicarTarifaCobroAguaEnUI();

    $("#reciboResumenLectura").html([
      '<div class="recibo-summary-title">' + escapeHtml(lectura.usuario) + '</div>',
      '<div class="recibo-summary-grid">',
      '<span>Medidor <strong>' + escapeHtml(lectura.medidor || "Sin medidor") + '</strong></span>',
      '<span>Ruta <strong>' + escapeHtml(lectura.ruta || "Sin ruta") + '</strong></span>',
      '<span>Periodo <strong>' + escapeHtml(lectura.periodo || "Sin periodo") + '</strong></span>',
      '<span>Consumo <strong>' + numberFormat(lectura.consumo_m3) + ' m3</strong></span>',
      '</div>'
    ].join(""));

      if (lectura.imagen_path) {
        const imageUrl = rutaAbsolutaArchivo(lectura.imagen_path) + "?t=" + Date.now();
        $("#reciboPreviewBox").html('<img src="' + escapeHtml(imageUrl) + '" alt="Recibo generado">');
        $("#btnAbrirReciboGenerado").removeClass("d-none").attr("href", imageUrl);
      } else {
        $("#reciboPreviewBox").html('<div class="recibo-preview-empty"><i class="fas fa-file-image"></i><span>La imagen del recibo aparecera aqui despues de generarlo.</span></div>');
      }

    calcularTotalRecibo();
    actualizarBotonEnvioRecibo();
    actualizarBotonImprimirReciboActual();
    $("#modalGenerarRecibo").modal("show");
  }

  function abrirGenerarRecibo(lecturaId) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "lecturas.obtener",
        lectura_id: lecturaId
      },
      beforeSend: function () {
        showLecturasFeedback("info", "Cargando lectura...");
      },
      success: function (response) {
        $("#lecturasFeedback").addClass("d-none");
        llenarModalRecibo(response.data || {});
      },
      error: function (xhr) {
        showLecturasFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar la lectura."));
      }
    });
  }

  $(".sync-summary").on("input change", function () {
    syncSummary($(this));
  });

  $(".text-uppercase-field").on("input", function () {
    const cursor = this.selectionStart;
    this.value = uppercaseValue(this.value);
    this.setSelectionRange(cursor, cursor);
  });

  $(".only-numbers").on("input", function () {
    this.value = cleanNumbers(this.value);
  });

  $(".route-code").on("input", function () {
    this.value = cleanCode(this.value, 25);
    syncSummary($(this));
  });

  $(".meter-code").on("input", function () {
    this.value = cleanCode(this.value, 60);
    syncSummary($(this));
    if (this.id === "medidor") {
      renderMedidorAltaInfo();
      renderListaMedidoresAlta();
    }
  });

  $("#medidor").on("change", function () {
    normalizarMedidorAlta();
  });

  $("#medidor").on("select2:open", function () {
    const $search = $(".select2-container--open .select2-search__field");

    $search.off("input.medidorAlta").on("input.medidorAlta", function () {
      const cursor = this.selectionStart;
      const cleanValue = cleanCode(this.value, 60);

      if (this.value !== cleanValue) {
        this.value = cleanValue;
        if (typeof cursor === "number" && this.setSelectionRange) {
          this.setSelectionRange(cleanValue.length, cleanValue.length);
        }
      }
    });
  });

  $("#searchDireccionMapa, #editSearchDireccionMapa").on("keydown", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      geocodeSearchInput(this.id === "editSearchDireccionMapa" ? "edit" : "alta");
    }
  });

  $("#searchDireccionMapa, #editSearchDireccionMapa").on("blur", function () {
    geocodeSearchInput(this.id === "editSearchDireccionMapa" ? "edit" : "alta");
  });

  $(".coordinate-field").on("input", function () {
    this.value = cleanCoordinate(this.value);
  });

  $("#latitud, #longitud").on("change blur", function () {
    syncMapFromFields("alta");
    if ($.trim($("#latitud").val()) || $.trim($("#longitud").val())) {
      if ($("#modoUbicacion").val() !== "aproximada") {
        setLocationMode(mapsConfig.alta, "manual");
      } else {
        renderLocationModeState(mapsConfig.alta);
      }
    }
  });

  $("#editLatitud, #editLongitud").on("change blur", function () {
    syncMapFromFields("edit");
    if ($.trim($("#editLatitud").val()) || $.trim($("#editLongitud").val())) {
      if ($("#editModoUbicacion").val() !== "aproximada") {
        setLocationMode(mapsConfig.edit, "manual");
      } else {
        renderLocationModeState(mapsConfig.edit);
      }
    }
  });

  $("#modoUbicacion").on("change", function () {
    if ($(this).val() !== "google_maps") {
      $("#googlePlaceId").val("");
    }
    renderLocationModeState(mapsConfig.alta);
  });

  $("#editModoUbicacion").on("change", function () {
    if ($(this).val() !== "google_maps") {
      $("#editGooglePlaceId").val("");
    }
    renderLocationModeState(mapsConfig.edit);
  });

  $(".app-menu-link").on("click", function (event) {
    if ($(this).data("navigation") === "page") {
      return;
    }
    event.preventDefault();
    switchView($(this).data("view"), $(this).attr("id"));
  });

  $("#btnConsultarUsuarios, #btnRecargarUsuarios").on("click", function () {
    usuariosPaginaActual = 1;
    cargarUsuarios(1);
  });

  $("#usuariosPorPagina").on("change", function () {
    usuariosPorPaginaActual = Number($(this).val() || 25);
    usuariosPaginaActual = 1;
    cargarUsuarios(1);
  });

  $("#btnUsuariosAnterior").on("click", function () {
    if (usuariosPaginaActual > 1) {
      cargarUsuarios(usuariosPaginaActual - 1);
    }
  });

  $("#btnUsuariosSiguiente").on("click", function () {
    if (usuariosPaginaActual < usuariosTotalPaginas) {
      cargarUsuarios(usuariosPaginaActual + 1);
    }
  });

  $("#comunidad").on("change", function () {
    cargarComboRutas($("#ruta"), comunidadIdPorNombre($(this).val()));
  });

  $("#editComunidad").on("change", function () {
    cargarComboRutas($("#editRuta"), comunidadIdPorNombre($(this).val()));
  });

  $(document).on("click", "#btnRecargarMedidores", function () {
    $("#buscarMedidorListado").val("");
    $("#medidoresCampoBusqueda").val("todos");
    medidoresPaginaActual = 1;
    cargarMedidores(1);
  });

  $(document).on("click", "#btnBuscarMedidores", function () {
    medidoresPaginaActual = 1;
    cargarMedidores(1);
  });

  $(document).on("click", "#btnLimpiarBusquedaMedidores", function () {
    $("#buscarMedidorListado").val("");
    $("#medidoresCampoBusqueda").val("todos");
    medidoresPaginaActual = 1;
    cargarMedidores(1);
  });

  $(document).on("keydown", "#buscarMedidorListado", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      medidoresPaginaActual = 1;
      cargarMedidores(1);
    }
  });

  $("#medidoresPorPagina").on("change", function () {
    medidoresPorPaginaActual = Number($(this).val() || 25);
    medidoresPaginaActual = 1;
    cargarMedidores(1);
  });

  $("#btnMedidoresAnterior").on("click", function () {
    if (medidoresPaginaActual > 1) {
      cargarMedidores(medidoresPaginaActual - 1);
    }
  });

  $("#btnMedidoresSiguiente").on("click", function () {
    if (medidoresPaginaActual < medidoresTotalPaginas) {
      cargarMedidores(medidoresPaginaActual + 1);
    }
  });

  $("#btnRecargarRutas").on("click", function () {
    $("#buscarRutaListado").val("");
    $("#rutasCampoBusqueda").val("todos");
    rutasPaginaActual = 1;
    cargarRutas(1);
  });

  $("#btnBuscarRutas").on("click", function () {
    rutasPaginaActual = 1;
    cargarRutas(1);
  });

  $("#btnLimpiarBusquedaRutas").on("click", function () {
    $("#buscarRutaListado").val("");
    $("#rutasCampoBusqueda").val("todos");
    rutasPaginaActual = 1;
    cargarRutas(1);
  });

  $(document).on("keydown", "#buscarRutaListado", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      rutasPaginaActual = 1;
      cargarRutas(1);
    }
  });

  $("#rutasPorPagina").on("change", function () {
    rutasPorPaginaActual = Number($(this).val() || 25);
    rutasPaginaActual = 1;
    cargarRutas(1);
  });

  $("#btnRutasAnterior").on("click", function () {
    if (rutasPaginaActual > 1) {
      cargarRutas(rutasPaginaActual - 1);
    }
  });

  $("#btnRutasSiguiente").on("click", function () {
    if (rutasPaginaActual < rutasTotalPaginas) {
      cargarRutas(rutasPaginaActual + 1);
    }
  });

  $("#btnRecargarPeriodos").on("click", function () {
    cargarPeriodos();
  });

  $("#periodosPorPagina").on("change", function () {
    periodosPorPaginaActual = Number($(this).val() || 25);
    periodosPaginaActual = 1;
    cargarPeriodos(1);
  });

  $("#btnPeriodosAnterior").on("click", function () {
    if (periodosPaginaActual > 1) {
      cargarPeriodos(periodosPaginaActual - 1);
    }
  });

  $("#btnPeriodosSiguiente").on("click", function () {
    if (periodosPaginaActual < periodosTotalPaginas) {
      cargarPeriodos(periodosPaginaActual + 1);
    }
  });

  $("#btnRecargarLecturas").on("click", function () {
    lecturasPaginaActual = 1;
    cargarLecturas(1);
  });

  $("#btnAbrirPreviewRecibosPeriodo").on("click", function () {
    abrirPreviewRecibosPeriodo();
  });

  $("#btnPrepararPreviewRecibosPeriodo").on("click", function () {
    prepararPreviewRecibosPeriodo();
  });

  $("#btnImprimirTodosRecibosPeriodo").on("click", function () {
    if (!colaPreviewRecibosPeriodo.length) {
      showPreviewRecibosPeriodoFeedback("warning", "Primero prepara la vista previa del periodo antes de imprimir.");
      return;
    }

    imprimirListaRecibos(
      colaPreviewRecibosPeriodo,
      "Impresion masiva de recibos - " + ($("#previewPeriodoId option:selected").text() || "Periodo")
    );
  });

  $("#filtroEstadoRecibo").on("change", function () {
    lecturasPaginaActual = 1;
    cargarLecturas(1);
  });

  $("#filtroPeriodoLectura").on("change", function () {
    lecturasPaginaActual = 1;
    cargarLecturas(1);
  });

  $("#lecturasPorPagina").on("change", function () {
    lecturasPorPaginaActual = Number($(this).val() || 25);
    lecturasPaginaActual = 1;
    cargarLecturas(1);
  });

  $("#btnLecturasAnterior").on("click", function () {
    if (lecturasPaginaActual > 1) {
      cargarLecturas(lecturasPaginaActual - 1);
    }
  });

  $("#btnLecturasSiguiente").on("click", function () {
    if (lecturasPaginaActual < lecturasTotalPaginas) {
      cargarLecturas(lecturasPaginaActual + 1);
    }
  });

  $("#btnBuscarUsuarioPago, #btnRecargarPagos").on("click", function () {
    pagosPaginaActual = 1;
    cargarUsuariosPago($("#buscarPagoUsuario").val(), $("#selectUsuarioPago").val());
    cargarRecibosPago(1);
  });

  $("#pagosPorPagina").on("change", function () {
    pagosPorPaginaActual = Number($(this).val() || 25);
    pagosPaginaActual = 1;
    cargarRecibosPago(1);
  });

  $("#btnPagosAnterior").on("click", function () {
    if (pagosPaginaActual > 1) {
      cargarRecibosPago(pagosPaginaActual - 1);
    }
  });

  $("#btnPagosSiguiente").on("click", function () {
    if (pagosPaginaActual < pagosTotalPaginas) {
      cargarRecibosPago(pagosPaginaActual + 1);
    }
  });

  $("#btnRefreshWhatsapp, #btnRefreshWhatsappMessages").on("click", function () {
    cargarPanelWhatsapp();
  });

  $("#btnPrepararNotificacionMasiva").on("click", function () {
    prepararNotificacionesMasivas();
  });

  $("#modalNotificacionMasiva").on("show.bs.modal", function () {
    sincronizarTipoNotificacionMasiva();
    cargarFiltroPeriodoNotificacion();
  });

  $("#tipoNotificacionMasiva").on("change", function () {
    sincronizarTipoNotificacionMasiva();
  });

  $("#btnIniciarNotificacionMasiva").on("click", function () {
    const enviables = totalNotificacionesEnviables();

    if (!colaNotificacionesMasivas.length) {
      showNotificacionesMasivasFeedback("warning", "Primero prepara una lista de notificaciones.");
      return;
    }

    if (enviables <= 0) {
      showNotificacionesMasivasFeedback("warning", "La lista actual no tiene registros listos para envio por WhatsApp.");
      return;
    }

    if (!confirm("Se enviaran " + colaNotificacionesMasivas.length + " mensajes por WhatsApp con una pausa de " + Math.max(5, Number($("#pausaNotificacionMasiva").val() || 8)) + " segundos entre cada uno. ¿Deseas continuar?")) {
      return;
    }

    envioMasivoActivo = true;
    indiceNotificacionMasiva = 0;
    colaNotificacionesMasivas = colaNotificacionesMasivas.map(function (item) {
      item.resultado = item.puede_enviar ? "pendiente" : "bloqueado";
      item.detalle = item.detalle_preparacion || (item.puede_enviar ? "" : "No se puede enviar.");
      return item;
    });
    renderNotificacionesMasivas(colaNotificacionesMasivas);
    actualizarBotonesEnvioMasivo();
    showNotificacionesMasivasFeedback("info", "Iniciando envio masivo con pausa controlada...");
    procesarSiguienteNotificacionMasiva();
  });

  $("#btnDetenerNotificacionMasiva").on("click", function () {
    detenerNotificacionesMasivas("El envio masivo fue detenido por el usuario.");
    showNotificacionesMasivasFeedback("warning", "Envio masivo detenido.");
  });

  $("#whatsappMessageStatus").on("change", function () {
    cargarPanelWhatsapp();
  });

  $("#selectUsuarioPago").on("change", function () {
    pagosPaginaActual = 1;
    cargarRecibosPago(1);
  });

  $("#btnAgregarMedidor").on("click", function () {
    abrirAgregarMedidor();
  });

  $("#btnAgregarRuta").on("click", function () {
    abrirAgregarRuta();
  });

  $("#btnRutaNuevaAlta").on("click", function () {
    abrirAgregarRuta(comunidadIdPorNombre($("#comunidad").val()));
  });

  $("#btnRutaNuevaEditar").on("click", function () {
    abrirAgregarRuta(comunidadIdPorNombre($("#editComunidad").val()));
  });

  $("#btnRutaEditarAlta").on("click", function () {
    const rutaId = $("#ruta").val();

    if (!rutaId) {
      showFeedback("warning", "Selecciona una ruta para editarla o crea una nueva.");
      return;
    }

    abrirEditarRuta(rutaId);
  });

  $("#btnRutaEditarUsuario").on("click", function () {
    const rutaId = $("#editRuta").val();

    if (!rutaId) {
      showFeedback("warning", "Selecciona una ruta para editarla o crea una nueva.");
      return;
    }

    abrirEditarRuta(rutaId);
  });

  $("#sidebarCurtainToggle").on("click", function () {
    $("body").toggleClass("sidebar-overlay-open");
    actualizarEstadoCortinillaSidebar();
  });

  $("#sidebarDrawerBackdrop").on("click", function () {
    $("body").removeClass("sidebar-overlay-open");
    actualizarEstadoCortinillaSidebar();
  });

  $(".app-menu-link").on("click", function () {
    if (window.innerWidth < 992) {
      $("body").removeClass("sidebar-overlay-open");
      actualizarEstadoCortinillaSidebar();
    }
  });

  $("#btnAgregarPeriodo").on("click", function () {
    abrirAgregarPeriodo();
  });

  $(document).on("click", ".btn-editar-medidor", function () {
    abrirEditarMedidor($(this).data("id"));
  });

  $(document).on("click", ".btn-editar-ruta", function () {
    abrirEditarRuta($(this).data("id"));
  });

  $(document).on("click", ".btn-baja-ruta-tabla", function () {
    bajaRuta($(this).data("id"), "tabla");
  });

  $(document).on("click", ".btn-editar-periodo", function () {
    abrirEditarPeriodo($(this).data("id"));
  });

  $(document).on("click", ".btn-baja-periodo-tabla", function () {
    bajaPeriodo($(this).data("id"), "tabla");
  });

  $(document).on("click", ".btn-generar-recibo", function () {
    abrirGenerarRecibo($(this).data("id"));
  });

  $(document).on("click", ".btn-ver-recibo", function () {
    abrirVistaRecibo($(this).data("lectura-id"));
  });

  $(document).on("click", ".btn-imprimir-recibo", function () {
    solicitarImpresionRecibo($(this).data("lectura-id"), {
      title: "Impresion de recibo"
    });
  });

  $(document).on("click", ".btn-imprimir-preview-recibo", function () {
    const item = colaPreviewRecibosPeriodo[Number($(this).data("index") || -1)] || null;

    if (item && item.impresion) {
      imprimirListaRecibos([item], "Impresion de recibo");
      return;
    }

    solicitarImpresionRecibo($(this).data("lectura-id"), {
      title: "Impresion de recibo",
      feedbackMode: "preview"
    });
  });

  $("#btnImprimirReciboActual").on("click", function () {
    if (lecturaReciboActual && lecturaReciboActual.impresion) {
      imprimirListaRecibos([lecturaReciboActual], "Impresion de recibo");
      return;
    }

    solicitarImpresionRecibo($(this).attr("data-lectura-id"), {
      title: "Impresion de recibo",
      feedbackMode: "modal",
      formData: obtenerConfiguracionReciboActual()
    });
  });

  $("#btnEnviarReciboWhatsapp").on("click", function () {
    const reciboId = $(this).attr("data-recibo-id");
    const whatsapp = lecturaReciboActual ? String(lecturaReciboActual.whatsapp || "") : "";

    if (!reciboId) {
      showModalReciboFeedback("warning", "Primero genera el recibo antes de enviarlo por WhatsApp.");
      return;
    }

    if (!whatsapp) {
      showModalReciboFeedback("warning", "El usuario no tiene WhatsApp registrado.");
      return;
    }

    if (!confirm("Se enviara la imagen del recibo al WhatsApp registrado: " + whatsapp + ". ¿Deseas continuar?")) {
      return;
    }

    const $button = $(this);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "recibos.enviarWhatsApp",
        recibo_id: reciboId
      },
      beforeSend: function () {
        showModalReciboFeedback("info", "Enviando recibo por WhatsApp...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Enviando');
      },
      success: function (response) {
        const data = response.data || {};
        showModalReciboFeedback("success", "Recibo " + (data.folio || "") + " enviado por WhatsApp a " + (data.whatsapp || whatsapp) + ".");
        cargarPanelWhatsapp();
      },
      error: function (xhr) {
        showModalReciboFeedback("danger", extractAjaxMessage(xhr, "No se pudo enviar el recibo por WhatsApp."));
      },
      complete: function () {
        $button.prop("disabled", false).html('<i class="fab fa-whatsapp mr-1"></i> Enviar por WhatsApp');
        actualizarBotonEnvioRecibo();
      }
    });
  });

  $(document).on("click", ".btn-registrar-pago", function () {
    abrirRegistrarPago($(this).data("id"));
  });

  $("#buscarLectura").on("keyup", function (event) {
    if (event.key === "Enter") {
      lecturasPaginaActual = 1;
      cargarLecturas(1);
    }
  });

  $("#buscarPagoUsuario").on("keyup", function (event) {
    if (event.key === "Enter") {
      pagosPaginaActual = 1;
      cargarUsuariosPago($(this).val(), $("#selectUsuarioPago").val());
      cargarRecibosPago(1);
    }
  });

  $(".recibo-money-field").on("input", calcularTotalRecibo);

  $("#buscarNombreConsulta").on("keyup", function (event) {
    if (event.key === "Enter") {
      usuariosPaginaActual = 1;
      cargarUsuarios(1);
    }
  });

  $(document).on("click", ".btn-editar-usuario", function () {
    abrirEditarUsuario($(this).data("id"));
  });

  $(document).on("click", ".btn-baja-tabla", function () {
    bajaUsuario($(this).data("id"), $(this).data("source") || "tabla");
  });

  function buildDuplicateSearchTerm() {
    const manualSearch = $.trim($("#buscarDuplicado").val());

    if (manualSearch) {
      return manualSearch;
    }

    const medidor = $.trim($("#medidor").val());
    if (medidor) {
      return medidor;
    }

    const nombre = $.trim($("#nombre").val());
    if (nombre) {
      return nombre;
    }

    const whatsapp = $.trim($("#whatsapp").val());
    if (whatsapp) {
      return whatsapp;
    }

    const rutaSeleccionada = $.trim($("#ruta option:selected").data("codigo") || $("#ruta option:selected").text() || "");
    if (rutaSeleccionada) {
      return rutaSeleccionada;
    }

    return "";
  }

  function revisarDuplicadosAlta() {
    const search = buildDuplicateSearchTerm();
    const $result = $("#resultadoDuplicado");

    if (!search) {
      $result
        .removeClass("d-none warning")
        .html('<i class="fas fa-info-circle mr-2"></i><span>Ingresa un nombre, ruta, medidor o WhatsApp para revisar.</span>');
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $.param({
        accion: "usuarios.buscarDuplicados",
        termino: search
      }),
      beforeSend: function () {
        $result
          .removeClass("d-none warning")
          .html('<i class="fas fa-spinner fa-spin mr-2"></i><span>Buscando coincidencias...</span>');
      },
      success: function (response) {
        const data = response.data || {};
        const coincidencias = data.coincidencias || [];

        if (coincidencias.length) {
          const items = coincidencias.map(function (item) {
            return '<li class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-2">' +
              '<div class="pr-md-3"><strong>' + escapeHtml(item.nombre || "") + '</strong> | Ruta: ' + escapeHtml(item.ruta || 'Sin ruta') + ' | Medidor: ' + escapeHtml(item.medidor || 'Sin medidor') + ' | WhatsApp: ' + escapeHtml(item.whatsapp || 'Sin WhatsApp') + '</div>' +
              '<button type="button" class="btn btn-sm btn-outline-danger btn-baja-tabla mt-2 mt-md-0" data-id="' + escapeHtml(item.usuario_id) + '" data-source="duplicados"><i class="fas fa-user-slash mr-1"></i> Dar de baja</button>' +
              '</li>';
          }).join("");

          $result
            .removeClass("d-none")
            .addClass("warning")
            .html('<i class="fas fa-exclamation-triangle mr-2"></i><span>Se encontraron posibles duplicados:</span><ul class="mb-0 mt-2">' + items + '</ul>');
          return;
        }

        $result
          .removeClass("d-none warning")
          .html('<i class="fas fa-check-circle mr-2"></i><span>No se encontraron coincidencias exactas.</span>');
      },
      error: function (xhr) {
        $result
          .removeClass("d-none")
          .addClass("warning")
          .html('<i class="fas fa-exclamation-circle mr-2"></i><span>' + extractAjaxMessage(xhr, "No se pudo revisar duplicados.") + '</span>');
      }
    });
  }

  $(document).on("click", "#btnBuscar", function (event) {
    event.preventDefault();
    revisarDuplicadosAlta();
  });

  $(document).on("keydown", "#buscarDuplicado", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      revisarDuplicadosAlta();
    }
  });

  $("#btnLimpiarPanel").on("click", function () {
    $("#formAltaUsuario")[0].reset();
    $("#medidor").val("").trigger("change.select2");
    $("#buscarDuplicado").val("");
    $("#resultadoDuplicado").addClass("d-none");
    $("#statusFeedback").addClass("d-none");
    renderFieldErrors({});
    $("#googlePlaceId").val("");
    $("#searchDireccionMapa").val("");
    $("#modoUbicacion").val("manual");
    $("#referenciaUbicacion").val("");
    $("#summaryNombre").text("Sin nombre");
    $("#summaryRuta").text("Sin ruta");
    $("#summaryMedidor").text("Sin medidor");
    $("#summaryComunidad").text("Centro 1");
    $("#summaryEstado").text("Activo");
    $("#medidorAltaInfo").addClass("d-none").removeClass("is-warning is-ok").empty();
    $("#medidorAltaLista").addClass("d-none").empty();
    setMapPreviewCoords("", "");
    syncMapFromFields("alta");
    renderLocationModeState(mapsConfig.alta);
    $("#fachadaAltaPreview").html('<i class="fas fa-home mr-2"></i><span>Sin foto de fachada cargada.</span>');
    cargarComboRutas($("#ruta"), comunidadIdPorNombre($("#comunidad").val()));
  });

  $("#btnGuardarPanel").on("click", function () {
    $("#formAltaUsuario").trigger("submit");
  });

  $("#formAltaUsuario").on("submit", function (event) {
    event.preventDefault();

    const $form = $(this);
    const $buttons = $("#btnGuardarPanel");
    const clientErrors = validateClientFields();
    const formData = new FormData(this);

    if (Object.keys(clientErrors).length) {
      renderFieldErrors(clientErrors);
      showFeedback("warning", "Revisa los campos marcados antes de guardar.");
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: formData,
      processData: false,
      contentType: false,
      beforeSend: function () {
        renderFieldErrors({});
        showFeedback("info", "Guardando usuario en MySQL...");
        $buttons.prop("disabled", true);
        $("#btnGuardarPanel").html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando usuario');
      },
      success: function (response) {
        showFeedback("success", response.message || "Usuario guardado correctamente.");
        $("#modalGuardado .modal-title").html('<i class="fas fa-check-circle text-success mr-2"></i>Registro guardado');
        $("#modalGuardado .modal-body").text("Usuario ID: " + response.data.usuario_id + " | Medidor ID: " + response.data.medidor_id);
        $("#modalGuardado").modal("show");
        cargarUsuarios();
        cargarSugerenciasMedidoresAlta();
      },
      error: function (xhr) {
        const message = extractAjaxMessage(xhr, "No se pudo guardar el usuario.");
        const errors = xhr.responseJSON && xhr.responseJSON.errors ? xhr.responseJSON.errors : {};

        renderFieldErrors(errors);
        showFeedback("danger", message);
        $("#modalGuardado .modal-title").html('<i class="fas fa-exclamation-circle text-danger mr-2"></i>Error al guardar');
        $("#modalGuardado .modal-body").text(message);
        $("#modalGuardado").modal("show");
      },
      complete: function () {
        $buttons.prop("disabled", false);
        $("#btnGuardarPanel").html('<i class="fas fa-save mr-1"></i> Guardar usuario');
      }
    });
  });

  $("#formEditarUsuario").on("submit", function (event) {
    event.preventDefault();

    const formData = new FormData(this);
    const $button = $("#btnActualizarUsuario");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: formData,
      processData: false,
      contentType: false,
      beforeSend: function () {
        showModalFeedback("info", "Guardando cambios...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando');
      },
      success: function (response) {
        llenarModal(response.data || {});
        showModalFeedback("success", response.message || "Usuario actualizado correctamente.");
        cargarUsuarios();
      },
      error: function (xhr) {
        showModalFeedback("danger", extractAjaxMessage(xhr, "No se pudieron guardar los cambios."));
      },
      complete: function () {
        $button.prop("disabled", false).html('<i class="fas fa-save mr-1"></i> Guardar cambios');
      }
    });
  });

  $("#formMedidor").on("submit", function (event) {
    event.preventDefault();

    const $button = $("#btnGuardarMedidor");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $(this).serialize(),
      beforeSend: function () {
        showModalMedidorFeedback("info", "Guardando medidor...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando');
      },
      success: function (response) {
        showModalMedidorFeedback("success", response.message || "Medidor guardado correctamente.");
        cargarMedidores();
        cargarSugerenciasMedidoresAlta();
      },
      error: function (xhr) {
        showModalMedidorFeedback("danger", extractAjaxMessage(xhr, "No se pudo guardar el medidor."));
      },
      complete: function () {
        $button.prop("disabled", false);
        if ($("#medidorAccion").val() === "medidores.actualizar") {
          $button.html('<i class="fas fa-save mr-1"></i> Guardar cambios');
        } else {
          $button.html('<i class="fas fa-save mr-1"></i> Guardar medidor');
        }
      }
    });
  });

  $("#formRuta").on("submit", function (event) {
    event.preventDefault();

    const $button = $("#btnGuardarRuta");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $(this).serialize(),
      beforeSend: function () {
        showModalRutaFeedback("info", "Guardando ruta...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando');
      },
      success: function (response) {
        showModalRutaFeedback("success", response.message || "Ruta guardada correctamente.");
        cargarRutas();
        cargarComboRutas($("#ruta"), comunidadIdPorNombre($("#comunidad").val()), response.data && response.data.ruta_id ? response.data.ruta_id : "");
        cargarComboRutas($("#editRuta"), comunidadIdPorNombre($("#editComunidad").val()), response.data && response.data.ruta_id ? response.data.ruta_id : "");
        cargarUsuarios();
        cargarSugerenciasMedidoresAlta();
        restablecerBotonRuta();
        $("#modalRuta").modal("hide");
      },
      error: function (xhr) {
        showModalRutaFeedback("danger", extractAjaxMessage(xhr, "No se pudo guardar la ruta."));
      },
      complete: function () {
        restablecerBotonRuta();
      }
    });
  });

  $("#modalRuta").on("hidden.bs.modal", function () {
    restablecerBotonRuta();
  });

  $("#formPeriodo").on("submit", function (event) {
    event.preventDefault();

    const $button = $("#btnGuardarPeriodo");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $(this).serialize(),
      beforeSend: function () {
        showModalPeriodoFeedback("info", "Guardando periodo...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando');
      },
      success: function (response) {
        showModalPeriodoFeedback("success", response.message || "Periodo guardado correctamente.");
        cargarPeriodos();
      },
      error: function (xhr) {
        showModalPeriodoFeedback("danger", extractAjaxMessage(xhr, "No se pudo guardar el periodo."));
      },
      complete: function () {
        $button.prop("disabled", false);
        if ($("#periodoAccion").val() === "periodos.actualizar") {
          $button.html('<i class="fas fa-save mr-1"></i> Guardar cambios');
        } else {
          $button.html('<i class="fas fa-save mr-1"></i> Guardar periodo');
        }
      }
    });
  });

  $("#formGenerarRecibo").on("submit", function (event) {
    event.preventDefault();

    const $button = $("#btnGenerarRecibo");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $(this).serialize(),
      beforeSend: function () {
        showModalReciboFeedback("info", "Generando recibo con la plantilla...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generando');
      },
      success: function (response) {
        const data = response.data || {};
        const imageUrl = data.imagen_path + "?t=" + Date.now();
        if (lecturaReciboActual) {
          lecturaReciboActual.recibo_id = data.recibo_id;
          lecturaReciboActual.folio = data.folio;
          lecturaReciboActual.imagen_path = data.imagen_path;
          lecturaReciboActual.total = data.total;
          lecturaReciboActual.impresion = data.impresion || null;
        }
        showModalReciboFeedback("success", "Recibo " + data.folio + " generado por " + money(data.total) + ".");
        $("#reciboPreviewBox").html('<img src="' + escapeHtml(imageUrl) + '" alt="Recibo generado">');
        $("#btnAbrirReciboGenerado").removeClass("d-none").attr("href", imageUrl);
        actualizarBotonEnvioRecibo();
        actualizarBotonImprimirReciboActual();
        cargarLecturas();
      },
      error: function (xhr) {
        showModalReciboFeedback("danger", extractAjaxMessage(xhr, "No se pudo generar el recibo."));
      },
      complete: function () {
        $button.prop("disabled", false).html('<i class="fas fa-file-invoice-dollar mr-1"></i> Generar recibo');
      }
    });
  });

  $("#formRegistrarPago").on("submit", function (event) {
    event.preventDefault();

    const $button = $("#btnGuardarPago");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $(this).serialize(),
      beforeSend: function () {
        showModalPagoFeedback("info", "Registrando pago...");
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Registrando');
      },
      success: function (response) {
        llenarModalPago(response.data || {});
        showModalPagoFeedback("success", response.message || "Pago registrado correctamente.");
        cargarRecibosPago();
        cargarLecturas();
      },
      error: function (xhr) {
        showModalPagoFeedback("danger", extractAjaxMessage(xhr, "No se pudo registrar el pago."));
      },
      complete: function () {
        if (!reciboPagoActual || String(reciboPagoActual.estado_pago) === "pagado" || String(reciboPagoActual.estado_pago) === "cancelado" || Number(reciboPagoActual.saldo || 0) <= 0) {
          $button.prop("disabled", true).html('<i class="fas fa-save mr-1"></i> Registrar pago');
          return;
        }

        $button.prop("disabled", false).html('<i class="fas fa-save mr-1"></i> Registrar pago');
      }
    });
  });

  $("#btnBajaPeriodo").on("click", function () {
    bajaPeriodo($("#modalPeriodoId").val(), "modal");
  });

  $("#btnBajaRuta").on("click", function () {
    bajaRuta($("#modalRutaId").val(), "modal");
  });

  $("#btnBajaUsuario").on("click", function () {
    bajaUsuario($("#editUsuarioId").val(), "modal");
  });

  $(document).on("click", ".btn-eliminar-pago", function () {
    const pagoId = $(this).data("id");

    if (!confirm("Se eliminara este pago del historial del recibo. ¿Deseas continuar?")) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "pagos.eliminar",
        pago_id: pagoId
      },
      beforeSend: function () {
        showModalPagoFeedback("info", "Eliminando pago...");
      },
      success: function (response) {
        llenarModalPago(response.data || {});
        showModalPagoFeedback("success", response.message || "Pago eliminado correctamente.");
        cargarRecibosPago();
        cargarLecturas();
      },
      error: function (xhr) {
        showModalPagoFeedback("danger", extractAjaxMessage(xhr, "No se pudo eliminar el pago."));
      }
    });
  });

  $("#btnLogoutWhatsapp").on("click", function () {
    if (!confirm("Se cerrara la sesion actual de WhatsApp para generar un nuevo QR. ¿Deseas continuar?")) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "whatsapp.logout"
      },
      beforeSend: function () {
        showWhatsappFeedback("info", "Solicitando nuevo QR a UltraMsg...");
      },
      success: function (response) {
        showWhatsappFeedback("success", response.message || "Se genero un nuevo QR.");
        renderWhatsappPanel(response.data || {});
      },
      error: function (xhr) {
        showWhatsappFeedback("danger", extractAjaxMessage(xhr, "No se pudo cerrar la sesion de WhatsApp."));
      }
    });
  });

  $("#editMedidor").on("change", function () {
    const selectedEstado = $(this).find("option:selected").data("estado");

    if (selectedEstado) {
      $("#editEstadoMedidor").val(estadoMedidorTexto(selectedEstado));
    }
  });

  $("#ruta").on("change", function () {
    const codigo = $(this).find("option:selected").data("codigo") || "";
    $("#summaryRuta").text(valueOrFallback(codigo, "Sin ruta"));
  });

  $("#editFachada").on("change", function () {
    const file = this.files && this.files[0];
    if (!file) {
      return;
    }

    const imageUrl = URL.createObjectURL(file);
    $("#fachadaPreview").html('<img src="' + imageUrl + '" alt="Vista previa fachada"><span>Vista previa de la nueva foto.</span>');
  });

  $("#fachada").on("change", function () {
    const file = this.files && this.files[0];
    if (!file) {
      $("#fachadaAltaPreview").html('<i class="fas fa-home mr-2"></i><span>Sin foto de fachada cargada.</span>');
      return;
    }

    const imageUrl = URL.createObjectURL(file);
    $("#fachadaAltaPreview").html('<img src="' + imageUrl + '" alt="Vista previa fachada"><span>Vista previa de la foto que se guardara en el alta.</span>');
  });

  function sistemaRoleLabel(role) {
    if (window.MexquiticSession && typeof window.MexquiticSession.roleLabel === "function") {
      return window.MexquiticSession.roleLabel(role);
    }

    const labels = {
      admin: "Administrador",
      cobrador: "Cobro",
      verificador: "Verificador"
    };

    return labels[String(role || "").toLowerCase()] || "Perfil";
  }

  function formatDateTimeDisplay(value) {
    if (!value) {
      return "-";
    }

    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleString("es-MX", {
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit"
    });
  }

  function estadoSistemaBadge(activo) {
    return Number(activo) === 1
      ? '<span class="badge badge-success">Activo</span>'
      : '<span class="badge badge-secondary">Inactivo</span>';
  }

  function renderSistemaUsuarios(usuarios) {
    const $tbody = $("#tablaSistemaUsuarios tbody");

    if (!usuarios.length) {
      $tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron usuarios del sistema.</td></tr>');
      return;
    }

    $tbody.html(usuarios.map(function (usuario) {
      const login = formatDateTimeDisplay(usuario.ultimo_login_at);
      const acceso = formatDateTimeDisplay(usuario.ultimo_acceso_at);
      const modulo = usuario.ultimo_acceso_modulo ? '<div class="text-muted small">' + escapeHtml(usuario.ultimo_acceso_modulo) + '</div>' : '';
      const acciones = [
        '<button type="button" class="btn btn-sm btn-outline-primary btn-editar-sistema mr-1" data-id="' + escapeHtml(usuario.id) + '"><i class="fas fa-edit mr-1"></i>Editar</button>',
        '<button type="button" class="btn btn-sm btn-outline-secondary btn-reset-sistema mr-1" data-id="' + escapeHtml(usuario.id) + '" data-nombre="' + escapeHtml(usuario.nombre || "") + '"><i class="fas fa-key mr-1"></i>Clave</button>'
      ];

      if (Number(usuario.activo) === 1) {
        acciones.push(
          '<button type="button" class="btn btn-sm btn-outline-danger btn-baja-sistema" data-id="' + escapeHtml(usuario.id) + '" data-nombre="' + escapeHtml(usuario.nombre || "") + '"><i class="fas fa-user-slash mr-1"></i>Baja</button>'
        );
      }

      return '<tr>' +
        '<td><strong>' + escapeHtml(usuario.nombre || "") + '</strong></td>' +
        '<td><div class="font-weight-bold">@' + escapeHtml(usuario.usuario || "") + '</div><div class="text-muted small">' + escapeHtml(usuario.correo || "Sin correo") + '</div></td>' +
        '<td>' + escapeHtml(sistemaRoleLabel(usuario.rol)) + '</td>' +
        '<td>' + escapeHtml(usuario.telefono || "Sin telefono") + '</td>' +
        '<td>' + escapeHtml(login) + '<div class="text-muted small">' + escapeHtml(usuario.ultimo_login_ip || "") + '</div></td>' +
        '<td>' + escapeHtml(acceso) + modulo + '</td>' +
        '<td>' + estadoSistemaBadge(usuario.activo) + '</td>' +
        '<td class="text-right">' + acciones.join("") + '</td>' +
      '</tr>';
    }).join(""));
  }

  function actualizarPaginacionSistema(meta) {
    meta = meta || {};
    sistemaUsuariosPaginaActual = Number(meta.page || 1);
    sistemaUsuariosTotalPaginas = Number(meta.total_pages || 1);
    sistemaUsuariosPorPaginaActual = Number(meta.per_page || 0) === 0
      ? 0
      : Number(meta.effective_per_page || sistemaUsuariosPorPaginaActual || 25);
    $("#sistemaUsuariosPorPagina").val(String(sistemaUsuariosPorPaginaActual));

    const total = Number(meta.total || 0);
    const from = Number(meta.from || 0);
    const to = Number(meta.to || 0);
    let resumen = "Sin accesos para mostrar";

    if (Number(meta.per_page || 0) === 0 && total > 0) {
      resumen = "Total " + total + " accesos";
    } else if (total > 0) {
      resumen = "Total " + total + " accesos | " + from + "-" + to + " | Pagina " + sistemaUsuariosPaginaActual + " de " + sistemaUsuariosTotalPaginas;
    }

    $("#sistemaUsuariosResumenPaginacion").text(resumen);

    const allMode = Number(meta.per_page || 0) === 0;
    $("#btnSistemaUsuariosAnterior").prop("disabled", sistemaUsuariosPaginaActual <= 1 || allMode);
    $("#btnSistemaUsuariosSiguiente").prop("disabled", sistemaUsuariosPaginaActual >= sistemaUsuariosTotalPaginas || allMode);
  }

  function cargarUsuariosSistema(page) {
    if (page) {
      sistemaUsuariosPaginaActual = page;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "usuariosSistema.listar",
        termino: $("#buscarUsuarioSistema").val(),
        page: sistemaUsuariosPaginaActual,
        per_page: sistemaUsuariosPorPaginaActual
      },
      beforeSend: function () {
        $("#sistemaUsuariosPorPagina").prop("disabled", true);
        $("#btnSistemaUsuariosAnterior").prop("disabled", true);
        $("#btnSistemaUsuariosSiguiente").prop("disabled", true);
        $("#tablaSistemaUsuarios tbody").html('<tr><td colspan="8" class="text-center text-muted py-4">Cargando usuarios del sistema...</td></tr>');
      },
      success: function (response) {
        const data = response.data || {};
        renderSistemaUsuarios(data.usuarios || []);
        actualizarPaginacionSistema(data.pagination || {});
      },
      error: function (xhr) {
        showSistemaFeedback("danger", extractAjaxMessage(xhr, "No se pudo consultar el catalogo de usuarios del sistema."));
      },
      complete: function () {
        $("#sistemaUsuariosPorPagina").prop("disabled", false);
      }
    });
  }

  function renderBitacora(logs) {
    const $tbody = $("#tablaBitacoraSistema tbody");

    if (!logs.length) {
      $tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">Todavia no hay movimientos registrados.</td></tr>');
      return;
    }

    $tbody.html(logs.map(function (row) {
      const referencia = row.referencia_tipo
        ? escapeHtml(row.referencia_tipo + (row.referencia_id ? " #" + row.referencia_id : ""))
        : "-";

      return '<tr>' +
        '<td>' + escapeHtml(formatDateTimeDisplay(row.created_at)) + '</td>' +
        '<td><strong>' + escapeHtml(row.nombre_usuario || "Sistema") + '</strong></td>' +
        '<td>' + escapeHtml(sistemaRoleLabel(row.rol)) + '</td>' +
        '<td>' + escapeHtml(row.modulo || "-") + '</td>' +
        '<td>' + escapeHtml(row.accion || "-") + '</td>' +
        '<td>' + escapeHtml(row.descripcion || "-") + '</td>' +
        '<td>' + referencia + '</td>' +
      '</tr>';
      }).join(""));
  }

  function llenarSelectBitacora($select, items, placeholder) {
    const currentValue = String($select.val() || "");
    const options = ['<option value="">' + escapeHtml(placeholder) + '</option>'].concat((items || []).map(function (item) {
      const value = String(item || "");
      const selected = value === currentValue ? " selected" : "";
      return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(value) + '</option>';
    }));
    $select.html(options.join(""));
  }

  function actualizarCatalogosBitacora(catalogos) {
    if (!catalogos) {
      return;
    }

    llenarSelectBitacora($("#filtroUsuarioBitacora"), catalogos.usuarios || [], "Todos los usuarios");
    llenarSelectBitacora($("#filtroAccionBitacora"), catalogos.acciones || [], "Todas las acciones");
  }

  function renderBitacoraPaginacion(pagination) {
    const data = pagination || {};
    const total = Number(data.total || 0);
    const page = Number(data.page || 1);
    const totalPages = Number(data.total_pages || 1);
    const from = Number(data.from || 0);
    const to = Number(data.to || 0);

    bitacoraPaginaActual = page;
    bitacoraTotalPaginas = totalPages;
    $("#bitacoraPorPagina").val(String(data.per_page || bitacoraPorPaginaActual || 25));

    let resumen = "Sin movimientos para mostrar";
    if (total > 0) {
      resumen = "Total " + total + " movimientos | " + from + "-" + to + " | Pagina " + page + " de " + totalPages;
    }

    $("#bitacoraResumenPaginacion").text(resumen);
    $("#btnBitacoraAnterior").prop("disabled", page <= 1 || total === 0);
    $("#btnBitacoraSiguiente").prop("disabled", page >= totalPages || total === 0);
  }

  function cargarBitacora(page) {
    const modulo = $("#filtroModuloBitacora").val() || "";
    const usuario = $("#filtroUsuarioBitacora").val() || "";
    const accionFiltro = $("#filtroAccionBitacora").val() || "";
    const fechaDesde = $("#filtroFechaDesdeBitacora").val() || "";
    const fechaHasta = $("#filtroFechaHastaBitacora").val() || "";
    const targetPage = page || bitacoraPaginaActual || 1;
    const perPage = Number($("#bitacoraPorPagina").val() || bitacoraPorPaginaActual || 25);

    bitacoraPorPaginaActual = perPage;

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "bitacora.listar",
        modulo: modulo,
        usuario: usuario,
        accion_filtro: accionFiltro,
        fecha_desde: fechaDesde,
        fecha_hasta: fechaHasta,
        page: targetPage,
        per_page: perPage
      },
      beforeSend: function () {
        $("#tablaBitacoraSistema tbody").html('<tr><td colspan="7" class="text-center text-muted py-4">Cargando bitacora...</td></tr>');
        $("#btnBitacoraAnterior, #btnBitacoraSiguiente, #btnRecargarBitacora, #btnAplicarFiltrosBitacora, #btnLimpiarFiltrosBitacora, #bitacoraPorPagina").prop("disabled", true);
      },
      success: function (response) {
        const data = response.data || {};
        renderBitacora(data.logs || []);
        renderBitacoraPaginacion(data.pagination || {});
        actualizarCatalogosBitacora(data.catalogos || {});
      },
      error: function (xhr) {
        showSistemaFeedback("danger", extractAjaxMessage(xhr, "No se pudo consultar la bitacora del sistema."));
      },
      complete: function () {
        $("#btnBitacoraAnterior, #btnBitacoraSiguiente, #btnRecargarBitacora, #btnAplicarFiltrosBitacora, #btnLimpiarFiltrosBitacora, #bitacoraPorPagina").prop("disabled", false);
      }
    });
  }

  function limpiarModalSistemaUsuario() {
    $("#formSistemaUsuario")[0].reset();
    $("#sistemaUsuarioAccion").val("usuariosSistema.guardar");
    $("#sistemaUsuarioId").val("");
    $("#modalSistemaUsuarioTitle").text("Nuevo usuario del sistema");
    $("#sistemaPasswordWrap").removeClass("d-none");
    $("#modalSistemaUsuarioFeedback").addClass("d-none").removeClass("alert-success alert-info alert-warning alert-danger");
  }

  function abrirModalSistemaUsuarioNuevo() {
    limpiarModalSistemaUsuario();
    $("#modalSistemaUsuario").modal("show");
  }

  function cargarSistemaUsuario(id) {
    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "usuariosSistema.obtener",
        usuario_sistema_id: id
      },
      beforeSend: function () {
        limpiarModalSistemaUsuario();
        showModalSistemaUsuarioFeedback("info", "Cargando datos del usuario...");
      },
      success: function (response) {
        const data = response.data || {};
        $("#sistemaUsuarioAccion").val("usuariosSistema.actualizar");
        $("#sistemaUsuarioId").val(data.id || "");
        $("#sistemaNombre").val(data.nombre || "");
        $("#sistemaUsuario").val(data.usuario || "");
        $("#sistemaTelefono").val(data.telefono || "");
        $("#sistemaCorreo").val(data.correo || "");
        $("#sistemaRol").val(data.rol || "cobrador");
        $("#sistemaActivo").val(String(Number(data.activo || 0)));
        $("#modalSistemaUsuarioTitle").text("Editar usuario del sistema");
        $("#sistemaPasswordWrap").addClass("d-none");
        $("#modalSistemaUsuarioFeedback").addClass("d-none").removeClass("alert-success alert-info alert-warning alert-danger");
        $("#modalSistemaUsuario").modal("show");
      },
      error: function (xhr) {
        showSistemaFeedback("danger", extractAjaxMessage(xhr, "No se pudo cargar el usuario del sistema."));
      }
    });
  }

  function bajaUsuarioSistema(id, nombre) {
    if (!id) {
      showSistemaFeedback("warning", "No se identifico el usuario del sistema a dar de baja.");
      return;
    }

    if (!confirm("Deseas dar de baja al usuario del sistema " + (nombre || "seleccionado") + "?")) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "usuariosSistema.baja",
        usuario_sistema_id: id
      },
      beforeSend: function () {
        showSistemaFeedback("info", "Dando de baja usuario del sistema...");
      },
      success: function (response) {
        showSistemaFeedback("success", response.message || "Usuario del sistema dado de baja correctamente.");
        cargarUsuariosSistema(sistemaUsuariosPaginaActual);
        cargarBitacora();
      },
      error: function (xhr) {
        showSistemaFeedback("danger", extractAjaxMessage(xhr, "No se pudo dar de baja el usuario del sistema."));
      }
    });
  }

  $("#btnBuscarSistemaUsuarios").on("click", function () {
    sistemaUsuariosPaginaActual = 1;
    cargarUsuariosSistema(1);
  });

  $("#btnRecargarSistemaUsuarios").on("click", function () {
    $("#buscarUsuarioSistema").val("");
    sistemaUsuariosPaginaActual = 1;
    cargarUsuariosSistema(1);
  });

  $("#sistemaUsuariosPorPagina").on("change", function () {
    sistemaUsuariosPorPaginaActual = Number($(this).val() || 25);
    sistemaUsuariosPaginaActual = 1;
    cargarUsuariosSistema(1);
  });

  $("#btnAgregarSistemaUsuario").on("click", function () {
    abrirModalSistemaUsuarioNuevo();
  });

  $("#btnSistemaUsuariosAnterior").on("click", function () {
    if (sistemaUsuariosPaginaActual > 1) {
      cargarUsuariosSistema(sistemaUsuariosPaginaActual - 1);
    }
  });

  $("#btnSistemaUsuariosSiguiente").on("click", function () {
    if (sistemaUsuariosPaginaActual < sistemaUsuariosTotalPaginas) {
      cargarUsuariosSistema(sistemaUsuariosPaginaActual + 1);
    }
  });

    $("#btnRecargarBitacora").on("click", function () {
      cargarBitacora();
    });

    $("#filtroModuloBitacora").on("change", function () {
      bitacoraPaginaActual = 1;
      cargarBitacora();
    });

    $("#btnAplicarFiltrosBitacora").on("click", function () {
      bitacoraPaginaActual = 1;
      cargarBitacora(1);
    });

    $("#btnLimpiarFiltrosBitacora").on("click", function () {
      $("#filtroModuloBitacora").val("");
      $("#filtroUsuarioBitacora").val("");
      $("#filtroAccionBitacora").val("");
      $("#filtroFechaDesdeBitacora").val("");
      $("#filtroFechaHastaBitacora").val("");
      bitacoraPaginaActual = 1;
      cargarBitacora(1);
    });

    $("#bitacoraPorPagina").on("change", function () {
      bitacoraPorPaginaActual = Number($(this).val() || 25);
      bitacoraPaginaActual = 1;
      cargarBitacora(1);
    });

    $("#btnBitacoraAnterior").on("click", function () {
      if (bitacoraPaginaActual > 1) {
        cargarBitacora(bitacoraPaginaActual - 1);
      }
    });

    $("#btnBitacoraSiguiente").on("click", function () {
      if (bitacoraPaginaActual < bitacoraTotalPaginas) {
        cargarBitacora(bitacoraPaginaActual + 1);
      }
    });

  $(document).on("click", ".btn-editar-sistema", function () {
    cargarSistemaUsuario($(this).data("id"));
  });

  $(document).on("click", ".btn-reset-sistema", function () {
    $("#formResetSistemaPassword")[0].reset();
    $("#resetSistemaUsuarioId").val($(this).data("id"));
    $("#resetSistemaUsuarioNombre").text($(this).data("nombre") || "esta cuenta");
    $("#modalResetSistemaFeedback").addClass("d-none").removeClass("alert-success alert-info alert-warning alert-danger");
    $("#modalResetSistemaPassword").modal("show");
  });

  $(document).on("click", ".btn-baja-sistema", function () {
    bajaUsuarioSistema($(this).data("id"), $(this).data("nombre"));
  });

  $("#formSistemaUsuario").on("submit", function (event) {
    event.preventDefault();

    const $form = $(this);
    const $button = $form.find("button[type='submit']");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $form.serialize(),
      beforeSend: function () {
        showModalSistemaUsuarioFeedback("info", "Guardando usuario del sistema...");
        $button.prop("disabled", true);
      },
      success: function (response) {
        showSistemaFeedback("success", response.message || "Usuario del sistema guardado correctamente.");
        $("#modalSistemaUsuario").modal("hide");
        cargarUsuariosSistema(sistemaUsuariosPaginaActual);
        cargarBitacora();
      },
      error: function (xhr) {
        showModalSistemaUsuarioFeedback("danger", extractAjaxMessage(xhr, "No se pudo guardar el usuario del sistema."));
      },
      complete: function () {
        $button.prop("disabled", false);
      }
    });
  });

  $("#formResetSistemaPassword").on("submit", function (event) {
    event.preventDefault();

    const $form = $(this);
    const $button = $form.find("button[type='submit']");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: $form.serialize(),
      beforeSend: function () {
        showModalResetSistemaFeedback("info", "Restableciendo contraseña...");
        $button.prop("disabled", true);
      },
      success: function (response) {
        showSistemaFeedback("success", response.message || "Contraseña actualizada correctamente.");
        $("#modalResetSistemaPassword").modal("hide");
        cargarUsuariosSistema(sistemaUsuariosPaginaActual);
        cargarBitacora();
      },
      error: function (xhr) {
        showModalResetSistemaFeedback("danger", extractAjaxMessage(xhr, "No se pudo restablecer la contraseña."));
      },
      complete: function () {
        $button.prop("disabled", false);
      }
    });
  });

  window.setTimeout(finalizarCargaAplicacion, 1500);

  $.when(inicializarSesionAdmin()).always(function () {
    try {
      inicializarAplicacionAdmin();
    } catch (error) {
      console.error("Error al inicializar la plataforma administrativa.", error);
      abrirVistaSegura(vistaDefault, menuIdPorVista(vistaDefault));
    } finally {
      finalizarCargaAplicacion();
    }
  });
});
