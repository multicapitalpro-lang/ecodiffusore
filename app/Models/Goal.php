<?php

namespace App\Models;

use App\Core\Database;

class Goal
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT g.*, u.name AS seller_name, c.name AS creator_name FROM goals g
             LEFT JOIN users u ON u.id = g.seller_id
             LEFT JOIN users c ON c.id = g.created_by
             ORDER BY g.end_date DESC'
        );
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT g.*, u.name AS seller_name, c.name AS creator_name FROM goals g
             LEFT JOIN users u ON u.id = g.seller_id
             LEFT JOIN users c ON c.id = g.created_by
             WHERE g.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Metas ativas hoje relevantes pra esse usuario: as que sao PRA ele (seller_id) ou as que
     * ELE criou pra outra pessoa (created_by) -- pra aparecer tanto pra quem precisa bater a
     * meta quanto pra quem definiu ela e quer acompanhar.
     */
    public static function activeFor(int $userId): array
    {
        $today = date('Y-m-d');
        $stmt = Database::connection()->prepare(
            'SELECT g.*, u.name AS seller_name, c.name AS creator_name FROM goals g
             LEFT JOIN users u ON u.id = g.seller_id
             LEFT JOIN users c ON c.id = g.created_by
             WHERE g.start_date <= :today1 AND g.end_date >= :today2
               AND (g.seller_id = :uid1 OR g.created_by = :uid2)
             ORDER BY g.end_date'
        );
        $stmt->execute(['today1' => $today, 'today2' => $today, 'uid1' => $userId, 'uid2' => $userId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO goals (name, start_date, end_date, target_value, metric_type, reward_description, reward_amount, seller_id, created_by)
             VALUES (:name, :start_date, :end_date, :target_value, :metric_type, :reward_description, :reward_amount, :seller_id, :created_by)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'target_value' => $data['target_value'],
            'metric_type' => ($data['metric_type'] ?? '') === 'quantidade' ? 'quantidade' : 'valor',
            'reward_description' => $data['reward_description'] ?: null,
            'reward_amount' => $data['reward_amount'] !== '' && $data['reward_amount'] !== null ? $data['reward_amount'] : null,
            'seller_id' => $data['seller_id'] ?: null,
            'created_by' => $data['created_by'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function markRewardPaid(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE goals SET reward_paid = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM goals WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Progresso somando o time todo de quem a meta e' pra -- pra um vendedor e' so ele mesmo,
     * pra licenciado/gestor/supervisor/gerente e' a rede deles (User::teamIds resolve a
     * travessia certa pra cada papel). Fase 82: meta pode ser por VALOR (R$ faturado) ou por
     * QUANTIDADE (equipamentos/placas vendidas, ja contado em Order::metrics()['products_sold']
     * -- pedido do usuario pra ficar mais exato que so olhar R$, que varia com desconto/faixa). */
    public static function progress(array $goal): array
    {
        $teamIds = $goal['seller_id'] ? User::teamIds((int) $goal['seller_id']) : null;
        $metrics = Order::metrics($goal['start_date'], $goal['end_date'], null, $teamIds);
        $achieved = ($goal['metric_type'] ?? 'valor') === 'quantidade' ? (float) $metrics['products_sold'] : $metrics['total_value'];
        $target = (float) $goal['target_value'];
        $pct = $target > 0 ? min(100, round($achieved / $target * 100, 1)) : 0.0;

        return ['achieved' => $achieved, 'target' => $target, 'pct' => $pct, 'reached' => $achieved >= $target && $target > 0];
    }
}
