<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Order
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT o.*, c.name AS client_name, u.name AS seller_name
                FROM orders o
                JOIN clients c ON c.id = o.client_id
                LEFT JOIN users u ON u.id = o.seller_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND o.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND o.order_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND o.order_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['seller_id'])) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $filters['seller_id'];
        }
        if (!empty($filters['client_id'])) {
            $sql .= ' AND o.client_id = :client_id';
            $params['client_id'] = $filters['client_id'];
        }

        $sql .= ' ORDER BY o.order_date DESC, o.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.*, c.name AS client_name, u.name AS seller_name
             FROM orders o
             JOIN clients c ON c.id = o.client_id
             LEFT JOIN users u ON u.id = o.seller_id
             WHERE o.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();
        return $order ?: null;
    }

    public static function create(array $data, array $items): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO orders (client_id, seller_id, status, order_date, total_value, notes)
                 VALUES (:client_id, :seller_id, :status, :order_date, 0, :notes)'
            );
            $stmt->execute([
                'client_id' => $data['client_id'],
                'seller_id' => $data['seller_id'] ?: null,
                'status' => $data['status'] ?? 'em_andamento',
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?: null,
            ]);
            $orderId = (int) $db->lastInsertId();

            foreach ($items as $item) {
                OrderItem::create($orderId, (int) $item['product_id'], (int) $item['quantity'], (float) $item['unit_price']);
            }

            self::recalculateTotal($orderId);
            $db->commit();

            return $orderId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateHeaderAndItems(int $id, array $data, array $items): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'UPDATE orders SET client_id = :client_id, seller_id = :seller_id,
                    order_date = :order_date, notes = :notes WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'client_id' => $data['client_id'],
                'seller_id' => $data['seller_id'] ?: null,
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?: null,
            ]);

            OrderItem::deleteForOrder($id);
            foreach ($items as $item) {
                OrderItem::create($id, (int) $item['product_id'], (int) $item['quantity'], (float) $item['unit_price']);
            }

            self::recalculateTotal($id);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function recalculateTotal(int $id): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT COALESCE(SUM(subtotal), 0) FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $id]);
        $total = (float) $stmt->fetchColumn();

        $update = $db->prepare('UPDATE orders SET total_value = :total WHERE id = :id');
        $update->execute(['total' => $total, 'id' => $id]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Marca o pedido como verificado e roda a mesma rotina de sempre: comissao em cascata
     * (Commission::createCascadeForOrder) + lancamento em Contas a Receber. Usado tanto pelo
     * botao manual "Marcar como Verificado" quanto pelo webhook do Asaas quando o cliente paga.
     */
    public static function markVerifiedWithCommission(int $id): void
    {
        $order = self::find($id);
        if (!$order || $order['status'] === 'verificado') {
            return;
        }

        self::updateStatus($id, 'verificado');

        if ($order['seller_id']) {
            Commission::createCascadeForOrder($id, (int) $order['seller_id'], (float) $order['total_value']);
        }

        $accountId = FinancialAccount::defaultAccountId();
        if ($accountId) {
            FinancialTransaction::createForOrderReceivable($id, $accountId, (float) $order['total_value'], date('Y-m-d'));
        }
    }

    public static function metrics(string $from, string $to, ?int $sellerId = null): array
    {
        $sql = 'SELECT COUNT(*) AS order_count, COALESCE(SUM(total_value), 0) AS total_value,
                    COALESCE(SUM((SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = o.id)), 0) AS products_sold
                FROM orders o
                WHERE o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        $orderCount = (int) $row['order_count'];
        $totalValue = (float) $row['total_value'];

        return [
            'order_count' => $orderCount,
            'total_value' => $totalValue,
            'products_sold' => (int) $row['products_sold'],
            'ticket_medio' => $orderCount > 0 ? $totalValue / $orderCount : 0.0,
        ];
    }

    public static function costTotal(string $from, string $to, ?int $sellerId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(oi.quantity * p.cost_price), 0)
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND o.seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    public static function dailySeries(string $from, string $to, ?int $sellerId = null): array
    {
        $sql = 'SELECT order_date, SUM(total_value) AS total
                FROM orders
                WHERE order_date BETWEEN :from AND :to AND status != \'cancelado\'';
        $params = ['from' => $from, 'to' => $to];

        if ($sellerId !== null) {
            $sql .= ' AND seller_id = :seller_id';
            $params['seller_id'] = $sellerId;
        }

        $sql .= ' GROUP BY order_date';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $series = [];
        foreach ($stmt->fetchAll() as $row) {
            $series[$row['order_date']] = (float) $row['total'];
        }

        return $series;
    }

    public static function sellerRanking(string $from, string $to): array
    {
        $sql = 'SELECT u.id AS seller_id, u.name,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(o.total_value), 0) AS total_value,
                    COALESCE((SELECT SUM(c.amount) FROM commissions c WHERE c.seller_id = u.id
                        AND c.order_id IN (SELECT id FROM orders WHERE order_date BETWEEN :from2 AND :to2)), 0) AS commission_total
                FROM users u
                JOIN roles r ON r.id = u.role_id AND r.slug = \'licenciado\'
                LEFT JOIN orders o ON o.seller_id = u.id AND o.order_date BETWEEN :from AND :to AND o.status != \'cancelado\'
                GROUP BY u.id
                ORDER BY total_value DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['from' => $from, 'to' => $to, 'from2' => $from, 'to2' => $to]);
        return $stmt->fetchAll();
    }
}
