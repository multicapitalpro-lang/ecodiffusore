<?php

namespace App\Models;

use App\Core\Database;

class WarrantyRequest
{
    public static function create(int $orderId, int $clientId, string $description, ?array $attachment): int
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO warranty_requests (order_id, client_id, description, attachment_path, attachment_original_name, status)
             VALUES (:order_id, :client_id, :description, :attachment_path, :attachment_original_name, 'aberta')"
        );
        $stmt->execute([
            'order_id' => $orderId,
            'client_id' => $clientId,
            'description' => $description,
            'attachment_path' => $attachment['stored_name'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.*, o.order_date, o.total_value, o.seller_id, c.name AS client_name, u.name AS resolved_by_name
             FROM warranty_requests w
             JOIN orders o ON o.id = w.order_id
             JOIN clients c ON c.id = w.client_id
             LEFT JOIN users u ON u.id = w.resolved_by_user_id
             WHERE w.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Todas as garantias de um cliente (portal do cliente). */
    public static function forClient(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT w.*, o.order_date FROM warranty_requests w
             JOIN orders o ON o.id = w.order_id
             WHERE w.client_id = :client_id ORDER BY w.created_at DESC'
        );
        $stmt->execute(['client_id' => $clientId]);
        return $stmt->fetchAll();
    }

    public static function forOrder(int $orderId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM warranty_requests WHERE order_id = :order_id ORDER BY created_at DESC');
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /** Fila de garantias no escopo de staff -- $sellerIds: null = sem escopo (Admin), [] = nada no
     *  escopo, senao filtra pelo vendedor do pedido. Mesmo espirito de Order/Client::all(). */
    public static function forScope(?array $sellerIds, ?string $status = null): array
    {
        $sql = 'SELECT w.*, o.order_date, o.seller_id, c.name AS client_name, u.name AS seller_name
                FROM warranty_requests w
                JOIN orders o ON o.id = w.order_id
                JOIN clients c ON c.id = w.client_id
                LEFT JOIN users u ON u.id = o.seller_id
                WHERE 1=1';
        $params = [];

        if ($sellerIds !== null) {
            if (!$sellerIds) {
                return [];
            }
            $names = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $key = "sid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $sid;
            }
            $sql .= ' AND o.seller_id IN (' . implode(',', $names) . ')';
        }
        if ($status) {
            $sql .= ' AND w.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY w.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus(int $id, string $status, ?string $resolutionNote, int $resolvedByUserId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE warranty_requests SET status = :status, resolution_note = :note,
                resolved_by_user_id = :resolved_by, resolved_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => $status,
            'note' => $resolutionNote !== '' ? $resolutionNote : null,
            'resolved_by' => $resolvedByUserId,
            'id' => $id,
        ]);
    }
}
