<?php

namespace App\Models;

use App\Core\Database;

class FinancialAttachment
{
    public static function forTransaction(int $transactionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM financial_attachments WHERE transaction_id = :id ORDER BY id'
        );
        $stmt->execute(['id' => $transactionId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM financial_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO financial_attachments (transaction_id, original_name, stored_name, mime_type, size_bytes)
             VALUES (:transaction_id, :original_name, :stored_name, :mime_type, :size_bytes)'
        );
        $stmt->execute([
            'transaction_id' => $data['transaction_id'],
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
