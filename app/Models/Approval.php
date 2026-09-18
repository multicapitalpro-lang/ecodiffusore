<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Roles;

/**
 * Fase 9: aprovacao de desconto (generica, por % contra products.price_cash).
 * Fase 57: SUBSTITUIDA pela regra de piso por papel, decisao do usuario ("todos os vendedores
 * comecariam a querer prostituir o preco... a comissao do licenciado fica menor") -- Vendedor
 * trabalha a partir de PricingTier::VENDOR_STANDARD_PRICE (R$4.290) por padrao; vender abaixo
 * disso precisa de aprovacao. Continua reaproveitando a MESMA tabela `approvals` e o MESMO
 * ponto de bloqueio (Order::markVerifiedWithCommission()/Quote::convert()) -- so mudou o
 * gatilho (preco, nao %) e quem pode decidir (depende do papel de quem pediu, nao mais um bloco
 * fixo de papeis).
 */
class Approval
{
    /**
     * Verifica o menor preco unitario dos itens contra o piso do PAPEL de quem esta vendendo:
     * Vendedor/Gestor/Licenciado abaixo de VENDOR_STANDARD_PRICE cria (ou atualiza) uma
     * pendencia; Admin/Gerente/Supervisor como "vendedor" de um pedido/orcamento (raro, mas
     * possivel) nunca precisam de aprovacao -- ja estao no topo da cadeia. O piso ABSOLUTO
     * (PricingTier::forPrice(), hoje R$3.450) continua validado separadamente em
     * OrderController/QuoteController/PropostaController::validate() -- essa aqui nunca deixa
     * passar preco abaixo do piso absoluto, so decide se precisa de aprovacao pra ficar entre o
     * piso absoluto e o piso do papel.
     */
    public static function checkAndRequest(string $type, int $id, array $items, ?int $sellerId, int $requestedBy): void
    {
        if (!$sellerId) {
            return;
        }

        $seller = User::find($sellerId);
        if (!$seller || !in_array($seller['role_slug'], ['vendedor', 'gestor', 'licenciado'], true)) {
            self::clearPendingFor($type, $id);
            return;
        }

        $lowestPrice = null;
        foreach ($items as $item) {
            $price = (float) $item['unit_price'];
            if ($price > 0 && ($lowestPrice === null || $price < $lowestPrice)) {
                $lowestPrice = $price;
            }
        }

        if ($lowestPrice === null || $lowestPrice >= PricingTier::VENDOR_STANDARD_PRICE) {
            self::clearPendingFor($type, $id);
            return;
        }

        $discountPct = round((PricingTier::VENDOR_STANDARD_PRICE - $lowestPrice) / PricingTier::VENDOR_STANDARD_PRICE * 100, 2);

        $existing = self::pendingFor($type, $id);
        if ($existing) {
            $stmt = Database::connection()->prepare(
                'UPDATE approvals SET requested_discount_pct = :dpct, requested_price = :price, requester_role = :role WHERE id = :id'
            );
            $stmt->execute(['dpct' => $discountPct, 'price' => $lowestPrice, 'role' => $seller['role_slug'], 'id' => $existing['id']]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO approvals (approvable_type, approvable_id, requested_discount_pct, requested_price, requester_role, status, requested_by)
             VALUES (:type, :id, :dpct, :price, :role, "pendente", :by)'
        );
        $stmt->execute(['type' => $type, 'id' => $id, 'dpct' => $discountPct, 'price' => $lowestPrice, 'role' => $seller['role_slug'], 'by' => $requestedBy]);
    }

    /**
     * Vendedor e aprovado pelo Gestor OU Licenciado da PROPRIA rede dele (nunca por um gestor/
     * licenciado de outra rede -- escopo via User::managerChain()). Gestor/Licenciado sao
     * aprovados por Gerente, Supervisor OU Admin -- qualquer um dos tres, sem precisar ser
     * especificamente responsavel por aquela rede (mesmo espirito ja usado pra aprovacao de
     * cadastro de Licenciado). Pendencia antiga (de antes da Fase 57, sem requester_role
     * gravado) cai no comportamento antigo -- Admin/Gestor/Licenciado genericos decidem, pra
     * nao travar pendencia ja existente no ar quando essa mudanca entrar no ar.
     */
    public static function canDecide(array $approval, array $user): bool
    {
        $requesterRole = $approval['requester_role'] ?? null;

        if ($requesterRole === 'vendedor') {
            if (!in_array($user['role_slug'], ['gestor', 'licenciado'], true)) {
                return false;
            }
            $seller = self::sellerFor($approval);
            if (!$seller) {
                return false;
            }
            $chainIds = array_map(fn ($p) => (int) $p['id'], User::managerChain((int) $seller['id']));
            return in_array((int) $user['id'], $chainIds, true);
        }

        if (in_array($requesterRole, ['gestor', 'licenciado'], true)) {
            return in_array($user['role_slug'], ['gerente', 'supervisor', 'admin'], true);
        }

        return in_array($user['role_slug'], Roles::MANAGEMENT, true);
    }

    private static function sellerFor(array $approval): ?array
    {
        $sellerId = $approval['approvable_type'] === 'order'
            ? (Order::find((int) $approval['approvable_id'])['seller_id'] ?? null)
            : (Quote::find((int) $approval['approvable_id'])['seller_id'] ?? null);

        return $sellerId ? User::find((int) $sellerId) : null;
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
