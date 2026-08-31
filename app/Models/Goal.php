<?php

namespace App\Models;

use App\Core\Database;

class Goal
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT g.*, u.name AS seller_name FROM goals g
             LEFT JOIN users u ON u.id = g.seller_id
             ORDER BY g.end_date DESC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM goals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Metas ativas hoje, visiveis para o usuario informado (todas se admin/gerente/supervisor; so as suas + gerais se licenciado) */
    public static function activeFor(?int $sellerId): array
    {
        $today = date('Y-m-d');
        $sql = 'SELECT g.*, u.name AS seller_name FROM goals g
                LEFT JOIN users u ON u.id = g.seller_id
                WHERE g.start_date <= :today AND g.end_date >= :today';
        $params = ['today' => $today];

        if ($sellerId !== null) {
            $sql .= ' AND (g.seller_id IS NULL OR g.seller_id = :seller_id)';
            $params['seller_id'] = $sellerId;
        }

        $sql .= ' ORDER BY g.end_date';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO goals (name, start_date, end_date, target_value, seller_id, created_by)
             VALUES (:name, :start_date, :end_date, :target_value, :seller_id, :created_by)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'target_value' => $data['target_value'],
            'seller_id' => $data['seller_id'] ?: null,
            'created_by' => $data['created_by'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM goals WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Progresso (valor vendido no periodo da meta) usando a mesma logica de Order::metrics */
    public static function progress(array $goal): array
    {
        $achieved = Order::metrics($goal['start_date'], $goal['end_date'], $goal['seller_id'] ? (int) $goal['seller_id'] : null)['total_value'];
        $target = (float) $goal['target_value'];
        $pct = $target > 0 ? min(100, round($achieved / $target * 100, 1)) : 0.0;

        return ['achieved' => $achieved, 'target' => $target, 'pct' => $pct];
    }
}
