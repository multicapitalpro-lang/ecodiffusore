<?php

namespace App\Models;

use App\Core\Database;

class Commission
{
    /**
     * Cria uma linha de comissao para o vendedor e, subindo a hierarquia (manager_id),
     * uma linha adicional para cada supervisor/gerente com commission_pct > 0.
     * Cada nivel usa o proprio commission_pct da pessoa, nao um valor fixo por papel.
     */
    public static function createCascadeForOrder(int $orderId, int $sellerId, float $orderTotal): void
    {
        $seller = User::find($sellerId);
        if (!$seller) {
            return;
        }

        $beneficiaries = [$seller, ...User::managerChain($sellerId)];

        foreach ($beneficiaries as $i => $beneficiary) {
            if ($beneficiary['commission_pct'] !== null) {
                $pct = (float) $beneficiary['commission_pct'];
            } else {
                // Compatibilidade com o comportamento anterior: vendedor sem % configurado ganha 5% padrao.
                // Supervisor/gerente sem % configurado nao ganham nada (precisa ser definido explicitamente pelo admin).
                $pct = $i === 0 ? 5.0 : 0.0;
            }

            if ($pct <= 0) {
                continue;
            }

            self::createForOrder($orderId, $sellerId, (int) $beneficiary['id'], $beneficiary['role_slug'], $orderTotal, $pct);
        }
    }

    public static function createForOrder(int $orderId, int $sellerId, int $beneficiaryId, string $roleSlug, float $orderTotal, float $percentage): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO commissions (order_id, seller_id, beneficiary_id, role_slug, percentage, amount, status)
             VALUES (:order_id, :seller_id, :beneficiary_id, :role_slug, :percentage, :amount, 'pendente')
             ON DUPLICATE KEY UPDATE percentage = VALUES(percentage), amount = VALUES(amount)"
        );
        $stmt->execute([
            'order_id' => $orderId,
            'seller_id' => $sellerId,
            'beneficiary_id' => $beneficiaryId,
            'role_slug' => $roleSlug,
            'percentage' => $percentage,
            'amount' => round($orderTotal * $percentage / 100, 2),
        ]);
    }

    public static function all(array $filters = []): array
    {
        $sql = 'SELECT c.*, b.name AS beneficiary_name, s.name AS seller_name,
                    o.order_date, o.total_value AS order_total, cl.name AS client_name
                FROM commissions c
                JOIN users b ON b.id = c.beneficiary_id
                JOIN users s ON s.id = c.seller_id
                JOIN orders o ON o.id = c.order_id
                JOIN clients cl ON cl.id = o.client_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['beneficiary_id'])) {
            $sql .= ' AND c.beneficiary_id = :beneficiary_id';
            $params['beneficiary_id'] = $filters['beneficiary_id'];
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

    public static function markPaid(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE commissions SET status = 'pago', paid_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    public static function byBeneficiary(array $filters = []): array
    {
        $sql = "SELECT u.id AS beneficiary_id, u.name,
                    COUNT(c.id) AS count_total,
                    COALESCE(SUM(c.amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN c.status = 'pago' THEN c.amount ELSE 0 END), 0) AS total_pago,
                    COALESCE(SUM(CASE WHEN c.status = 'pendente' THEN c.amount ELSE 0 END), 0) AS total_pendente
                FROM commissions c
                JOIN users u ON u.id = c.beneficiary_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['beneficiary_id'])) {
            $sql .= ' AND c.beneficiary_id = :beneficiary_id';
            $params['beneficiary_id'] = $filters['beneficiary_id'];
        }

        $sql .= ' GROUP BY u.id ORDER BY total DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
