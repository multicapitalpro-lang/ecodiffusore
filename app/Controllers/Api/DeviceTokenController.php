<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Models\DeviceToken;

/** Fase 77: registra o token de push (Expo Push Service) do aparelho -- chamado pelo app logo
 *  apos o login, sempre que o usuario autoriza notificacoes. */
class DeviceTokenController
{
    public function register(): void
    {
        $user = ApiAuth::requireUser();

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = trim((string) ($body['token'] ?? ''));
        $platform = trim((string) ($body['platform'] ?? '')) ?: null;

        if ($token === '') {
            ApiResponse::error('token obrigatorio.', 422);
        }

        DeviceToken::register((int) $user['id'], $token, $platform);

        ApiResponse::json(['ok' => true]);
    }
}
