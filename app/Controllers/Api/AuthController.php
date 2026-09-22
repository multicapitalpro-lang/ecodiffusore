<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Models\ApiToken;
use App\Models\User;

/** Fase 76: primeiro pedaco da API pro futuro app mobile -- login/logout/me por token, em
 *  paralelo ao login por sessao do painel web (App\Controllers\AuthController), sem mexer nele. */
class AuthController
{
    public function login(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $deviceName = trim((string) ($body['device_name'] ?? '')) ?: null;

        if ($email === '' || $password === '') {
            ApiResponse::error('Informe email e senha.', 422);
        }

        $user = User::findByEmail($email);
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            ApiResponse::error('Credenciais invalidas.', 401);
        }

        User::touchLastLogin((int) $user['id']);
        $token = ApiToken::issue((int) $user['id'], $deviceName);

        ApiResponse::json([
            'token' => $token,
            'user' => self::publicUser($user),
        ]);
    }

    public function logout(): void
    {
        ApiAuth::requireUser();
        $token = ApiAuth::bearerToken();
        if ($token) {
            ApiToken::revoke($token);
        }

        ApiResponse::json(['ok' => true]);
    }

    public function me(): void
    {
        $user = ApiAuth::requireUser();
        ApiResponse::json(['user' => self::publicUser($user)]);
    }

    private static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_slug' => $user['role_slug'],
            'role_name' => $user['role_name'],
            'must_change_password' => !empty($user['must_change_password']),
            'must_complete_training' => $user['role_slug'] === 'vendedor' && empty($user['training_completed_at']),
        ];
    }
}
