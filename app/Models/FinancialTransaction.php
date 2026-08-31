<?php

namespace App\Models;

use App\Core\Database;

class FinancialTransaction
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT ft.*, fa.name AS account_name, fc.name AS category_name, cl.name AS client_name
                FROM financial_transactions ft
                JOIN financial_accounts fa ON fa.id = ft.account_id
                LEFT JOIN financial_categories fc ON fc.id = ft.category_id
                LEFT JOIN clients cl ON cl.id = ft.client_id
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
        if (!empty($filters['client_id'])) {
            $sql .= ' AND ft.client_id = :client_id';
            $params['client_id'] = $filters['client_id'];
        }

        $sql .= ' ORDER BY ft.due_date DESC, ft.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ft.*, fa.name AS account_name, fc.name AS category_name, cl.name AS client_name
             FROM financial_transactions ft
             JOIN financial_accounts fa ON fa.id = ft.account_id
             LEFT JOIN financial_categories fc ON fc.id = ft.category_id
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO financial_transactions
                (account_id, order_id, client_id, category_id, type, description, amount,
                 issue_date, competencia, due_date, paid_date, payment_method, document_number,
                 interest_pct, penalty_pct, status)
             VALUES
                (:account_id, :order_id, :client_id, :category_id, :type, :description, :amount,
                 :issue_date, :competencia, :due_date, :paid_date, :payment_method, :document_number,
                 :interest_pct, :penalty_pct, :status)'
        );
        $stmt->execute([
            'account_id' => $data['account_id'],
            'order_id' => empty($data['order_id']) ? null : $data['order_id'],
            'client_id' => empty($data['client_id']) ? null : $data['client_id'],
            'category_id' => empty($data['category_id']) ? null : $data['category_id'],
            'type' => $data['type'],
            'description' => empty($data['description']) ? null : $data['description'],
            'amount' => $data['amount'],
            'issue_date' => empty($data['issue_date']) ? null : $data['issue_date'],
            'competencia' => empty($data['competencia']) ? null : $data['competencia'],
            'due_date' => $data['due_date'],
            'paid_date' => $data['paid_date'] ?? null,
            'payment_method' => empty($data['payment_method']) ? null : $data['payment_method'],
            'document_number' => empty($data['document_number']) ? null : $data['document_number'],
            'interest_pct' => empty($data['interest_pct']) ? 0 : $data['interest_pct'],
            'penalty_pct' => empty($data['penalty_pct']) ? 0 : $data['penalty_pct'],
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

    public static function totalValue(array $row): float
    {
        $amount = (float) $row['amount'];
        $extra = $amount * ((float) $row['interest_pct'] + (float) $row['penalty_pct']) / 100;
        return round($amount + $extra, 2);
    }
}
