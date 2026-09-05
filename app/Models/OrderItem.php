<?php

namespace App\Models;

use App\Core\Database;

class OrderItem
{
    public static function forOrder(int $orderId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT oi.*, p.name AS product_name, p.price_cash, p.price_high
             FROM order_items oi JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id'
        );
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public static function create(int $orderId, int $productId, int $quantity, float $unitPrice): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
             VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal)'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $quantity,
        ]);
    }

    public static function deleteForOrder(int $orderId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM order_items WHERE order_id = :order_id');
        $stmt->execute(['order_id' => $orderId]);
    }

    public static function topProducts(string $from, string $to, ?int $sellerId = null, ?array $sellerIds = null): array
    {
        $sql = 'SELECT p.name, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS total_value
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        } elseif (!empty($sellerIds)) {
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND o.seller_id IN (' . implode(',', $names) . ')';
        }

        $sql .= ' GROUP BY p.id ORDER BY total_value DESC LIMIT 10';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
