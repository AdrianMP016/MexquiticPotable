<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'alta',
    'title' => 'Alta de usuario',
    'eyebrow' => 'Usuarios / Alta',
    'breadcrumb' => 'Alta',
    'content_fragment' => 'pages/alta.html',
    'modal_fragments' => [
        'modals/modal-ruta.html',
        'modals/modal-guardado.html',
    ],
    'needs_maps' => true,
]);
