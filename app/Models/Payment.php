<?php

namespace App\Models;

use App\Core\Database;

class Payment
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO payments (payable_type, payable_id, asaas_customer_id, asaas_charge_id, method, amount, status, checkout_url, pix_payload, due_date)
             VALUES (:payable_type, :payable_id, :asaas_customer_id, :asaas_charge_id, :method, :amount, :status, :checkout_url, :pix_payload, :due_date)'
        );
        $stmt->execute([
            'payable_type' => $data['payable_type'],
            'payable_id' => $data['payable_id'],
            'asaas_customer_id' => $data['asaas_customer_id'],
            'asaas_charge_id' => $data['asaas_charge_id'],
            'method' => $data['method'],
            'amount' => $data['amount'],
            'status' => $data['status'] ?? 'pendente',
            'checkout_url' => $data['checkout_url'] ?? null,
            'pix_payload' => $data['pix_payload'] ?? null,
            'due_date' => $data['due_date'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function findByChargeId(string $chargeId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM payments WHERE asaas_charge_id = :id');
        $stmt->execute(['id' => $chargeId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forPayable(string $type, int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM payments WHERE payable_type = :type AND payable_id = :id ORDER BY created_at DESC'
        );
        $stmt->execute(['type' => $type, 'id' => $id]);
        return $stmt->fetchAll();
    }

    public static function markPaid(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE payments SET status = 'pago', paid_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public static function markCancelled(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE payments SET status = 'cancelado' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
