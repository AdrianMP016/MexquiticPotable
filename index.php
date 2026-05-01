<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'consulta',
    'title' => 'Consulta de usuarios',
    'eyebrow' => 'Usuarios / Consulta',
    'breadcrumb' => 'Consulta',
    'content_fragment' => 'pages/consulta.html',
    'modal_fragments' => [
        'modals/modal-ruta.html',
        'modals/modal-editar-usuario.html',
    ],
    'bootstrap_view' => 'consulta',
    'needs_maps' => true,
]);
