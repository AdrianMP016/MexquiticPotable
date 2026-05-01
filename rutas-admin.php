<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'rutas',
    'title' => 'Rutas',
    'eyebrow' => 'Rutas / Catalogo',
    'breadcrumb' => 'Rutas',
    'content_fragment' => 'pages/rutas.html',
    'modal_fragments' => [
        'modals/modal-ruta.html',
    ],
]);
