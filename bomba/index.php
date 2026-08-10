<?php

require_once __DIR__ . '/app/Core/PageGuard.php';

$currentUser = bombaRequirePageAccess(['admin', 'operador']);
$pageTitle = 'Panel principal — Control de Bomba';
$activeView = 'panel';
$jsFile = 'dashboard.js';

require __DIR__ . '/app/views/_header.php';
?>

<div class="bomba-card oculto" id="mantenimientoBanner">
  <div class="emergencia-banner">
    <i class="fas fa-tools"></i>
    <div>
      <strong>Bomba apagada por mantenimiento</strong>
      <p id="mantenimientoBannerTexto" style="margin:4px 0 0;"></p>
    </div>
  </div>
  <a href="mantenimiento.php" class="btn-grande primario" style="width:100%; justify-content:center; margin-top:14px; text-decoration:none;">
    <i class="fas fa-tools"></i> Ir a Mantenimiento para reactivarla
  </a>
</div>

<div class="bomba-card oculto" id="emergenciaBanner">
  <div class="emergencia-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
      <strong>Apagado de emergencia activo</strong>
      <p id="emergenciaBannerTexto" style="margin:4px 0 0;"></p>
    </div>
  </div>
  <button type="button" class="btn-grande primario" id="btnReanudarOperacion" style="width:100%; justify-content:center; margin-top:14px;">
    <i class="fas fa-play"></i> Reanudar operacion normal
  </button>
</div>

<div class="bomba-card">
  <h2><i class="fas fa-tint"></i> Estado de la bomba</h2>
  <div class="bomba-estado">
    <div class="bomba-led apagada" id="bombaLed"><i class="fas fa-power-off"></i></div>
    <div>
      <div class="bomba-estado-texto apagada" id="bombaEstadoTexto">Consultando...</div>
      <div class="bomba-temperatura" id="bombaTemperatura"><i class="fas fa-thermometer-half"></i> --</div>
      <div class="sensor-actualizado oculto" id="bombaSensorActualizado"></div>
    </div>
  </div>

  <div id="estadoFeedback" class="alerta peligro oculto"></div>

  <button type="button" class="btn-bomba encender" id="btnEncenderApagar" disabled>
    <i class="fas fa-power-off"></i> Cargando...
  </button>
  <div class="btn-bomba-espera oculto" id="esperaTexto"></div>

  <button type="button" class="btn-grande secundario" id="btnVerificarConexion" style="width:100%; justify-content:center; margin-top:14px;">
    <i class="fas fa-satellite-dish"></i> Verificar conexion con el Shelly
  </button>

  <button type="button" class="btn-grande peligro" id="btnApagadoEmergencia" style="width:100%; justify-content:center; margin-top:14px;">
    <i class="fas fa-exclamation-triangle"></i> Apagado de emergencia
  </button>
</div>

<div class="bomba-modal-fondo" id="modalConfirmarEncendido">
  <div class="bomba-modal-caja">
    <h3><i class="fas fa-exclamation-triangle"></i> <span id="modalConfirmarEncendidoTitulo">Confirmar accion</span></h3>
    <p id="modalConfirmarEncendidoTexto">¿Seguro que quieres continuar?</p>
    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCancelarConfirmarEncendido">Cancelar</button>
      <button type="button" class="btn-grande primario" id="btnConfirmarEncendido">Confirmar</button>
    </div>
  </div>
</div>

<div class="bomba-modal-fondo" id="modalConfirmarEmergencia">
  <div class="bomba-modal-caja">
    <h3><i class="fas fa-exclamation-triangle"></i> Confirmar apagado de emergencia</h3>
    <p>La bomba se apagara de inmediato y <strong>no va a volver a encender sola</strong> (ni por cronometro ni por ninguna regla programada) hasta que alguien la reanude manualmente. Usalo solo para mantenimiento o una necesidad real de dejarla apagada.</p>
    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCancelarEmergencia">Cancelar</button>
      <button type="button" class="btn-grande peligro" id="btnConfirmarEmergencia">Si, apagar de emergencia</button>
    </div>
  </div>
</div>

<div class="bomba-modal-fondo" id="modalConexion">
  <div class="bomba-modal-caja">
    <h3><i class="fas fa-satellite-dish"></i> Conexion con el Shelly</h3>
    <div id="modalConexionContenido">Consultando...</div>
    <div class="bomba-modal-botones">
      <button type="button" class="btn-grande secundario" id="btnCerrarModalConexion" style="width:100%;">Cerrar</button>
    </div>
  </div>
</div>

<div class="bomba-card">
  <h2><i class="fas fa-stopwatch"></i> Cronometro</h2>
  <p style="color:var(--agua-muted); margin-top:-8px;">Enciende la bomba ahora por un tiempo determinado; se apaga sola al terminar.</p>

  <div id="cronometroInactivo">
    <div class="cronometro-selector">
      <div>
        <label for="cronHoras">Horas</label>
        <input type="number" id="cronHoras" min="0" max="23" value="0">
      </div>
      <div>
        <label for="cronMinutos">Minutos</label>
        <input type="number" id="cronMinutos" min="0" max="59" value="30">
      </div>
      <button type="button" class="btn-grande primario" id="btnIniciarCronometro">
        <i class="fas fa-play"></i> Iniciar
      </button>
    </div>
  </div>

  <div id="cronometroActivoBox" class="oculto">
    <div class="cronometro-activo" id="cronometroTexto">--</div>
    <button type="button" class="btn-grande peligro" id="btnCancelarCronometro" style="margin-top:14px; width:100%; justify-content:center;">
      <i class="fas fa-stop"></i> Cancelar cronometro
    </button>
  </div>

  <div id="cronometroFeedback" class="alerta peligro oculto"></div>
</div>

<div class="bomba-card">
  <h2><i class="fas fa-chart-bar"></i> Actividad de la bomba</h2>
  <div class="actividad-tabs">
    <button type="button" class="activo" data-periodo="dia">Dia</button>
    <button type="button" data-periodo="semana">Semana</button>
    <button type="button" data-periodo="mes">Mes</button>
    <button type="button" data-periodo="anio">Año</button>
  </div>
  <div class="actividad-stats">
    <div class="actividad-stat">
      <div class="valor" id="actividadHoras">--</div>
      <div class="etiqueta">Horas encendida</div>
    </div>
    <div class="actividad-stat">
      <div class="valor" id="actividadVeces">--</div>
      <div class="etiqueta">Veces activada</div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/app/views/_footer.php'; ?>
