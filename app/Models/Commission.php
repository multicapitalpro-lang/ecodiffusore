<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Roles;

class Commission
{
    /**
     * Duas cascatas independentes disparam a partir do mesmo Licenciado:
     *
     * 1) Pool regional: o PRECO UNITARIO MEDIO negociado no pedido (orderTotal / totalQty) define
     *    a faixa de preco (Fase 31, PricingTier::forPrice -- substituiu por completo a faixa por
     *    QUANTIDADE da Fase 24: preco nao cai mais automaticamente com a quantidade, e' negociado
     *    livremente dentro de um piso minimo validado na criacao do pedido/proposta) -- a % de
     *    comissao do Licenciado (o "pool") vem SEMPRE dessa faixa, nao e mais um commission_pct
     *    negociado por licenciado (esse campo continua existindo em users, mas so vale pra
     *    Gestor/Vendedor/Supervisor/Gerente agora). O pool em si continua existindo so' como
     *    REFERENCIA de quanto o Licenciado tem "pra trabalhar" -- a partir da Fase 43, o
     *    commission_pct que o Licenciado configura pro Gestor/Vendedor e' % DO TOTAL DO PEDIDO
     *    (nao mais % do pool -- pedido explicito do usuario: "a % do vendedor definida pelo
     *    licenciado... deve ser pela % de venda do produto e nao a % sobre o valor da comissao do
     *    licenciado"). Vendedor tem DOIS esquemas possiveis, escolhidos pelo Licenciado no
     *    cadastro:
     *      a) Tabela por faixa (commission_type + user_commission_tiers): sai direto do valor da
     *         venda -- % da venda ou R$ fixo por unidade, conforme a MESMA faixa de preco que
     *         definiu o pool. Ver vendorTierAmount(). Ja era % do TOTAL do pedido desde a Fase 31,
     *         sem mudanca nesta fase.
     *      b) Sem commission_type configurado, ou sem valor pra essa faixa especifica: cai no
     *         esquema simples, commission_pct do Vendedor = % do TOTAL DO PEDIDO, igual Gestor.
     *    O que sobra do pool (pool menos as fatias de Gestor/Vendedor, nunca negativo -- se as
     *    fatias configuradas ultrapassarem o pool, o Licenciado simplesmente fica sem sobra nesse
     *    pedido, mas Gestor/Vendedor recebem o valor CHEIO que foi configurado pra eles, sem
     *    corte) fica com o Licenciado. Pedido sem itens (quantidade zero) ou com preco unitario
     *    medio abaixo do piso da faixa mais baixa (forPrice devolve null) nao gera pool nenhum --
     *    na pratica isso so aconteceria se algum preco escapasse da validacao de piso.
     *
     * 2) Comissao nacional: se o Licenciado tiver um Supervisor atribuido (supervisor_id, definido
     *    pelo Gerente em /painel/licenciados), Supervisor e Gerente recebem um % do TOTAL do
     *    pedido -- paga direto pela Ecodiffusore, nunca sai do pool acima. Por isso roda num bloco
     *    totalmente a parte, mesmo se o pedido nao tiver faixa de preco valida. Fase 83: o
     *    Supervisor pode, opcionalmente, ter a MESMA tabela por faixa de preco do Vendedor
     *    (commission_type + user_commission_tiers, definida pelo Gerente) em vez do % simples --
     *    ver tierBasedAmount(). O Gerente continua so' no % simples (nao pedido pelo usuario).
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

        $totalQty = 0;
        foreach (OrderItem::forOrder($orderId) as $item) {
            $totalQty += (int) $item['quantity'];
        }
        $avgUnitPrice = $totalQty > 0 ? $orderTotal / $totalQty : 0;
        $tier = $totalQty > 0 ? PricingTier::forPrice($avgUnitPrice) : null;

        if ($licenciado && $tier) {
            $pool = round($orderTotal * (float) $tier['licenciado_commission_pct'] / 100, 2);
            $distribuido = 0.0;

            foreach ($chain as $p) {
                if ((int) $p['id'] === (int) $licenciado['id']) {
                    continue;
                }

                $tierAmount = (int) $p['id'] === $sellerId ? self::tierBasedAmount($p, $tier, $orderTotal, $totalQty) : null;

                if ($tierAmount !== null) {
                    $effectivePct = $orderTotal > 0 ? round($tierAmount / $orderTotal * 100, 2) : 0.0;
                    $distribuido += $tierAmount;
                    self::insertRow($orderId, $sellerId, (int) $p['id'], $p['role_slug'], $effectivePct, $tierAmount);
                    continue;
                }

                $sharePct = (float) ($p['commission_pct'] ?? 0);
                if ($sharePct <= 0) {
                    continue;
                }

                // Fase 43: commission_pct de Gestor/Vendedor (esquema simples, sem tabela por
                // faixa) e' % do TOTAL DO PEDIDO, nao mais % do pool -- so' o que sobra do pool
                // depois de subtrair essas fatias (nunca negativo, ver $restante abaixo) e' que
                // continua sendo a comissao do Licenciado.
                $amount = round($orderTotal * $sharePct / 100, 2);
                $distribuido += $amount;
                self::insertRow($orderId, $sellerId, (int) $p['id'], $p['role_slug'], $sharePct, $amount);
            }

            $restante = max(0, round($pool - $distribuido, 2));

            // Fase 56: indicacao de Influenciador -- comissao FIXA (nao %, valor configurado por
            // admin em users.influencer_commission_value, R$100 default), descontada do que SOBRA
            // pro Licenciado (nao do pool inteiro nem do total do pedido -- pedido explicito do
            // usuario, com o exemplo "dos R$900 do licenciado, desconta R$100 pro influenciador").
            // min() com $restante evita comissao negativa se a sobra do licenciado for menor que o
            // valor do influenciador nesse pedido especifico.
            $order = Order::find($orderId);
            if ($order && !empty($order['influencer_id'])) {
                $influencer = User::find((int) $order['influencer_id']);
                if ($influencer && $influencer['role_slug'] === Roles::INFLUENCER) {
                    $influencerValue = min($restante, (float) ($influencer['influencer_commission_value'] ?? 100));
                    if ($influencerValue > 0) {
                        self::insertRow($orderId, $sellerId, (int) $influencer['id'], Roles::INFLUENCER, 0, $influencerValue);
                        $restante = max(0, round($restante - $influencerValue, 2));
                    }
                }
            }

            if ($restante > 0) {
                self::insertRow($orderId, $sellerId, (int) $licenciado['id'], 'licenciado', (float) $tier['licenciado_commission_pct'], $restante);
            }
        }

        if ($licenciado && !empty($licenciado['supervisor_id'])) {
            $supervisor = User::find((int) $licenciado['supervisor_id']);

            if ($supervisor && $supervisor['role_slug'] === 'supervisor') {
                // Fase 83: Supervisor tambem pode ter tabela por faixa de preco (definida pelo
                // Gerente), igual o Vendedor -- usa ela quando configurada pra essa faixa, senao
                // cai no % simples do commission_pct, igual antes.
                $supervisorTierAmount = $tier ? self::tierBasedAmount($supervisor, $tier, $orderTotal, $totalQty) : null;
                if ($supervisorTierAmount !== null) {
                    $effectivePct = $orderTotal > 0 ? round($supervisorTierAmount / $orderTotal * 100, 2) : 0.0;
                    self::insertRow($orderId, $sellerId, (int) $supervisor['id'], 'supervisor', $effectivePct, $supervisorTierAmount);
                } elseif ((float) ($supervisor['commission_pct'] ?? 0) > 0) {
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
     * Comissao por tabela de faixa de PRECO (Fase 31, Vendedor; Fase 83, tambem Supervisor) --
     * null se a pessoa nao tem commission_type configurado (ainda no esquema simples de % fixo)
     * OU se nao ha valor cadastrado especificamente pra essa faixa. Nesse caso o chamador cai de
     * volta pro % simples (commission_pct), igual Gestor/Gerente. "percentual" e sobre o TOTAL do
     * pedido; "fixo" e por unidade vendida (valor x quantidade total do pedido).
     */
    private static function tierBasedAmount(array $person, array $tier, float $orderTotal, int $totalQty): ?float
    {
        if (empty($person['commission_type'])) {
            return null;
        }

        $value = UserCommissionTier::valueFor((int) $person['id'], (int) $tier['id']);
        if ($value === null) {
            return null;
        }

        return $person['commission_type'] === 'percentual'
            ? round($orderTotal * $value / 100, 2)
            : round($value * $totalQty, 2);
    }

    /**
     * `percentage` guardado aqui significa coisas diferentes por papel: pro Licenciado e o % da
     * faixa de preco negociado (PricingTier) sobre o total do pedido; pro Gestor/Vendedor (esquema
     * simples, Fase 43) e o % do TOTAL DO PEDIDO configurado pelo Licenciado; pro Vendedor na
     * tabela por faixa e o % EFETIVO sobre o pedido (calculado a partir do valor, so pra
     * exibicao/relatorio); pro Supervisor/Gerente e o % do total do pedido pago direto pela
     * Ecodiffusore (fora do pool).
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
        if (!empty($filters['from'])) {
            $sql .= ' AND o.order_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND o.order_date <= :to';
            $params['to'] = $filters['to'];
        }

        $sql .= ' ORDER BY c.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, b.name AS beneficiary_name, b.whatsapp AS beneficiary_whatsapp
             FROM commissions c JOIN users b ON b.id = c.beneficiary_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Marca como pago e, quando ha um lancamento financeiro vinculado (saida real de caixa),
     *  guarda a referencia -- sem isso o pagamento de comissao ficava invisivel pro financeiro. */
    public static function markPaid(int $id, ?int $financialTransactionId = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE commissions SET status = 'pago', paid_at = NOW(), financial_transaction_id = :ftid WHERE id = :id"
        );
        $stmt->execute(['ftid' => $financialTransactionId, 'id' => $id]);
    }

    public static function byBeneficiary(array $filters = []): array
    {
        $sql = "SELECT u.id AS beneficiary_id, u.name, u.role_slug,
                    COUNT(c.id) AS count_total,
                    COALESCE(SUM(c.amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN c.status = 'pago' THEN c.amount ELSE 0 END), 0) AS total_pago,
                    COALESCE(SUM(CASE WHEN c.status = 'pendente' THEN c.amount ELSE 0 END), 0) AS total_pendente
                FROM commissions c
                JOIN (SELECT u.id, u.name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id) u ON u.id = c.beneficiary_id
                JOIN orders o ON o.id = c.order_id
                WHERE 1=1";
        $params = self::applyScopeAndPeriod($sql, $filters);

        $sql .= ' GROUP BY u.id ORDER BY total DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Mesmo total de byBeneficiary(), so que agrupado por papel (Vendedor/Licenciado/Gestor/
     *  Supervisor/Gerente) em vez de pessoa -- pra comparar quanto cada nivel da hierarquia esta
     *  ganhando no periodo, sem precisar somar linha por linha. */
    public static function byRole(array $filters = []): array
    {
        $sql = "SELECT c.role_slug,
                    COUNT(c.id) AS count_total,
                    COALESCE(SUM(c.amount), 0) AS total,
                    COALESCE(SUM(CASE WHEN c.status = 'pago' THEN c.amount ELSE 0 END), 0) AS total_pago,
                    COALESCE(SUM(CASE WHEN c.status = 'pendente' THEN c.amount ELSE 0 END), 0) AS total_pendente
                FROM commissions c
                JOIN orders o ON o.id = c.order_id
                WHERE 1=1";
        $params = self::applyScopeAndPeriod($sql, $filters);

        $sql .= ' GROUP BY c.role_slug ORDER BY total DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Acrescenta os filtros de escopo (beneficiario) + periodo (order_date) em $sql por
     *  referencia e devolve os parametros correspondentes -- reaproveitado por byBeneficiary()
     *  e byRole(), que agora precisam dos dois filtros identicos. */
    private static function applyScopeAndPeriod(string &$sql, array $filters): array
    {
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
        if (!empty($filters['from'])) {
            $sql .= ' AND o.order_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND o.order_date <= :to';
            $params['to'] = $filters['to'];
        }

        return $params;
    }
}
