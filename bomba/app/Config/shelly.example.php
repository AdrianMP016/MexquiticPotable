<?php

return [
    // Simulado: no llama a Shelly Cloud de verdad, guarda el estado en memoria/DB local.
    // Poner en false en cuanto se tengan las credenciales reales de la cuenta Shelly Cloud.
    'modo_simulado' => true,

    'server' => 'shelly-XX-eu.shelly.cloud',
    'auth_key' => 'TU_AUTH_KEY',
    'device_id_relay' => 'TU_ID_SHELLY_PRO',
    // Circuito de arranque/paro por pulso (no rele sostenido):
    // channel_inicio = canal que pulsa "Marcha" (enciende la bomba)
    // channel_paro   = canal que pulsa "Paro" (apaga la bomba)
    'channel_inicio' => 0,
    'channel_paro' => 1,
    'device_id_sensor' => 'TU_ID_SHELLY_HT',
    'verify_ssl' => true,
];
