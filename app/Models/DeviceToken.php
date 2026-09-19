<?php

namespace App\Models;

use App\Core\Database;

/** Fase 77: token de push (Expo Push Service) por dispositivo -- ver App\Core\PushClient e
 *  App\Core\Notifier::sendPush(). Um token so pertence a 1 usuario por vez (ON DUPLICATE KEY
 *  reatribui se o mesmo aparelho logar com outra conta). */
class DeviceToken
{
    public static function register(int $userId, string $token, ?string $platform): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO device_tokens (user_id, expo_push_token, platform, last_used_at)
             VALUES (:user_id, :token, :platform, NOW())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform), last_used_at = NOW()'
        );
        $stmt->execute(['user_id' => $userId, 'token' => $token, 'platform' => $platform]);
    }

    /** @return string[] */
    public static function tokensForUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT expo_push_token FROM device_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'expo_push_token');
    }

    public static function remove(string $token): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM device_tokens WHERE expo_push_token = :token');
        $stmt->execute(['token' => $token]);
    }
}
