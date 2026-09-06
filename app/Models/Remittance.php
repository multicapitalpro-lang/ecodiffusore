<?php

namespace App\Models;

use App\Core\Database;

class Remittance
{
    /** $createdBy: quando informado, mostra so as remessas criadas por essa pessoa (Gestor/
     *  Licenciado nao devem ver/gerenciar remessa de outra rede) -- null = todas (Admin). */
    public static function all(?int $createdBy = null): array
    {
        $sql = 'SELECT r.*, fa.name AS account_name, u.name AS created_by_name,
                    (SELECT COUNT(*) FROM remittance_items ri WHERE ri.remittance_id = r.id) AS items_count,
                    (SELECT COALESCE(SUM(ft.amount), 0) FROM remittance_items ri
                        JOIN financial_transactions ft ON ft.id = ri.transaction_id
                        WHERE ri.remittance_id = r.id) AS total_amount
             FROM remittances r
             JOIN financial_accounts fa ON fa.id = r.account_id
             LEFT JOIN users u ON u.id = r.created_by';
        $params = [];
        if ($createdBy !== null) {
            $sql .= ' WHERE r.created_by = :created_by';
            $params['created_by'] = $createdBy;
        }
        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, fa.name AS account_name
             FROM remittances r JOIN financial_accounts fa ON fa.id = r.account_id
             WHERE r.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function items(int $remittanceId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ft.*, cl.name AS client_name
             FROM remittance_items ri
             JOIN financial_transactions ft ON ft.id = ri.transaction_id
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ri.remittance_id = :id'
        );
        $stmt->execute(['id' => $remittanceId]);
        return $stmt->fetchAll();
    }

    /** Titulos pendentes elegiveis pra entrar numa remessa nova (ainda nao vinculados a nenhuma).
     *  $sellerIds: escopo por rede (mesma condicao EXISTS de FinancialTransaction::all()) -- null =
     *  sem escopo (Admin/Gestor global). */
    public static function eligibleTransactions(string $type, int $accountId, ?array $sellerIds = null): array
    {
        $params = ['type' => $type, 'account_id' => $accountId];
        $scopeSql = '';
        if ($sellerIds !== null) {
            $orderNames = [];
            $clientNames = [];
            foreach (array_values($sellerIds) as $i => $sid) {
                $orderKey = "eosid{$i}";
                $clientKey = "ecsid{$i}";
                $orderNames[] = ":{$orderKey}";
                $clientNames[] = ":{$clientKey}";
                $params[$orderKey] = $sid;
                $params[$clientKey] = $sid;
            }
            $orderIn = implode(',', $orderNames);
            $clientIn = implode(',', $clientNames);
            $scopeSql = " AND (
                EXISTS (SELECT 1 FROM orders o2 WHERE o2.id = ft.order_id AND o2.seller_id IN ({$orderIn}))
                OR EXISTS (SELECT 1 FROM clients c2 WHERE c2.id = ft.client_id AND c2.seller_id IN ({$clientIn}))
            )";
        }

        $stmt = Database::connection()->prepare(
            "SELECT ft.*, cl.name AS client_name
             FROM financial_transactions ft
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.type = :type AND ft.account_id = :account_id AND ft.status = 'pendente'
                AND ft.id NOT IN (SELECT transaction_id FROM remittance_items) {$scopeSql}
             ORDER BY ft.due_date"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data, array $transactionIds): int
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO remittances (account_id, type, payment_method, created_by)
                 VALUES (:account_id, :type, :payment_method, :created_by)'
            );
            $stmt->execute([
                'account_id' => $data['account_id'],
                'type' => $data['type'],
                'payment_method' => $data['payment_method'] ?: null,
                'created_by' => $data['created_by'],
            ]);
            $remittanceId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO remittance_items (remittance_id, transaction_id) VALUES (:remittance_id, :transaction_id)'
            );
            foreach ($transactionIds as $transactionId) {
                $itemStmt->execute(['remittance_id' => $remittanceId, 'transaction_id' => (int) $transactionId]);
            }

            $db->commit();
            return $remittanceId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function markSent(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE remittances SET status = 'enviada', sent_at = NOW() WHERE id = :id AND status = 'aberta'"
        );
        $stmt->execute(['id' => $id]);
    }

    public static function markReturned(int $id): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                "UPDATE remittances SET status = 'retornada', returned_at = NOW() WHERE id = :id AND status = 'enviada'"
            );
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() > 0) {
                $today = date('Y-m-d');
                $update = $db->prepare(
                    "UPDATE financial_transactions ft
                     JOIN remittance_items ri ON ri.transaction_id = ft.id
                     SET ft.status = 'pago', ft.paid_date = :today
                     WHERE ri.remittance_id = :id AND ft.status = 'pendente'"
                );
                $update->execute(['id' => $id, 'today' => $today]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
