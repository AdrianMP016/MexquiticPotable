<?php

require_once __DIR__ . '/app/Core/AdminPage.php';

mexquiticRenderAdminPage([
    'view' => 'sistema',
    'title' => 'Usuarios del sistema',
    'eyebrow' => 'Sistema / Seguridad',
    'breadcrumb' => 'Seguridad',
    'content_fragment' => 'pages/sistema.html',
    'modal_fragments' => [
        'modals/modal-sistema-usuario.html',
        'modals/modal-reset-sistema-password.html',
    ],
    'bootstrap_view' => 'sistema',
    'admin_only' => true,
]);
