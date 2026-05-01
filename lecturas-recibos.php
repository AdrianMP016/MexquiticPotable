<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'lecturas',
    'title' => 'Lecturas y recibos',
    'eyebrow' => 'Lecturas / Recibos',
    'breadcrumb' => 'Recibos',
    'content_fragment' => 'pages/lecturas.html',
    'modal_fragments' => [
        'modals/modal-generar-recibo.html',
        'modals/modal-preview-recibos-periodo.html',
        'modals/modal-notificacion-masiva.html',
    ],
]);
