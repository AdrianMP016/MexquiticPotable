<?php

class Request
{
    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function post(): array
    {
        return $_POST;
    }

    public static function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
