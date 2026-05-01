<?php

class WhatsApp
{
 private array $config;

 public function __construct()
  {
    $configPath = __DIR__ . '/../Config/ultramsg.php';
    if (!is_file($configPath)) {
      $configPath = __DIR__ . '/../Config/ultramsg.example.php';
    }

    $this->config = require $configPath;
  }

  public function panel(string $messageStatus = 'sent', int $limit = 15): array
  {
    $status = $this->status();
    $messages = $this->messages($messageStatus, $limit);
    $isLinked = in_array($status['status'], ['authenticated', 'standby'], true);
    $qrImage = null;

    if (!$isLinked) {
      try {
        $qrImage = $this->qrImageDataUrl();
      } catch (Throwable $exception) {
        $qrImage = null;
      }
    }

    return [
      'instance_id' => $this->config['instance_id'],
      'account_status' => $status['status'],
      'account_substatus' => $status['substatus'],
      'is_linked' => $isLinked,
      'qr_image' => $qrImage,
      'message_status' => $messageStatus,
      'messages' => $messages['messages'],
      'messages_total' => $messages['total'],
      'messages_page' => $messages['page'],
      'messages_limit' => $messages['limit'],
      'checked_at' => date('Y-m-d H:i:s'),
    ];
  }

  public function enviarImagen(string $telefono, string $rutaImagen, string $caption = ''): array
  {
    $destino = $this->normalizarTelefono($telefono);

    if (!is_file($rutaImagen)) {
      throw new RuntimeException('No se encontro la imagen del recibo para enviar.');
    }

    $contenido = file_get_contents($rutaImagen);

    if ($contenido === false) {
      throw new RuntimeException('No se pudo leer la imagen del recibo.');
    }

    $response = $this->request('POST', '/messages/image', [
      'token' => $this->config['token'],
      'to' => $destino,
      'image' => base64_encode($contenido),
      'caption' => $caption,
    ]);

    return [
      'to' => $destino,
      'response' => $response,
    ];
  }

  public function enviarTexto(string $telefono, string $mensaje): array
  {
    $destino = $this->normalizarTelefono($telefono);
    $mensaje = trim($mensaje);

    if ($mensaje === '') {
      throw new InvalidArgumentException(json_encode([
        'mensaje' => 'El mensaje de WhatsApp no puede ir vacio.',
      ], JSON_UNESCAPED_UNICODE));
    }

    $response = $this->request('POST', '/messages/chat', [
      'token' => $this->config['token'],
      'to' => $destino,
      'body' => $mensaje,
    ]);

    return [
      'to' => $destino,
      'response' => $response,
    ];
  }

  public function logout(): array
  {
    $this->request('POST', '/instance/logout', [
      'token' => $this->config['token'],
    ]);

    return $this->panel('sent', 15);
  }

  private function status(): array
  {
    $response = $this->request('GET', '/instance/status', [
      'token' => $this->config['token'],
    ]);

    $accountStatus = $response['status']['accountStatus'] ?? [];

    return [
      'status' => $accountStatus['status'] ?? 'unknown',
      'substatus' => $accountStatus['substatus'] ?? '',
    ];
  }

  private function qrImageDataUrl(): ?string
  {
    $binary = $this->request('GET', '/instance/qr', [
      'token' => $this->config['token'],
    ], true);

    if (!$binary) {
      return null;
    }

    $trimmed = ltrim($binary);
    if ($trimmed !== '' && $trimmed[0] === '{') {
      $decoded = json_decode($binary, true);
      if (is_array($decoded) && isset($decoded['error'])) {
        return null;
      }
    }

    return 'data:image/png;base64,' . base64_encode($binary);
  }

  private function messages(string $messageStatus = 'sent', int $limit = 15): array
  {
    $allowedStatuses = ['sent', 'queue', 'unsent', 'invalid', 'all'];
    $messageStatus = in_array($messageStatus, $allowedStatuses, true) ? $messageStatus : 'sent';
    $limit = max(1, min(50, $limit));

    $response = $this->request('GET', '/messages', [
      'token' => $this->config['token'],
      'page' => 1,
      'limit' => $limit,
      'status' => $messageStatus,
      'sort' => 'desc',
    ]);

    return [
      'total' => (int) ($response['total'] ?? 0),
      'page' => (int) ($response['page'] ?? 1),
      'limit' => (int) ($response['limit'] ?? $limit),
      'messages' => is_array($response['messages'] ?? null) ? $response['messages'] : [],
    ];
  }

  private function normalizarTelefono(string $telefono): string
  {
    $telefono = trim($telefono);

    if ($telefono === '') {
      throw new InvalidArgumentException(json_encode([
        'whatsapp' => 'El usuario no tiene WhatsApp registrado.',
      ], JSON_UNESCAPED_UNICODE));
    }

    if (str_contains($telefono, '@')) {
      return $telefono;
    }

    $digits = preg_replace('/\D+/', '', $telefono) ?? '';

    if (strlen($digits) === 10) {
      $digits = '52' . $digits;
    }

    if (strlen($digits) < 11) {
      throw new InvalidArgumentException(json_encode([
        'whatsapp' => 'El WhatsApp del usuario no tiene formato internacional valido.',
      ], JSON_UNESCAPED_UNICODE));
    }

    return '+' . $digits;
  }

  private function request(string $method, string $path, array $params = [], bool $raw = false)
  {
    $baseUrl = rtrim($this->config['base_url'], '/');
    $url = $baseUrl . '/' . $this->config['instance_id'] . $path;

    if ($method === 'GET' && !empty($params)) {
      $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 25,
      CURLOPT_SSL_VERIFYPEER => (bool) ($this->config['verify_ssl'] ?? true),
      CURLOPT_SSL_VERIFYHOST => (bool) ($this->config['verify_ssl'] ?? true) ? 2 : 0,
      CURLOPT_CUSTOMREQUEST => $method,
    ]);

    if ($method === 'POST') {
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $error) {
      throw new RuntimeException('No se pudo conectar con UltraMsg: ' . $error);
    }

    if ($httpCode >= 400) {
      throw new RuntimeException('UltraMsg devolvio un error HTTP ' . $httpCode . '.');
    }

    if ($raw) {
      return $body;
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
      throw new RuntimeException('La respuesta de UltraMsg no se pudo interpretar.');
    }

    if (isset($decoded['error']) && $decoded['error']) {
      $message = is_string($decoded['error']) ? $decoded['error'] : 'UltraMsg devolvio un error.';
      throw new RuntimeException($message);
    }

    return $decoded;
  }
}
