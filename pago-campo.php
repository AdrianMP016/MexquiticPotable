<?php

require_once __DIR__ . '/app/Core/PageGuard.php';
mexquiticRequirePageAccess('cobro', 'Ingreso al entorno de cobro.');
readfile(__DIR__ . '/pago-campo.html');
