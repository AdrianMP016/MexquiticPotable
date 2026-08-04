let bombaActividadAnio = new Date().getFullYear();
let bombaActividadMes = new Date().getMonth() + 1;

const bombaMeses = [
  "", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
  "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
];
const bombaDiasNombre = ["Lun", "Mar", "Mie", "Jue", "Vie", "Sab", "Dom"];

function bombaPrimerDiaSemanaIso(anio, mes) {
  var fecha = new Date(anio, mes - 1, 1);
  var diaIso = fecha.getDay();
  return diaIso === 0 ? 7 : diaIso;
}

function bombaRenderCalendario(data) {
  $("#actividadMesTitulo").html('<i class="fas fa-calendar-alt"></i> ' + bombaMeses[data.mes] + ' ' + data.anio);
  $("#actividadMesHoras").text(bombaFormatoHorasMinutos(data.total_segundos || 0));
  $("#actividadMesVeces").text(data.total_veces || 0);

  var html = bombaDiasNombre.map(function (n) {
    return '<div class="calendario-dia-nombre">' + n + '</div>';
  }).join("");

  var espacios = bombaPrimerDiaSemanaIso(data.anio, data.mes) - 1;
  for (var i = 0; i < espacios; i++) {
    html += '<div class="calendario-celda vacia"></div>';
  }

  data.dias.forEach(function (dia) {
    var conActividad = dia.veces > 0;
    html += (
      '<div class="calendario-celda ' + (conActividad ? "con-actividad" : "") + '" ' +
        (conActividad ? 'data-fecha="' + dia.fecha + '"' : '') + '>' +
        '<div class="numero-dia">' + dia.dia + '</div>' +
        (conActividad ? '<div class="horas-dia">' + bombaFormatoHorasMinutosCorto(dia.segundos) + ' &middot; ' + dia.veces + 'x</div>' : '') +
      '</div>'
    );
  });

  $("#calendarioActividad").html(html);
}

function bombaCargarResumenMensual() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "activaciones.resumenMensual", anio: bombaActividadAnio, mes: bombaActividadMes },
    success: function (response) {
      bombaRenderCalendario(response.data || {});
    }
  });
}

function bombaOrigenTexto(origen) {
  if (origen === "automatico") { return "Automatico (programacion)"; }
  if (origen === "cronometro") { return "Cronometro"; }
  return "Manual";
}

$(function () {
  bombaCargarResumenMensual();

  $("#btnMesAnterior").on("click", function () {
    bombaActividadMes--;
    if (bombaActividadMes < 1) {
      bombaActividadMes = 12;
      bombaActividadAnio--;
    }
    bombaCargarResumenMensual();
  });

  $("#btnMesSiguiente").on("click", function () {
    bombaActividadMes++;
    if (bombaActividadMes > 12) {
      bombaActividadMes = 1;
      bombaActividadAnio++;
    }
    bombaCargarResumenMensual();
  });

  $(document).on("click", ".calendario-celda.con-actividad", function () {
    var fecha = $(this).data("fecha");

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "activaciones.detalleDia", fecha: fecha },
      success: function (response) {
        var lista = (response.data || {}).activaciones || [];
        var html = lista.map(function (a) {
          var duracion = a.duracion_segundos
            ? bombaFormatoHorasMinutos(a.duracion_segundos)
            : "en curso";
          return (
            '<div class="detalle-dia-item">' +
              '<strong>' + bombaOrigenTexto(a.origen) + '</strong> &middot; ' + duracion + '<br>' +
              '<span style="color:var(--agua-muted);">' + bombaFormatoFecha12h(a.inicio_at) + (a.fin_at ? " a " + bombaFormatoFecha12h(a.fin_at) : "") + '</span>' +
              (a.iniciado_por_nombre ? '<br><span style="color:var(--agua-muted);">Por: ' + a.iniciado_por_nombre + '</span>' : '') +
            '</div>'
          );
        }).join("");

        $("#modalDetalleDiaTitulo").html('<i class="fas fa-calendar-day"></i> Detalle del ' + fecha);
        $("#modalDetalleDiaLista").html(html || '<p style="color:var(--agua-muted);">Sin registros.</p>');
        $("#modalDetalleDia").addClass("abierto");
      }
    });
  });

  $("#btnCerrarDetalleDia").on("click", function () {
    $("#modalDetalleDia").removeClass("abierto");
  });
});
