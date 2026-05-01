<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'periodos',
    'title' => 'Periodos bimestrales',
    'eyebrow' => 'Periodos / Cobro',
    'breadcrumb' => 'Periodos',
    'content_fragment' => 'pages/periodos.html',
    'modal_fragments' => [
        'modals/modal-periodo.html',
    ],
]);
