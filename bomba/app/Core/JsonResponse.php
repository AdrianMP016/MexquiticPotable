<?php

class JsonResponse
{
    public static function success(string $message, array $data = [], int $statusCode = 200): void
    {
        self::send(true, $message, $data, [], $statusCode);
    }

    public static function error(string $message, array $errors = [], int $statusCode = 400): void
    {
        self::send(false, $message, [], $errors, $statusCode);
    }

    private static function send(bool $success, string $message, array $data, array $errors, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}
