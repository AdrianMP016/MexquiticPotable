<?php

/**
 * Envia notificaciones push del navegador (Web Push, RFC 8291/8292) sin
 * depender de ninguna libreria externa ni de Composer (este proyecto no usa
 * ninguno de los dos) - solo ext-openssl y ext-hash, que ya trae PHP.
 *
 * Nota de implementacion: el cifrado del mensaje (aes128gcm) y la firma VAPID
 * (ES256) se hacen a mano siguiendo el estandar al pie de la letra, porque no
 * hay libreria vendorizada para esto en el proyecto. Si algun push deja de
 * llegar, revisar primero aqui antes que en el navegador.
 */
class WebPushClient
{
    private array $config;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;

        $configPath = __DIR__ . '/../Config/webpush.php';
        if (!is_file($configPath)) {
            $configPath = __DIR__ . '/../Config/webpush.example.php';
        }

        $this->config = require $configPath;
    }

    public function clavePublica(): string
    {
        return (string) $this->config['vapid_public_key'];
    }

    public function suscribir(?int $usuarioId, string $endpoint, string $p256dh, string $auth, string $userAgent): void
    {
        $hash = hash('sha256', $endpoint);

        $stmt = $this->db->prepare(
            "INSERT INTO bomba_push_subscripciones
                (usuario_bomba_id, endpoint, endpoint_hash, p256dh_key, auth_key, user_agent)
             VALUES (:uid, :endpoint, :hash, :p256dh, :auth, :ua)
             ON DUPLICATE KEY UPDATE
                usuario_bomba_id = VALUES(usuario_bomba_id),
                p256dh_key = VALUES(p256dh_key),
                auth_key = VALUES(auth_key),
                user_agent = VALUES(user_agent)"
        );
        $stmt->execute([
            'uid' => $usuarioId,
            'endpoint' => $endpoint,
            'hash' => $hash,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'ua' => substr($userAgent, 0, 255),
        ]);
    }

    public function desuscribir(string $endpoint): void
    {
        $stmt = $this->db->prepare("DELETE FROM bomba_push_subscripciones WHERE endpoint_hash = :hash");
        $stmt->execute(['hash' => hash('sha256', $endpoint)]);
    }

    /**
     * Manda la notificacion a todas las suscripciones activas. Nunca truena
     * la accion que la dispara (encender/apagar la bomba, etc.) si un envio
     * falla - cada suscripcion se intenta por separado y los errores solo se
     * ignoran (o, si el navegador ya no existe, se borra la suscripcion).
     */
    public function enviarATodos(string $titulo, string $cuerpo, array $datos = []): void
    {
        $suscripciones = $this->db->query(
            "SELECT id, endpoint, p256dh_key, auth_key FROM bomba_push_subscripciones"
        )->fetchAll();

        if (!$suscripciones) {
            return;
        }

        $payload = json_encode([
            'titulo' => $titulo,
            'cuerpo' => $cuerpo,
            'datos' => $datos,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($suscripciones as $suscripcion) {
            try {
                $this->enviarUna($suscripcion, $payload);
            } catch (WebPushGoneException $exception) {
                $stmt = $this->db->prepare("DELETE FROM bomba_push_subscripciones WHERE id = :id");
                $stmt->execute(['id' => $suscripcion['id']]);
            } catch (Throwable $exception) {
                // Una suscripcion fallando (navegador sin internet, etc.) no debe
                // impedir que las demas reciban su notificacion.
            }
        }
    }

    private function enviarUna(array $suscripcion, string $payload): void
    {
        $cuerpoCifrado = $this->cifrarPayload(
            $payload,
            (string) $suscripcion['p256dh_key'],
            (string) $suscripcion['auth_key']
        );

        $endpoint = (string) $suscripcion['endpoint'];
        $origen = $this->origenDe($endpoint);
        $jwt = $this->generarJwtVapid($origen);

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 60',
            'Authorization: vapid t=' . $jwt . ', k=' . $this->config['vapid_public_key'],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $cuerpoCifrado,
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 404 || $httpCode === 410) {
            throw new WebPushGoneException('La suscripcion ya no existe (' . $httpCode . ').');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('El servicio de push devolvio ' . $httpCode . ($error ? ' (' . $error . ')' : ''));
        }
    }

    /**
     * Cifra el mensaje siguiendo RFC 8291 (Message Encryption for Web Push)
     * con content-coding aes128gcm (RFC 8188), como un solo registro.
     */
    private function cifrarPayload(string $payload, string $p256dhB64, string $authB64): string
    {
        $uaPublicRaw = self::b64urlDecode($p256dhB64);
        $authSecret = self::b64urlDecode($authB64);

        if (strlen($uaPublicRaw) !== 65 || $uaPublicRaw[0] !== "\x04") {
            throw new RuntimeException('La llave p256dh de la suscripcion no es valida.');
        }

        $asKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($asKey === false) {
            throw new RuntimeException('No se pudo generar la llave efimera para el push.');
        }
        $asDetails = openssl_pkey_get_details($asKey);
        $asPublicRaw = "\x04" . self::pad32($asDetails['ec']['x']) . self::pad32($asDetails['ec']['y']);

        $uaPublicKeyResource = openssl_pkey_get_public(self::pemPublicKeyFromRawPoint($uaPublicRaw));
        if ($uaPublicKeyResource === false) {
            throw new RuntimeException('No se pudo interpretar la llave publica de la suscripcion.');
        }

        $ecdhSecret = openssl_pkey_derive($uaPublicKeyResource, $asKey, 32);
        if ($ecdhSecret === false || strlen($ecdhSecret) !== 32) {
            throw new RuntimeException('No se pudo calcular el secreto ECDH para el push.');
        }

        // RFC 8291 seccion 3.4: combina el secreto ECDH con el "auth secret"
        // de la suscripcion, atado a las dos llaves publicas (contexto).
        $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
        $ikm = hash_hkdf('sha256', $ecdhSecret, 32, $keyInfo, $authSecret);

        $salt = random_bytes(16);

        // RFC 8188: deriva la llave de cifrado (CEK) y el nonce a partir del
        // IKM anterior y una sal aleatoria por mensaje.
        $prk = hash_hkdf('sha256', $ikm, 32, '', $salt);
        $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\x00", '');
        $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\x00", '');

        // Un solo registro (0x02 = delimitador de "ultimo registro", sin
        // relleno extra): suficiente para los mensajes cortos que mandamos.
        $plaintext = $payload . "\x02";

        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('No se pudo cifrar el mensaje de push.');
        }

        $recordSize = pack('N', 4096);
        $keyIdLen = pack('C', strlen($asPublicRaw));

        return $salt . $recordSize . $keyIdLen . $asPublicRaw . $ciphertext . $tag;
    }

    private function generarJwtVapid(string $origen): string
    {
        $privateKey = openssl_pkey_get_private($this->config['vapid_private_key_pem']);
        if ($privateKey === false) {
            throw new RuntimeException('La llave privada VAPID configurada no es valida.');
        }

        $header = self::b64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
        $payload = self::b64urlEncode(json_encode([
            'aud' => $origen,
            'exp' => time() + 12 * 3600,
            'sub' => $this->config['vapid_subject'],
        ], JSON_UNESCAPED_SLASHES));

        $firmaDer = '';
        openssl_sign($header . '.' . $payload, $firmaDer, $privateKey, OPENSSL_ALGO_SHA256);

        $firma = self::b64urlEncode(self::derEcdsaARaw($firmaDer));

        return $header . '.' . $payload . '.' . $firma;
    }

    private function origenDe(string $url): string
    {
        $partes = parse_url($url);
        return ($partes['scheme'] ?? 'https') . '://' . ($partes['host'] ?? '');
    }

    private static function pad32(string $bytes): string
    {
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    }

    private static function pemPublicKeyFromRawPoint(string $rawPoint): string
    {
        // Prefijo DER fijo de SubjectPublicKeyInfo para EC P-256 (secp256r1),
        // seguido del punto sin comprimir de 65 bytes (0x04 || X || Y).
        $prefijo = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $prefijo . $rawPoint;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * openssl_sign() con una llave EC regresa la firma en formato DER
     * (SEQUENCE de dos INTEGER r/s); JWS ES256 exige r y s crudos, cada uno
     * de 32 bytes, concatenados - aqui se hace esa conversion.
     */
    private static function derEcdsaARaw(string $der): string
    {
        $offset = 2; // salta SEQUENCE + su longitud
        [$r, $offset] = self::leerEnteroDer($der, $offset);
        [$s, $offset] = self::leerEnteroDer($der, $offset);

        return self::pad32($r) . self::pad32($s);
    }

    private static function leerEnteroDer(string $der, int $offset): array
    {
        if ($der[$offset] !== "\x02") {
            throw new RuntimeException('Firma ECDSA con formato DER inesperado.');
        }
        $offset++;
        $len = ord($der[$offset]);
        $offset++;
        $valor = substr($der, $offset, $len);
        $valor = ltrim($valor, "\x00");

        return [$valor, $offset + $len];
    }

    private static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($data);
    }
}

class WebPushGoneException extends RuntimeException
{
}
