<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT c.*, u.name AS seller_name FROM clients c LEFT JOIN users u ON u.id = c.seller_id ORDER BY c.name')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.name AS seller_name FROM clients c LEFT JOIN users u ON u.id = c.seller_id WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clients (user_id, name, document, person_type, state_registration, email, whatsapp, city, state, address,
                credit_limit_type, credit_limit_value, payment_terms, seller_id, status)
             VALUES (:user_id, :name, :document, :person_type, :state_registration, :email, :whatsapp, :city, :state, :address,
                :credit_limit_type, :credit_limit_value, :payment_terms, :seller_id, :status)'
        );
        $stmt->execute(self::params($data));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clients SET name = :name, document = :document, person_type = :person_type,
                state_registration = :state_registration, email = :email, whatsapp = :whatsapp, city = :city,
                state = :state, address = :address, credit_limit_type = :credit_limit_type,
                credit_limit_value = :credit_limit_value, payment_terms = :payment_terms, seller_id = :seller_id,
                status = :status
             WHERE id = :id'
        );
        $stmt->execute(array_merge(self::params($data), ['id' => $id]));
    }

    public static function linkUser(int $clientId, int $userId): void
    {
        $stmt = Database::connection()->prepare('UPDATE clients SET user_id = :user_id WHERE id = :id');
        $stmt->execute(['user_id' => $userId, 'id' => $clientId]);
    }

    public static function bulkAssignSeller(array $clientIds, ?int $sellerId): void
    {
        $clientIds = array_filter(array_map('intval', $clientIds));
        if (!$clientIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = Database::connection()->prepare("UPDATE clients SET seller_id = ? WHERE id IN ({$placeholders})");
        $stmt->execute([$sellerId, ...$clientIds]);
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE user_id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $client = $stmt->fetch();
        return $client ?: null;
    }

    private static function params(array $data): array
    {
        $creditType = in_array($data['credit_limit_type'] ?? '', ['ilimitado', 'zero', 'valor'], true)
            ? $data['credit_limit_type']
            : 'ilimitado';

        return [
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'document' => ($data['document'] ?? '') ?: null,
            'person_type' => ($data['person_type'] ?? '') === 'juridica' ? 'juridica' : 'fisica',
            'state_registration' => ($data['state_registration'] ?? '') ?: null,
            'email' => ($data['email'] ?? '') ?: null,
            'whatsapp' => ($data['whatsapp'] ?? '') ?: null,
            'city' => ($data['city'] ?? '') ?: null,
            'state' => ($data['state'] ?? '') ?: null,
            'address' => ($data['address'] ?? '') ?: null,
            'credit_limit_type' => $creditType,
            'credit_limit_value' => $creditType === 'valor' ? (($data['credit_limit_value'] ?? '') ?: null) : null,
            'payment_terms' => ($data['payment_terms'] ?? '') ?: null,
            'seller_id' => ($data['seller_id'] ?? '') ?: null,
            'status' => $data['status'] ?? 'ativo',
        ];
    }
}
