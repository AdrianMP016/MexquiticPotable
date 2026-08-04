let bombaBitacoraPagina = 1;
let bombaBitacoraTipo = "todo";
let bombaBitacoraFechaDesde = "";
let bombaBitacoraFechaHasta = "";

function bombaRenderBitacora(logs) {
  if (!logs.length) {
    $("#bitacoraLista").html('<p style="color:var(--agua-muted);">Sin actividad registrada.</p>');
    return;
  }

  var html = logs.map(function (log) {
    return (
      '<div class="bitacora-item">' +
        '<div class="fecha">' + bombaFormatoFechaCorta12h(log.created_at) + ' &middot; ' + (log.nombre_usuario || "Sistema") + '</div>' +
        '<div class="accion">' + (log.descripcion || log.accion) + '</div>' +
      '</div>'
    );
  }).join("");

  $("#bitacoraLista").html(html);
}

function bombaUltimoDiaMes(anio, mes) {
  return new Date(anio, mes, 0).getDate();
}

function bombaCargarBitacora() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: {
      accion: "bitacora.listar",
      page: bombaBitacoraPagina,
      per_page: 25,
      fecha_desde: bombaBitacoraFechaDesde,
      fecha_hasta: bombaBitacoraFechaHasta
    },
    success: function (response) {
      var data = response.data || {};
      bombaRenderBitacora(data.logs || []);

      var pagination = data.pagination || { page: 1, total_pages: 1 };
      bombaBitacoraPagina = pagination.page;

      $("#bitacoraPaginaTexto").text("Pagina " + pagination.page + " de " + pagination.total_pages);
      $("#btnPaginaAnterior").prop("disabled", pagination.page <= 1);
      $("#btnPaginaSiguiente").prop("disabled", pagination.page >= pagination.total_pages);
    }
  });
}

function bombaBitacoraMostrarCampo(tipo) {
  $("#bitacoraFiltroDia, #bitacoraFiltroMes, #bitacoraFiltroAnio").addClass("oculto");

  if (tipo === "dia") { $("#bitacoraFiltroDia").removeClass("oculto"); }
  if (tipo === "mes") { $("#bitacoraFiltroMes").removeClass("oculto"); }
  if (tipo === "anio") { $("#bitacoraFiltroAnio").removeClass("oculto"); }
}

function bombaBitacoraAplicarFiltro() {
  if (bombaBitacoraTipo === "dia") {
    var fecha = $("#bitacoraFiltroDia").val();
    if (!fecha) { return; }
    bombaBitacoraFechaDesde = fecha;
    bombaBitacoraFechaHasta = fecha;
  } else if (bombaBitacoraTipo === "mes") {
    var valorMes = $("#bitacoraFiltroMes").val();
    if (!valorMes) { return; }
    var partes = valorMes.split("-");
    var anio = parseInt(partes[0], 10);
    var mes = parseInt(partes[1], 10);
    bombaBitacoraFechaDesde = valorMes + "-01";
    bombaBitacoraFechaHasta = valorMes + "-" + String(bombaUltimoDiaMes(anio, mes)).padStart(2, "0");
  } else if (bombaBitacoraTipo === "anio") {
    var anioSel = $("#bitacoraFiltroAnio").val();
    bombaBitacoraFechaDesde = anioSel + "-01-01";
    bombaBitacoraFechaHasta = anioSel + "-12-31";
  } else {
    bombaBitacoraFechaDesde = "";
    bombaBitacoraFechaHasta = "";
  }

  bombaBitacoraPagina = 1;
  bombaCargarBitacora();
}

$(function () {
  var anioActual = new Date().getFullYear();
  var opcionesAnio = "";
  for (var a = anioActual; a >= anioActual - 5; a--) {
    opcionesAnio += '<option value="' + a + '">' + a + "</option>";
  }
  $("#bitacoraFiltroAnio").html(opcionesAnio);
  $("#bitacoraFiltroDia").val(new Date().toISOString().slice(0, 10));
  $("#bitacoraFiltroMes").val(new Date().toISOString().slice(0, 7));

  bombaCargarBitacora();

  $("#bitacoraFiltroTipos .dia-pastilla").on("click", function () {
    $("#bitacoraFiltroTipos .dia-pastilla").removeClass("activo");
    $(this).addClass("activo");
    bombaBitacoraTipo = $(this).data("tipo");
    bombaBitacoraMostrarCampo(bombaBitacoraTipo);

    if (bombaBitacoraTipo === "todo") {
      bombaBitacoraAplicarFiltro();
    }
  });

  $("#btnBitacoraFiltrar").on("click", bombaBitacoraAplicarFiltro);

  $("#btnPaginaAnterior").on("click", function () {
    if (bombaBitacoraPagina > 1) {
      bombaBitacoraPagina--;
      bombaCargarBitacora();
    }
  });

  $("#btnPaginaSiguiente").on("click", function () {
    bombaBitacoraPagina++;
    bombaCargarBitacora();
  });
});
