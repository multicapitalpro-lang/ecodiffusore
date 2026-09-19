<?php

namespace App\Core;

class ApiResponse
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Sempre encerra a requisicao (exit) -- nunca retorna, pra chamar como
     *  `ApiAuth::requireUser()`-style guard sem precisar de `return` depois no controller. */
    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }
}
