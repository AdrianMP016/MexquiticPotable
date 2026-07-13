<?php

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Clases/Recibos.php';
require_once __DIR__ . '/app/Clases/WhatsApp.php';
require_once __DIR__ . '/app/Clases/WhatsAppBot.php';

header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/app/Config/ultramsg.php';
$config = is_file($configPath) ? require $configPath : [];
$secretoEsperado = (string) ($config['webhook_secret'] ?? '');
$secretoRecibido = (string) ($_GET['token'] ?? '');

if ($secretoEsperado === '' || !hash_equals($secretoEsperado, $secretoRecibido)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'token invalido']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    echo json_encode(['ok' => true]);
    exit;
}

$eventType = (string) ($payload['event_type'] ?? '');
$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$fromMe = (bool) ($data['fromMe'] ?? false);
$tipo = (string) ($data['type'] ?? '');
$from = trim((string) ($data['from'] ?? ''));
$body = (string) ($data['body'] ?? '');

if ($eventType === 'message_received' && !$fromMe && $tipo === 'chat' && $from !== '' && trim($body) !== '') {
    try {
        $bot = new WhatsAppBot($__mexquiticDb, new Recibos($__mexquiticDb), new WhatsApp());
        $bot->procesarMensajeEntrante($from, $body);
    } catch (Throwable $exception) {
        error_log('whatsapp-webhook: ' . $exception->getMessage());
    }
}

echo json_encode(['ok' => true]);
