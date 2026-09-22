<?php

namespace App\Models;

use App\Core\Database;

class Payment
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO payments (payable_type, payable_id, asaas_customer_id, asaas_charge_id, method, amount, status, checkout_url, pix_payload, due_date, installments)
             VALUES (:payable_type, :payable_id, :asaas_customer_id, :asaas_charge_id, :method, :amount, :status, :checkout_url, :pix_payload, :due_date, :installments)'
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
            // Fase 81: quantidade de parcelas escolhida pelo comprador -- pedido explicito do
            // usuario pra aparecer no card do Kanban, antes so ia pra Asaas e nao ficava salvo
            // aqui (Payment::create() nao tinha essa coluna).
            'installments' => $data['installments'] ?? null,
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

    public static function markRefunded(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE payments SET status = 'reembolsado' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /** O pagamento mais recente de cada payable_id, num unico round-trip -- usado pra listar
     * pedidos sem fazer N+1 (Payment::forPayable() por pedido). */
    public static function latestByPayableIds(string $type, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT p.* FROM payments p
                INNER JOIN (
                    SELECT payable_id, MAX(created_at) AS max_created
                    FROM payments
                    WHERE payable_type = ? AND payable_id IN ({$placeholders})
                    GROUP BY payable_id
                ) latest ON latest.payable_id = p.payable_id AND latest.max_created = p.created_at
                WHERE p.payable_type = ?";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$type], $ids, [$type]));

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $byId[(int) $row['payable_id']] = $row;
        }
        return $byId;
    }

    /**
     * Situacao de pagamento pra exibicao (Pago/Pendente/Expirado/Cancelado/Reembolsado) -- so
     * decorativo, nao mexe em orders.status nem na logica de comissao/verificacao. "Expirado" e
     * calculado na hora (due_date vencida), ja que nao existe cron que atualize isso sozinho.
     */
    public static function situationFor(array $order, ?array $payment, string $cancelledStatus = 'cancelado'): array
    {
        if ($order['status'] === $cancelledStatus) {
            return ['slug' => 'cancelado', 'label' => 'Cancelado', 'badge' => 'inactive'];
        }
        if (!$payment) {
            return ['slug' => 'pendente', 'label' => 'Pendente de pagamento', 'badge' => 'novo'];
        }
        if ($payment['status'] === 'pago') {
            return ['slug' => 'pago', 'label' => 'Pago', 'badge' => 'active'];
        }
        if ($payment['status'] === 'reembolsado') {
            return ['slug' => 'reembolsado', 'label' => 'Reembolsado', 'badge' => 'inactive'];
        }
        if ($payment['status'] === 'cancelado') {
            return ['slug' => 'cancelado', 'label' => 'Cancelado', 'badge' => 'inactive'];
        }

        $isExpired = $payment['status'] === 'vencido'
            || (($payment['due_date'] ?? null) && strtotime($payment['due_date']) < strtotime(date('Y-m-d')));

        return $isExpired
            ? ['slug' => 'expirado', 'label' => 'Expirado', 'badge' => 'inactive']
            : ['slug' => 'pendente', 'label' => 'Pendente de pagamento', 'badge' => 'novo'];
    }
}
