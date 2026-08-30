<?php

namespace App\Models;

use App\Core\Database;

class Lead
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO leads (name, whatsapp, city, truck_brand, message, source)
             VALUES (:name, :whatsapp, :city, :truck_brand, :message, :source)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'whatsapp' => $data['whatsapp'],
            'city' => $data['city'] ?: null,
            'truck_brand' => $data['truck_brand'] ?: null,
            'message' => $data['message'] ?: null,
            'source' => $data['source'] ?? 'landing_page',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT l.*, u.name AS assigned_name FROM leads l
                      LEFT JOIN users u ON u.id = l.assigned_to_user_id
                      ORDER BY l.created_at DESC')
            ->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM leads WHERE assigned_to_user_id = :id ORDER BY created_at DESC'
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    }
}
