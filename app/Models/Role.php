<?php

namespace App\Models;

use App\Core\Database;

class Role
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM roles ORDER BY id')
            ->fetchAll();
    }

    public static function idBySlug(string $slug): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }
}
