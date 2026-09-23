<?php

namespace App\Models;

use App\Core\Database;

class FinancialTransaction
{
    public static function all(array $filters = []): array
    {
        $sql = 'SELECT ft.*, fa.name AS account_name, fc.name AS category_name, cl.name AS client_name
                FROM financial_transactions ft
                JOIN financial_accounts fa ON fa.id = ft.account_id
                LEFT JOIN financial_categories fc ON fc.id = ft.category_id
                LEFT JOIN clients cl ON cl.id = ft.client_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= ' AND ft.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['account_id'])) {
            $sql .= ' AND ft.account_id = :account_id';
            $params['account_id'] = $filters['account_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND ft.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $sql .= ' AND ft.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }
        if (!empty($filters['client_id'])) {
            $sql .= ' AND ft.client_id = :client_id';
            $params['client_id'] = $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND ft.due_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND ft.due_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['exclude_transfers'])) {
            $sql .= ' AND ft.is_transfer = 0';
        }
        if (!empty($filters['seller_ids'])) {
            // Escopo por regiao: so entra se o pedido ou o cliente vinculado pertence a alguem
            // da equipe do licenciado. Lancamento manual sem pedido/cliente nao aparece aqui
            // (limitacao aceita -- so o admin ve movimentacao de caixa solta, sem vinculo).
            $orderNames = [];
            $clientNames = [];
            foreach (array_values($filters['seller_ids']) as $i => $sid) {
                $orderKey = "osid{$i}";
                $clientKey = "csid{$i}";
                $orderNames[] = ":{$orderKey}";
                $clientNames[] = ":{$clientKey}";
                $params[$orderKey] = $sid;
                $params[$clientKey] = $sid;
            }
            $orderIn = implode(',', $orderNames);
            $clientIn = implode(',', $clientNames);
            $sql .= " AND (
                EXISTS (SELECT 1 FROM orders o2 WHERE o2.id = ft.order_id AND o2.seller_id IN ({$orderIn}))
                OR EXISTS (SELECT 1 FROM clients c2 WHERE c2.id = ft.client_id AND c2.seller_id IN ({$clientIn}))
            )";
        }

        $sql .= ' ORDER BY ft.due_date DESC, ft.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ft.*, fa.name AS account_name, fc.name AS category_name, cl.name AS client_name
             FROM financial_transactions ft
             JOIN financial_accounts fa ON fa.id = ft.account_id
             LEFT JOIN financial_categories fc ON fc.id = ft.category_id
             LEFT JOIN clients cl ON cl.id = ft.client_id
             WHERE ft.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO financial_transactions
                (account_id, order_id, client_id, category_id, type, description, amount,
                 issue_date, competencia, due_date, paid_date, payment_method, document_number,
                 interest_pct, penalty_pct, status, is_transfer, transfer_pair_id,
                 recurrence_frequency, recurrence_parent_id)
             VALUES
                (:account_id, :order_id, :client_id, :category_id, :type, :description, :amount,
                 :issue_date, :competencia, :due_date, :paid_date, :payment_method, :document_number,
                 :interest_pct, :penalty_pct, :status, :is_transfer, :transfer_pair_id,
                 :recurrence_frequency, :recurrence_parent_id)'
        );
        $stmt->execute([
            'account_id' => $data['account_id'],
            'order_id' => empty($data['order_id']) ? null : $data['order_id'],
            'client_id' => empty($data['client_id']) ? null : $data['client_id'],
            'category_id' => empty($data['category_id']) ? null : $data['category_id'],
            'type' => $data['type'],
            'description' => empty($data['description']) ? null : $data['description'],
            'amount' => $data['amount'],
            'issue_date' => empty($data['issue_date']) ? null : $data['issue_date'],
            'competencia' => empty($data['competencia']) ? null : $data['competencia'],
            'due_date' => $data['due_date'],
            'paid_date' => $data['paid_date'] ?? null,
            'payment_method' => empty($data['payment_method']) ? null : $data['payment_method'],
            'document_number' => empty($data['document_number']) ? null : $data['document_number'],
            'interest_pct' => empty($data['interest_pct']) ? 0 : $data['interest_pct'],
            'penalty_pct' => empty($data['penalty_pct']) ? 0 : $data['penalty_pct'],
            'status' => $data['status'] ?? 'pendente',
            'is_transfer' => !empty($data['is_transfer']) ? 1 : 0,
            'transfer_pair_id' => empty($data['transfer_pair_id']) ? null : $data['transfer_pair_id'],
            'recurrence_frequency' => empty($data['recurrence_frequency']) ? null : $data['recurrence_frequency'],
            'recurrence_parent_id' => empty($data['recurrence_parent_id']) ? null : $data['recurrence_parent_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** Edicao generica -- reaproveitada tanto pro lancamento simples de Caixas e Bancos quanto
     *  pra conta a pagar/receber (o form de edicao e' o mesmo pros dois, ver _payable_fields.php). */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE financial_transactions SET
                account_id = :account_id, client_id = :client_id, category_id = :category_id,
                description = :description, amount = :amount, issue_date = :issue_date,
                competencia = :competencia, due_date = :due_date, payment_method = :payment_method,
                document_number = :document_number, interest_pct = :interest_pct, penalty_pct = :penalty_pct
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'account_id' => $data['account_id'],
            'client_id' => empty($data['client_id']) ? null : $data['client_id'],
            'category_id' => empty($data['category_id']) ? null : $data['category_id'],
            'description' => empty($data['description']) ? null : $data['description'],
            'amount' => $data['amount'],
            'issue_date' => empty($data['issue_date']) ? null : $data['issue_date'],
            'competencia' => empty($data['competencia']) ? null : $data['competencia'],
            'due_date' => $data['due_date'],
            'payment_method' => empty($data['payment_method']) ? null : $data['payment_method'],
            'document_number' => empty($data['document_number']) ? null : $data['document_number'],
            'interest_pct' => empty($data['interest_pct']) ? 0 : $data['interest_pct'],
            'penalty_pct' => empty($data['penalty_pct']) ? 0 : $data['penalty_pct'],
        ]);
    }

    /** So remove lancamento solto -- o controller bloqueia exclusao de linha gerada pelo
     *  sistema (vinculada a um pedido, o que inclui comissao paga, ver FinanceController). */
    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM financial_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function createForOrderReceivable(int $orderId, int $accountId, float $amount, string $dueDate): void
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM financial_transactions WHERE order_id = :order_id AND type = 'entrada' LIMIT 1"
        );
        $stmt->execute(['order_id' => $orderId]);
        if ($stmt->fetch()) {
            return;
        }

        self::create([
            'account_id' => $accountId,
            'order_id' => $orderId,
            'type' => 'entrada',
            'description' => 'Recebimento referente ao pedido #' . $orderId,
            'amount' => $amount,
            'due_date' => $dueDate,
            'paid_date' => $dueDate,
            'status' => 'pago',
        ]);
    }

    /** Fase 100: custo de fabrica + imposto automaticos, gerados quando um pedido NORMAL (com
     *  vendedor) e' verificado -- obrigacao da EMPRESA, nunca do Licenciado, mesmo sendo um pedido
     *  da rede dele (pedido explicito do usuario). De proposito SEM order_id/client_id -- o filtro
     *  por rede em all() (seller_ids) so' enxerga transacao vinculada a um order_id/client_id cujo
     *  vendedor esteja na rede de quem esta vendo; sem esses 2 campos a transacao fica invisivel
     *  pra esse filtro, so' Admin/Gerente (sem filtro de rede) enxergam. O numero do pedido fica
     *  so' no texto (idempotente via descricao, ja que nao da pra usar order_id aqui). Usa o mesmo
     *  custo por FAIXA DE PRECO (pricing_tiers.cost_price) e a mesma formula de imposto
     *  (tax_pct * total) ja usados no Relatorio Fiscal (App\Core\TaxReport), pra nao inventar um
     *  numero novo/divergente. */
    public static function createFactoryCostAndTax(int $orderId, int $accountId, float $costAmount, float $taxAmount, string $date): void
    {
        $marker = 'Pedido #' . $orderId;

        if ($costAmount > 0) {
            $desc = 'Custo de fábrica · ' . $marker;
            $stmt = Database::connection()->prepare('SELECT id FROM financial_transactions WHERE description = :d LIMIT 1');
            $stmt->execute(['d' => $desc]);
            if (!$stmt->fetch()) {
                self::create([
                    'account_id' => $accountId,
                    'category_id' => FinancialCategory::factoryCostCategoryId(),
                    'type' => 'saida',
                    'description' => $desc,
                    'amount' => $costAmount,
                    'due_date' => $date,
                    'status' => 'pendente',
                ]);
            }
        }

        if ($taxAmount > 0) {
            $desc = 'Imposto sobre venda · ' . $marker;
            $stmt = Database::connection()->prepare('SELECT id FROM financial_transactions WHERE description = :d LIMIT 1');
            $stmt->execute(['d' => $desc]);
            if (!$stmt->fetch()) {
                self::create([
                    'account_id' => $accountId,
                    'category_id' => FinancialCategory::salesTaxCategoryId(),
                    'type' => 'saida',
                    'description' => $desc,
                    'amount' => $taxAmount,
                    'due_date' => $date,
                    'status' => 'pendente',
                ]);
            }
        }
    }

    /** Fase 98: saida ja paga, pro Pix que o Admin manda pra Fabrica num pedido a preco de custo
     *  -- mesmo espirito de createForOrderReceivable() (idempotente, nunca duplica pro mesmo
     *  pedido), so' que do lado de saida. Sem categoria fixa -- fica "sem categoria" mesmo, o
     *  Admin reclassifica depois em Caixas e Bancos se quiser. */
    public static function createForFactoryPayment(int $orderId, int $accountId, float $amount, string $date): void
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM financial_transactions WHERE order_id = :order_id AND type = 'saida' AND description LIKE 'Pagamento à fábrica%' LIMIT 1"
        );
        $stmt->execute(['order_id' => $orderId]);
        if ($stmt->fetch()) {
            return;
        }

        self::create([
            'account_id' => $accountId,
            'order_id' => $orderId,
            'type' => 'saida',
            'description' => 'Pagamento à fábrica referente ao pedido #' . $orderId,
            'amount' => $amount,
            'due_date' => $date,
            'paid_date' => $date,
            'status' => 'pago',
        ]);
    }

    /** Transferencia entre contas proprias: duas pernas (saida na origem, entrada no destino),
     *  sempre pagas na hora e marcadas is_transfer=1 pra nunca entrar em DRE/Balancete/Relatorios
     *  de pagamento e recebimento (nao e' receita nem despesa, so move dinheiro de um bolso pro
     *  outro) -- so aparece no extrato de Caixas e Bancos e no Controle de Caixa por conta.
     *  @return array{from:int, to:int} ids das duas linhas criadas
     */
    public static function createTransfer(int $fromAccountId, int $toAccountId, float $amount, string $date, string $description): array
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $fromAccount = FinancialAccount::find($fromAccountId);
            $toAccount = FinancialAccount::find($toAccountId);

            $fromId = self::create([
                'account_id' => $fromAccountId,
                'type' => 'saida',
                'description' => 'Transferência para ' . ($toAccount['name'] ?? 'outra conta') . ($description ? ' · ' . $description : ''),
                'amount' => $amount,
                'due_date' => $date,
                'paid_date' => $date,
                'status' => 'pago',
                'is_transfer' => true,
            ]);
            $toId = self::create([
                'account_id' => $toAccountId,
                'type' => 'entrada',
                'description' => 'Transferência de ' . ($fromAccount['name'] ?? 'outra conta') . ($description ? ' · ' . $description : ''),
                'amount' => $amount,
                'due_date' => $date,
                'paid_date' => $date,
                'status' => 'pago',
                'is_transfer' => true,
                'transfer_pair_id' => $fromId,
            ]);

            $stmt = $db->prepare('UPDATE financial_transactions SET transfer_pair_id = :pair WHERE id = :id');
            $stmt->execute(['pair' => $toId, 'id' => $fromId]);

            $db->commit();
            return ['from' => $fromId, 'to' => $toId];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function markPaid(int $id, string $paidDate): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE financial_transactions SET status = 'pago', paid_date = :paid_date WHERE id = :id"
        );
        $stmt->execute(['paid_date' => $paidDate, 'id' => $id]);
    }

    public static function totalValue(array $row): float
    {
        $amount = (float) $row['amount'];
        $extra = $amount * ((float) $row['interest_pct'] + (float) $row['penalty_pct']) / 100;
        return round($amount + $extra, 2);
    }
}
