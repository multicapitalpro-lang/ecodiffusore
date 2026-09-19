<?php

namespace App\Models;

use App\Core\Database;

/**
 * Fase 76: token de acesso pro futuro app mobile (React Native) -- espelha o padrao "personal
 * access token" (Sanctum): guarda so o HASH (sha256) do token, nunca o valor puro, que so existe
 * uma vez, na resposta do login. Nao substitui App\Core\Auth (sessao/cookie, usado pelo painel
 * web) -- e' um mecanismo paralelo, so pra App\Core\ApiAuth (chamadas em /api/v1/*).
 */
class ApiToken
{
    public static function issue(int $userId, ?string $deviceName = null): string
    {
        $plain = bin2hex(random_bytes(40));
        $hash = hash('sha256', $plain);

        $stmt = Database::connection()->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, device_name) VALUES (:user_id, :token_hash, :device_name)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
            'device_name' => $deviceName ?: null,
        ]);

        return $plain;
    }

    /** Usuario dono de um token valido (nao revogado) -- ja com role_slug/role_name via JOIN,
     *  mesmo formato de User::find(). Atualiza last_used_at (best-effort, nao trava a resposta). */
    public static function userForToken(string $plain): ?array
    {
        $hash = hash('sha256', $plain);

        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM api_tokens t
             JOIN users u ON u.id = t.user_id
             JOIN roles r ON r.id = u.role_id
             WHERE t.token_hash = :hash AND t.revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }

        $upd = Database::connection()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = :hash');
        $upd->execute(['hash' => $hash]);

        return $user;
    }

    public static function revoke(string $plain): void
    {
        $hash = hash('sha256', $plain);
        $stmt = Database::connection()->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = :hash AND revoked_at IS NULL');
        $stmt->execute(['hash' => $hash]);
    }

    public static function revokeAllForUser(int $userId): void
    {
        $stmt = Database::connection()->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = :user_id AND revoked_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
    }
}
