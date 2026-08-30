<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM clients ORDER BY name')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clients (user_id, name, document, email, whatsapp, city, state, address, status)
             VALUES (:user_id, :name, :document, :email, :whatsapp, :city, :state, :address, :status)'
        );
        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'document' => $data['document'] ?: null,
            'email' => $data['email'] ?: null,
            'whatsapp' => $data['whatsapp'] ?: null,
            'city' => $data['city'] ?: null,
            'state' => $data['state'] ?: null,
            'address' => $data['address'] ?: null,
            'status' => $data['status'] ?? 'ativo',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clients SET name = :name, document = :document, email = :email,
                whatsapp = :whatsapp, city = :city, state = :state, address = :address, status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'document' => $data['document'] ?: null,
            'email' => $data['email'] ?: null,
            'whatsapp' => $data['whatsapp'] ?: null,
            'city' => $data['city'] ?: null,
            'state' => $data['state'] ?: null,
            'address' => $data['address'] ?: null,
            'status' => $data['status'] ?? 'ativo',
        ]);
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $client = $stmt->fetch();
        return $client ?: null;
    }
}
