<?php

namespace App\Models;

use App\Core\Database;

class Commission
{
    public static function createForOrder(int $orderId, int $sellerId, float $orderTotal, float $percentage): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO commissions (order_id, seller_id, percentage, amount, status)
             VALUES (:order_id, :seller_id, :percentage, :amount, "pendente")
             ON DUPLICATE KEY UPDATE percentage = VALUES(percentage), amount = VALUES(amount)'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'seller_id' => $sellerId,
            'percentage' => $percentage,
            'amount' => round($orderTotal * $percentage / 100, 2),
        ]);
    }

    public static function all(array $filters = []): array
    {
        $sql = 'SELECT c.*, u.name AS seller_name, o.order_date, o.total_value AS order_total, cl.name AS client_name
                FROM commissions c
                JOIN users u ON u.id = c.seller_id
                JOIN orders o ON o.id = c.order_id
                JOIN clients cl ON cl.id = o.client_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['seller_id'])) {
            $sql .= ' AND c.seller_id = :seller_id';
            $params['seller_id'] = $filters['seller_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND c.status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
