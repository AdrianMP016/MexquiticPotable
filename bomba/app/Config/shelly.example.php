<?php

return [
    // Simulado: no llama a Shelly Cloud de verdad, guarda el estado en memoria/DB local.
    // Poner en false en cuanto se tengan las credenciales reales de la cuenta Shelly Cloud.
    'modo_simulado' => true,

    'server' => 'shelly-XX-eu.shelly.cloud',
    'auth_key' => 'TU_AUTH_KEY',
    'device_id_relay' => 'TU_ID_SHELLY_PRO',
    'channel_relay' => 0,
    'device_id_sensor' => 'TU_ID_SHELLY_HT',
    'verify_ssl' => true,
];
