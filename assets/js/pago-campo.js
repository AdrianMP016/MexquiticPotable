$(function () {
  const ajaxUrl = "ajax/peticiones.php";
  let scanner = null;
  let scannerActivo = false;
  let ultimoToken = "";
  let ultimoEscaneoMs = 0;
  let reciboActual = null;
  let consultaQrEnProceso = false;
  let bloqueoPagoForzado = false;

  function inicializarSesion() {
    if (!window.MexquiticSession) {
      return $.Deferred().resolve().promise();
    }

    window.MexquiticSession.bindLogout("#btnLogoutCobro", "cobro");

    return window.MexquiticSession.ensure("cobro", {
      panelSelector: "#sessionPanelCobro",
      nameSelector: "#sessionCobroNombre",
      roleSelector: "#sessionCobroRol"
    });
  }

  function escapeHtml(value) {
    return $("<div>").text(value || "").html();
  }

  function money(value) {
    return "$" + Number(value || 0).toLocaleString("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function datetimeLocalNow() {
    const now = new Date();
    now.setSeconds(0, 0);
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, "0");
    const d = String(now.getDate()).padStart(2, "0");
    const h = String(now.getHours()).padStart(2, "0");
    const min = String(now.getMinutes()).padStart(2, "0");
    return y + "-" + m + "-" + d + "T" + h + ":" + min;
  }

  function pagoBloqueado() {
    if (!reciboActual) {
      return true;
    }

    return bloqueoPagoForzado || reciboSinAdeudo(reciboActual);
  }

  function bloquearPagoConAviso() {
    actualizarAccesoPago();
    showScreen("screenDetalle");
    mostrarModalInfo(
      "Recibo sin adeudo",
      "Este recibo ya tiene pago registrado y no presenta adeudo en este momento."
    );
  }

  function showScreen(screenId) {
    if (screenId === "screenPago") {
      if (!reciboActual) {
        showFeedback("warning", "Primero escanea un recibo para continuar.");
        screenId = "screenScan";
      } else if (pagoBloqueado()) {
        bloquearPagoConAviso();
        return;
      }
    }

    $(".mobile-screen").removeClass("active");
    $("#" + screenId).addClass("active");
    $(".bottom-nav button").removeClass("active");
    $('.bottom-nav button[data-target="' + screenId + '"]').addClass("active");

    if (screenId !== "screenScan") {
      detenerScanner();
    }
  }

  function actualizarEstadoBotonesScanner() {
    $("#btnIniciarScanner").prop("disabled", scannerActivo);
    $("#btnDetenerScanner").prop("disabled", !scannerActivo);
  }

  function showFeedback(type, message) {
    $("#pagoCampoFeedback")
      .removeClass("d-none info warning success")
      .addClass(type || "info")
      .text(message || "");
  }

  function hideFeedback() {
    $("#pagoCampoFeedback").addClass("d-none").text("");
  }

  function badgeEstadoCobro(recibo) {
    const estado = String(recibo.estado_cobro || recibo.estado_pago || "pendiente").toLowerCase();
    if (estado === "pagado") {
      return { text: "Pagado", cls: "paid" };
    }
    if (estado === "adeudo") {
      return { text: "Adeudo", cls: "debt" };
    }
    return { text: "Pendiente", cls: "pending" };
  }

  function reciboSinAdeudo(recibo) {
    const saldo = Number(recibo && recibo.saldo ? recibo.saldo : 0);
    const estadoPago = String((recibo && recibo.estado_pago) || "").toLowerCase();
    return saldo <= 0 || estadoPago === "pagado" || estadoPago === "cancelado";
  }

  function mostrarModalInfo(titulo, mensaje) {
    $("#modalPagoCampoTitulo").text(titulo || "Aviso");
    $("#textoPagoCampoOk").text(mensaje || "");
    $("#modalPagoCampoOk").modal("show");
  }

  function refrescarBotonEntrega() {
    const entregado = reciboActual && Number(reciboActual.recibo_entregado || 0) === 1;
    const $btn = $("#btnToggleEntrega");

    if (entregado) {
      $btn
        .removeClass("btn-outline-primary")
        .addClass("btn-outline-danger")
        .html('<i class="fas fa-undo mr-1"></i> Marcar como no entregado');
      return;
    }

    $btn
      .removeClass("btn-outline-danger")
      .addClass("btn-outline-primary")
      .html('<i class="fas fa-check-circle mr-1"></i> Marcar recibo entregado');
  }

  function actualizarAccesoPago() {
    const bloqueado = pagoBloqueado();
    const $btnPago = $("#btnIrPago");
    const $navPago = $('.bottom-nav button[data-target="screenPago"]');

    $btnPago
      .prop("disabled", bloqueado)
      .toggleClass("btn-success", !bloqueado)
      .toggleClass("btn-secondary", bloqueado)
      .html(
        bloqueado
          ? '<i class="fas fa-check-circle mr-1"></i> Recibo sin adeudo'
          : '<i class="fas fa-cash-register mr-1"></i> Registrar pago'
      );

    $navPago
      .prop("disabled", bloqueado)
      .attr("aria-disabled", bloqueado ? "true" : "false")
      .toggleClass("disabled-nav", bloqueado);

    $("#btnGuardarPagoCampo").prop("disabled", bloqueado);
  }

  function llenarDetalleRecibo(recibo, qrToken) {
    reciboActual = recibo || null;
    if (!reciboActual) {
      bloqueoPagoForzado = false;
      return;
    }

    bloqueoPagoForzado = reciboSinAdeudo(reciboActual);

    const estado = badgeEstadoCobro(reciboActual);
    const entregado = Number(reciboActual.recibo_entregado || 0) === 1;
    const entregaTxt = entregado
      ? "Si" + (reciboActual.fecha_entrega ? " (" + reciboActual.fecha_entrega + ")" : "")
      : "No";

    $("#detalleUsuario").text(reciboActual.usuario || "Sin usuario");
    $("#detalleFolio").text(reciboActual.folio || "Sin folio");
    $("#detallePeriodo").text(reciboActual.periodo || "Sin periodo");
    $("#detalleMedidor").text(reciboActual.medidor || "Sin medidor");
    $("#detalleRuta").text(reciboActual.ruta || "Sin ruta");
    $("#detalleTotal").text(money(reciboActual.total));
    $("#detallePagado").text(money(reciboActual.total_pagado));
    $("#detalleSaldo").text(money(reciboActual.saldo));
    $("#detalleEntrega").text(entregaTxt);
    $("#detalleEstadoCobro")
      .removeClass("pending paid debt")
      .addClass(estado.cls)
      .text(estado.text);

    $("#pagoQrToken").val(qrToken || ultimoToken || "");
    $("#pagoMontoCampo").val(Number(reciboActual.saldo || 0).toFixed(2));
    $("#pagoFechaCampo").val(datetimeLocalNow());
    $("#pagoMetodoCampo").val("efectivo");
    $("#pagoReferenciaCampo").val("");
    $("#pagoObservacionesCampo").val("");
    refrescarBotonEntrega();
    actualizarAccesoPago();
  }

  function buscarReciboPorQr(qrToken) {
    const token = $.trim(qrToken || "");
    if (!token) {
      showFeedback("warning", "Escanea o captura un QR valido para continuar.");
      return;
    }

    if (consultaQrEnProceso) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      cache: false,
      dataType: "json",
      data: {
        accion: "pagos.reciboPorQr",
        qr_token: token
      },
      beforeSend: function () {
        consultaQrEnProceso = true;
        showFeedback("info", "Buscando recibo con el QR...");
      },
      success: function (response) {
        const continuar = function () {
          hideFeedback();
          ultimoToken = token;
          $("#scanTokenResume").text(token);
          llenarDetalleRecibo(response.data || {}, token);
          showScreen("screenDetalle");

          if (reciboSinAdeudo(response.data || {})) {
            mostrarModalInfo(
              "Recibo sin adeudo",
              "Este recibo ya tiene pago registrado y no presenta adeudo en este momento."
            );
          }
        };

        if (scannerActivo) {
          detenerScanner().finally(continuar);
          return;
        }

        continuar();
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : "No se pudo encontrar el recibo con ese QR.";
        showFeedback("warning", message);
      },
      complete: function () {
        consultaQrEnProceso = false;
      }
    });
  }

  async function iniciarScanner() {
    if (!window.Html5Qrcode) {
      showFeedback("warning", "No fue posible cargar el lector QR en este navegador.");
      return;
    }

    if (!scanner) {
      scanner = new Html5Qrcode("qrReader");
    }

    if (scannerActivo) {
      showFeedback("info", "El escaner ya esta activo.");
      return;
    }

    const onSuccess = function (decodedText) {
      const ahora = Date.now();
      if (decodedText === ultimoToken && ahora - ultimoEscaneoMs < 4000) {
        return;
      }
      ultimoEscaneoMs = ahora;
      buscarReciboPorQr(decodedText);
    };

    const onError = function () {
      return;
    };

    const config = {
      fps: 10,
      qrbox: { width: 250, height: 250 },
      rememberLastUsedCamera: true
    };

    try {
      await scanner.start({ facingMode: { exact: "environment" } }, config, onSuccess, onError);
      scannerActivo = true;
      actualizarEstadoBotonesScanner();
      showFeedback("success", "Escaner iniciado. Apunta al QR del recibo.");
      return;
    } catch (errorExacto) {
      try {
        await scanner.start({ facingMode: "environment" }, config, onSuccess, onError);
        scannerActivo = true;
        actualizarEstadoBotonesScanner();
        showFeedback("success", "Escaner iniciado. Apunta al QR del recibo.");
        return;
      } catch (errorGeneral) {
        scannerActivo = false;
        actualizarEstadoBotonesScanner();
        showFeedback("warning", "No se pudo iniciar la camara. Puedes capturar el token QR manualmente.");
      }
    }
  }

  async function detenerScanner() {
    if (!scanner || !scannerActivo) {
      return;
    }

    try {
      await scanner.stop();
      await scanner.clear();
    } catch (error) {
      return;
    } finally {
      scannerActivo = false;
      actualizarEstadoBotonesScanner();
    }
  }

  $("#btnIniciarScanner").on("click", function () {
    iniciarScanner();
  });

  $("#btnDetenerScanner").on("click", function () {
    detenerScanner();
    showFeedback("info", "Escaner detenido.");
  });

  $("#btnBuscarQrManual").on("click", function () {
    buscarReciboPorQr($("#qrManualInput").val());
  });

  $("#qrManualInput").on("keydown", function (event) {
    if (event.ctrlKey && event.key === "Enter") {
      buscarReciboPorQr($(this).val());
    }
  });

  $("#btnIrPago").on("click", function () {
    if (!reciboActual) {
      showFeedback("warning", "Primero escanea un recibo.");
      showScreen("screenScan");
      return;
    }

    if (pagoBloqueado()) {
      bloquearPagoConAviso();
      return;
    }

    showScreen("screenPago");
  });

  $("#btnToggleEntrega").on("click", function () {
    if (!reciboActual) {
      showFeedback("warning", "Primero escanea un recibo.");
      showScreen("screenScan");
      return;
    }

    const entregadoActual = Number(reciboActual.recibo_entregado || 0) === 1;
    const nuevoEstado = entregadoActual ? 0 : 1;
    const $button = $(this);

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      cache: false,
      dataType: "json",
      data: {
        accion: "pagos.actualizarEntrega",
        recibo_id: reciboActual.recibo_id,
        recibo_entregado: nuevoEstado
      },
      beforeSend: function () {
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando');
        showFeedback("info", "Actualizando estatus de entrega...");
      },
      success: function (response) {
        const recibo = response.data || {};
        llenarDetalleRecibo(recibo, $("#pagoQrToken").val());
        showFeedback("success", "Estatus de entrega actualizado.");
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : "No se pudo actualizar la entrega del recibo.";
        showFeedback("warning", message);
      },
      complete: function () {
        $button.prop("disabled", false);
        refrescarBotonEntrega();
      }
    });
  });

  $(".back-button, .bottom-nav button").on("click", function () {
    const target = $(this).data("target");
    if ((target === "screenDetalle" || target === "screenPago") && !reciboActual) {
      showFeedback("warning", "Primero escanea un recibo para continuar.");
      showScreen("screenScan");
      return;
    }

    if (target === "screenPago" && pagoBloqueado()) {
      bloquearPagoConAviso();
      return;
    }

    showScreen(target);
  });

  $("#formPagoCampo").on("submit", function (event) {
    event.preventDefault();

    if (!reciboActual) {
      showFeedback("warning", "Primero escanea un recibo.");
      showScreen("screenScan");
      return;
    }

    if (pagoBloqueado()) {
      bloquearPagoConAviso();
      return;
    }

    const $button = $("#btnGuardarPagoCampo");

    $.ajax({
      url: ajaxUrl,
      method: "POST",
      cache: false,
      dataType: "json",
      data: $(this).serialize(),
      beforeSend: function () {
        $button.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando');
        showFeedback("info", "Registrando pago en el sistema...");
      },
      success: function (response) {
        const recibo = response.data || {};
        llenarDetalleRecibo(recibo, $("#pagoQrToken").val());
        showScreen("screenDetalle");
        hideFeedback();
        $("#textoPagoCampoOk").text(
          "Pago registrado para folio " + (recibo.folio || "-") + ". Saldo actual: " + money(recibo.saldo || 0) + "."
        );
        $("#modalPagoCampoOk").modal("show");
      },
      error: function (xhr) {
        const message = xhr.responseJSON && xhr.responseJSON.message
          ? xhr.responseJSON.message
          : "No se pudo registrar el pago.";
        showFeedback("warning", message);
      },
      complete: function () {
        $button.prop("disabled", false).html('<i class="fas fa-save mr-1"></i> Guardar pago');
      }
    });
  });

  $(window).on("beforeunload", function () {
    detenerScanner();
  });

  $("#modalPagoCampoOk").on("hidden.bs.modal", function () {
    if (pagoBloqueado()) {
      showScreen("screenDetalle");
      actualizarAccesoPago();
    }
  });

  $.when(inicializarSesion()).always(function () {
    $("#pagoFechaCampo").val(datetimeLocalNow());
    actualizarEstadoBotonesScanner();
    actualizarAccesoPago();
  });
});
