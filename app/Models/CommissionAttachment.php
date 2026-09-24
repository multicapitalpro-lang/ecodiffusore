<?php

namespace App\Models;

use App\Core\Database;

/** Comprovante de pagamento anexado ao dar baixa numa comissao (Fase 116) -- espelha
 *  FinancialAttachment 1:1, mas em tabela propria: comissao nem sempre gera um
 *  financial_transaction_id (FinancialAccount::defaultAccountId() pode vir vazio), entao nao
 *  dava pra reaproveitar a FK dura de financial_attachments pra financial_transactions. */
class CommissionAttachment
{
    public static function forCommission(int $commissionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM commission_attachments WHERE commission_id = :id ORDER BY id'
        );
        $stmt->execute(['id' => $commissionId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array> lista de anexos indexada por commission_id */
    public static function forCommissions(array $commissionIds): array
    {
        if (!$commissionIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($commissionIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM commission_attachments WHERE commission_id IN ($placeholders) ORDER BY id"
        );
        $stmt->execute(array_values($commissionIds));

        $byCommission = [];
        foreach ($stmt->fetchAll() as $row) {
            $byCommission[(int) $row['commission_id']][] = $row;
        }

        return $byCommission;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM commission_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO commission_attachments (commission_id, original_name, stored_name, mime_type, size_bytes)
             VALUES (:commission_id, :original_name, :stored_name, :mime_type, :size_bytes)'
        );
        $stmt->execute([
            'commission_id' => $data['commission_id'],
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
