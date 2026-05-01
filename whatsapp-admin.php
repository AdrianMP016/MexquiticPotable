<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'whatsapp',
    'title' => 'Enlace y monitoreo de WhatsApp',
    'eyebrow' => 'WhatsApp / UltraMsg',
    'breadcrumb' => 'WhatsApp',
    'content_fragment' => 'pages/whatsapp.html',
]);
