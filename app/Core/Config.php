<?php

namespace App\Core;

class Config
{
    private static ?array $data = null;

    public static function load(): void
    {
        if (self::$data !== null) {
            return;
        }

        $path = BASE_PATH . '/config/config.php';
        if (!file_exists($path)) {
            $path = BASE_PATH . '/config/config.example.php';
        }

        self::$data = require $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        $segments = explode('.', $key);
        $value = self::$data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
