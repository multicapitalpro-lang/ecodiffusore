<?php

namespace App\Models;

use App\Core\Database;

class Approval
{
    /** % de desconto dado nos itens: (preco de tabela - preco real) / preco de tabela * 100 */
    public static function discountPct(array $items): float
    {
        $reference = 0.0;
        $real = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $real += $quantity * (float) $item['unit_price'];

            $product = Product::find((int) $item['product_id']);
            $reference += $quantity * (float) ($product['price_cash'] ?? $item['unit_price']);
        }

        if ($reference <= 0) {
            return 0.0;
        }

        return round(max(0, ($reference - $real) / $reference * 100), 2);
    }

    /**
     * Verifica o desconto contra o limite do vendedor; se passar do limite, cria (ou atualiza) uma
     * pendencia de aprovacao pra esse pedido/orcamento. Sem limite configurado (NULL) = sem checagem.
     */
    public static function checkAndRequest(string $type, int $id, array $items, ?int $sellerId, int $requestedBy): void
    {
        if (!$sellerId) {
            return;
        }

        $seller = User::find($sellerId);
        $limit = $seller['discount_limit_pct'] ?? null;
        if ($limit === null) {
            return;
        }

        $discount = self::discountPct($items);

        if ($discount <= (float) $limit) {
            // Desconto dentro do limite: se havia uma pendencia antiga (pedido editado pra baixar o desconto), limpa.
            self::clearPendingFor($type, $id);
            return;
        }

        $existing = self::pendingFor($type, $id);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE approvals SET requested_discount_pct = :pct WHERE id = :id'
            );
            $stmt->execute(['pct' => $discount, 'id' => $existing['id']]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO approvals (approvable_type, approvable_id, requested_discount_pct, status, requested_by)
             VALUES (:type, :id, :pct, "pendente", :by)'
        );
        $stmt->execute(['type' => $type, 'id' => $id, 'pct' => $discount, 'by' => $requestedBy]);
    }

    public static function pendingFor(string $type, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM approvals WHERE approvable_type = :type AND approvable_id = :id AND status = 'pendente'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['type' => $type, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function clearPendingFor(string $type, int $id): void
    {
        $stmt = Database::connection()->prepare(
            "DELETE FROM approvals WHERE approvable_type = :type AND approvable_id = :id AND status = 'pendente'"
        );
        $stmt->execute(['type' => $type, 'id' => $id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM approvals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function decide(int $id, string $status, int $decidedBy): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE approvals SET status = :status, decided_by = :by, decided_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'by' => $decidedBy, 'id' => $id]);
    }
}
