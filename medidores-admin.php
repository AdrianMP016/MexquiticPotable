<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'medidores',
    'title' => 'Medidores',
    'eyebrow' => 'Medidores / Consulta',
    'breadcrumb' => 'Medidores',
    'content_fragment' => 'pages/medidores.html',
    'modal_fragments' => [
        'modals/modal-medidor.html',
    ],
]);
