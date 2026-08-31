<?php

namespace App\Models;

use App\Core\Database;

class AuditLog
{
    public static function record(int $userId, string $action, ?string $entityType, ?int $entityId, mixed $before, mixed $after): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, before_value, after_value, ip)
             VALUES (:user_id, :action, :entity_type, :entity_id, :before_value, :after_value, :ip)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_value' => self::serialize($before),
            'after_value' => self::serialize($after),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function recent(int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, u.name AS user_name FROM activity_log a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
    }
}
