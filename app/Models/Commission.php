<?php

namespace App\Models;

use App\Core\Database;

class Commission
{
    /**
     * Duas cascatas independentes disparam a partir do mesmo Licenciado:
     *
     * 1) Pool regional: o Licenciado (dono da regiao) recebe um % fixo contratual sobre o total
     *    do pedido -- isso forma o "pool". A partir do pool, Gestor e Vendedor recebem o % que o
     *    proprio Licenciado configurou pra cada um (commission_pct deles = % do pool, nao % do
     *    pedido). O que sobra do pool fica com o Licenciado. Sem % contratual definido, nao ha
     *    pool e ninguem desse nivel recebe.
     *
     * 2) Comissao nacional: se o Licenciado tiver um Supervisor atribuido (supervisor_id, definido
     *    pelo Gerente em /painel/licenciados), Supervisor e Gerente recebem um % do TOTAL do
     *    pedido -- paga direto pela Ecodiffusore, nunca sai do pool acima. Por isso roda num bloco
     *    totalmente a parte, mesmo se o Licenciado nao tiver pool configurado ainda.
     */
    public static function createCascadeForOrder(int $orderId, int $sellerId, float $orderTotal): void
    {
        $vendedor = User::find($sellerId);
        if (!$vendedor) {
            return;
        }

        $chain = [$vendedor, ...User::managerChain($sellerId)];

        $licenciado = null;
        foreach ($chain as $p) {
            if ($p['role_slug'] === 'licenciado') {
                $licenciado = $p;
                break;
            }
        }

        if ($licenciado && (float) ($licenciado['commission_pct'] ?? 0) > 0) {
            $pool = round($orderTotal * (float) $licenciado['commission_pct'] / 100, 2);
            $distribuido = 0.0;

            foreach ($chain as $p) {
                if ((int) $p['id'] === (int) $licenciado['id']) {
                    continue;
                }

                $sharePct = (float) ($p['commission_pct'] ?? 0);
                if ($sharePct <= 0) {
                    continue;
                }

                $amount = round($pool * $sharePct / 100, 2);
                $distribuido += $amount;
                self::insertRow($orderId, $sellerId, (int) $p['id'], $p['role_slug'], $sharePct, $amount);
            }

            $restante = max(0, round($pool - $distribuido, 2));
            if ($restante > 0) {
                self::insertRow($orderId, $sellerId, (int) $licenciado['id'], 'licenciado', (float) $licenciado['commission_pct'], $restante);
            }
        }

        if ($licenciado && !empty($licenciado['supervisor_id'])) {
            $supervisor = User::find((int) $licenciado['supervisor_id']);

            if ($supervisor && $supervisor['role_slug'] === 'supervisor') {
                if ((float) ($supervisor['commission_pct'] ?? 0) > 0) {
                    $pct = (float) $supervisor['commission_pct'];
                    self::insertRow($orderId, $sellerId, (int) $supervisor['id'], 'supervisor', $pct, round($orderTotal * $pct / 100, 2));
                }

                if (!empty($supervisor['manager_id'])) {
                    $gerente = User::find((int) $supervisor['manager_id']);
                    if ($gerente && $gerente['role_slug'] === 'gerente' && (float) ($gerente['commission_pct'] ?? 0) > 0) {
                        $pct = (float) $gerente['commission_pct'];
                        self::insertRow($orderId, $sellerId, (int) $gerente['id'], 'gerente', $pct, round($orderTotal * $pct / 100, 2));
                    }
                }
            }
        }
    }

    /**
     * `percentage` guardado aqui significa coisas diferentes por papel: pro Licenciado e o %
     * contratual sobre o total do pedido; pro Gestor/Vendedor e o % do pool do Licenciado; pro
     * Supervisor/Gerente e o % do total do pedido pago direto pela Ecodiffusore (fora do pool).
     */
    private static function insertRow(int $orderId, int $sellerId, int $beneficiaryId, string $roleSlug, float $percentage, float $amount): void
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
            'amount' => $amount,
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
        } elseif (!empty($filters['beneficiary_ids'])) {
            $names = [];
            foreach (array_values($filters['beneficiary_ids']) as $i => $bid) {
                $key = "bid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $bid;
            }
            $sql .= ' AND c.beneficiary_id IN (' . implode(',', $names) . ')';
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
        } elseif (!empty($filters['beneficiary_ids'])) {
            $names = [];
            foreach (array_values($filters['beneficiary_ids']) as $i => $bid) {
                $key = "bid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $bid;
            }
            $sql .= ' AND c.beneficiary_id IN (' . implode(',', $names) . ')';
        }

        $sql .= ' GROUP BY u.id ORDER BY total DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
