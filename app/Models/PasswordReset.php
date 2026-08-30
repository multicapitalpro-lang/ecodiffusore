<?php

namespace App\Models;

use App\Core\Database;

class PasswordReset
{
    public static function createCode(int $userId, string $code): void
    {
        $db = Database::connection();

        $stmt = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL');
        $stmt->execute(['id' => $userId]);

        $stmt = $db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => password_hash($code, PASSWORD_DEFAULT),
        ]);
    }

    public static function verifyCode(int $userId, string $code): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM password_resets
             WHERE user_id = :id AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $reset = $stmt->fetch();

        if (!$reset || !password_verify($code, $reset['token_hash'])) {
            return false;
        }

        $mark = Database::connection()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $mark->execute(['id' => $reset['id']]);

        return true;
    }
}
