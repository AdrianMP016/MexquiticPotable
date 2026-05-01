<?php

class ReciboQr
{
    private const PREFIX = 'MXQ1';

    public static function buildToken(int $usuarioId, int $lecturaId, string $fechaRegistro, string $secret): string
    {
        $fecha = self::normalizarFecha($fechaRegistro);
        $payload = $usuarioId . '|' . $lecturaId . '|' . $fecha;
        $encoded = self::base64UrlEncode($payload);
        $signature = substr(hash_hmac('sha256', $encoded, $secret), 0, 24);

        return self::PREFIX . '.' . $encoded . '.' . $signature;
    }

    public static function parseToken(string $token, string $secret): array
    {
        $token = trim($token);
        $parts = explode('.', $token);

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            throw new RuntimeException('El QR no corresponde a un recibo valido de Mexquitic.');
        }

        [$prefix, $encoded, $signature] = $parts;
        $expected = substr(hash_hmac('sha256', $encoded, $secret), 0, 24);

        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('El QR del recibo no es valido o fue alterado.');
        }

        $decoded = self::base64UrlDecode($encoded);
        $payload = explode('|', $decoded);

        if (count($payload) !== 3) {
            throw new RuntimeException('El QR no tiene el formato esperado.');
        }

        $usuarioId = (int) $payload[0];
        $lecturaId = (int) $payload[1];
        $fechaRegistro = self::normalizarFecha($payload[2]);

        if ($usuarioId <= 0 || $lecturaId <= 0) {
            throw new RuntimeException('El QR no contiene una referencia valida.');
        }

        return [
            'usuario_id' => $usuarioId,
            'lectura_id' => $lecturaId,
            'fecha_registro' => $fechaRegistro,
            'token' => $token,
        ];
    }

    private static function normalizarFecha(string $value): string
    {
        $value = trim(str_replace('T', ' ', $value));
        $timestamp = strtotime($value);

        if ($timestamp === false) {
            throw new RuntimeException('El QR contiene una fecha de registro invalida.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            throw new RuntimeException('El QR contiene datos corruptos.');
        }

        return $decoded;
    }
}
