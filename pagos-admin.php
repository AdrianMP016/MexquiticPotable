<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'pagos',
    'title' => 'Registro manual de pagos',
    'eyebrow' => 'Pagos / Caja',
    'breadcrumb' => 'Pagos',
    'content_fragment' => 'pages/pagos.html',
    'modal_fragments' => [
        'modals/modal-registrar-pago.html',
    ],
]);
