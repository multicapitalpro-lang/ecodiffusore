<?php

namespace App\Models;

use App\Core\Database;

class FinancialTransaction
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT ft.*, fa.name AS account_name
                FROM financial_transactions ft
                JOIN financial_accounts fa ON fa.id = ft.account_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= ' AND ft.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['account_id'])) {
            $sql .= ' AND ft.account_id = :account_id';
            $params['account_id'] = $filters['account_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND ft.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY ft.due_date DESC, ft.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO financial_transactions (account_id, order_id, type, category, description, amount, due_date, paid_date, status)
             VALUES (:account_id, :order_id, :type, :category, :description, :amount, :due_date, :paid_date, :status)'
        );
        $stmt->execute([
            'account_id' => $data['account_id'],
            'order_id' => $data['order_id'] ?? null,
            'type' => $data['type'],
            'category' => $data['category'],
            'description' => $data['description'] ?: null,
            'amount' => $data['amount'],
            'due_date' => $data['due_date'],
            'paid_date' => $data['paid_date'] ?? null,
            'status' => $data['status'] ?? 'pendente',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function createForOrderReceivable(int $orderId, int $accountId, float $amount, string $dueDate): void
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM financial_transactions WHERE order_id = :order_id AND type = 'entrada' LIMIT 1"
        );
        $stmt->execute(['order_id' => $orderId]);
        if ($stmt->fetch()) {
            return;
        }

        self::create([
            'account_id' => $accountId,
            'order_id' => $orderId,
            'type' => 'entrada',
            'category' => 'Venda',
            'description' => 'Recebimento referente ao pedido #' . $orderId,
            'amount' => $amount,
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'pago',
        ]);
    }

    public static function markPaid(int $id, string $paidDate): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE financial_transactions SET status = 'pago', paid_date = :paid_date WHERE id = :id"
        );
        $stmt->execute(['paid_date' => $paidDate, 'id' => $id]);
    }
}
