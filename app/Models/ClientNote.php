<?php

namespace App\Models;

use App\Core\Database;

class ClientNote
{
    public static function forClient(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cn.*, u.name AS user_name FROM client_notes cn
             LEFT JOIN users u ON u.id = cn.user_id
             WHERE cn.client_id = :id ORDER BY cn.created_at DESC'
        );
        $stmt->execute(['id' => $clientId]);
        return $stmt->fetchAll();
    }

    public static function create(int $clientId, int $userId, string $note): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO client_notes (client_id, user_id, note) VALUES (:client_id, :user_id, :note)'
        );
        $stmt->execute(['client_id' => $clientId, 'user_id' => $userId, 'note' => $note]);
    }
}
