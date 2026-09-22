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
 * Fase 58: quando quem pediu foi VENDEDOR, a aprovacao do Gestor/Licenciado (nivel 1) NAO e' mais
 * a decisao final -- pedido explicito do usuario ("ambos precisam da aprovacao do admin"). Depois
 * do nivel 1, a pendencia continua "pendente" (coluna `status` nao muda) mas passa a depender de
 * Gerente, Supervisor OU Admin (nivel 2), registrado em `level1_approved_by`/`level1_approved_at`.
 * Pedido de Gestor/Licenciado pra si mesmo continua de 1 nivel so (direto pro nivel 2).
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
    public static function checkAndRequest(string $type, int $id, array $items, ?int $sellerId, int $requestedBy, ?string $justification = null): void
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
                'UPDATE approvals SET requested_discount_pct = :dpct, requested_price = :price, requester_role = :role, justification = :justification WHERE id = :id'
            );
            $stmt->execute(['dpct' => $discountPct, 'price' => $lowestPrice, 'role' => $seller['role_slug'], 'justification' => $justification ?: null, 'id' => $existing['id']]);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO approvals (approvable_type, approvable_id, requested_discount_pct, requested_price, requester_role, justification, status, requested_by)
             VALUES (:type, :id, :dpct, :price, :role, :justification, "pendente", :by)'
        );
        $stmt->execute(['type' => $type, 'id' => $id, 'dpct' => $discountPct, 'price' => $lowestPrice, 'role' => $seller['role_slug'], 'justification' => $justification ?: null, 'by' => $requestedBy]);

        // Notifica so na CRIACAO (nao a cada reenvio do mesmo formulario com o mesmo preco baixo)
        // -- WhatsApp pra quem pode decidir essa pendencia especifica (nivel 1, ou direto nivel 2
        // se quem pediu ja' foi Gestor/Licenciado).
        $new = self::find((int) Database::connection()->lastInsertId());
        if ($new) {
            \App\Core\Notifier::liberacaoDescontoSolicitada($new);
        }
    }

    /**
     * Vendedor e' aprovado, em 2 etapas: primeiro pelo Gestor OU Licenciado da PROPRIA rede dele
     * (nunca de outra rede -- escopo via User::managerChain()); so' depois disso a pendencia
     * (que continua com status='pendente') passa a exigir Gerente, Supervisor OU Admin (qualquer
     * um dos tres). Gestor/Licenciado pedindo pra SI MESMO pula direto pra essa segunda etapa.
     * Pendencia antiga (de antes da Fase 57, sem requester_role gravado) cai no comportamento
     * antigo -- Admin/Gestor/Licenciado genericos decidem, pra nao travar pendencia ja existente
     * no ar quando essa mudanca entrar no ar.
     */
    public static function canDecide(array $approval, array $user): bool
    {
        $requesterRole = $approval['requester_role'] ?? null;

        if ($requesterRole === 'vendedor') {
            if (empty($approval['level1_approved_by'])) {
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

            // Nivel 2: ja passou pelo Gestor/Licenciado -- so falta a rede nacional.
            return in_array($user['role_slug'], ['gerente', 'supervisor', 'admin'], true);
        }

        if (in_array($requesterRole, ['gestor', 'licenciado'], true)) {
            return in_array($user['role_slug'], ['gerente', 'supervisor', 'admin'], true);
        }

        return in_array($user['role_slug'], Roles::MANAGEMENT, true);
    }

    /** Texto pronto pra exibir em qualquer tela (lista, banner, "minhas solicitacoes") -- unica
     *  fonte de verdade pro rotulo de status, inclusive a etapa intermediaria (nivel 1 aprovado,
     *  aguardando nivel 2) que nao tem um valor proprio na coluna `status`. */
    public static function statusLabel(array $approval): string
    {
        if ($approval['status'] === 'aprovado') {
            return 'Aprovado';
        }
        if ($approval['status'] === 'recusado') {
            return 'Recusado';
        }

        $requesterRole = $approval['requester_role'] ?? null;
        if ($requesterRole === 'vendedor') {
            if (!empty($approval['level1_approved_by'])) {
                return 'Aprovado pelo Gestor/Licenciado — aguardando aprovação final (Gerente, Supervisor ou Admin)';
            }
            return 'Aguardando aprovação do Gestor ou Licenciado';
        }
        if (in_array($requesterRole, ['gestor', 'licenciado'], true)) {
            return 'Aguardando aprovação do Gerente, Supervisor ou Admin';
        }

        return 'Aguardando aprovação';
    }

    private static function sellerFor(array $approval): ?array
    {
        $sellerId = $approval['approvable_type'] === 'order'
            ? (Order::find((int) $approval['approvable_id'])['seller_id'] ?? null)
            : (Quote::find((int) $approval['approvable_id'])['seller_id'] ?? null);

        return $sellerId ? User::find((int) $sellerId) : null;
    }

    /** Quantas pendencias ESSE usuario pode decidir agora -- badge do menu (mesmo padrao de
     *  MachineQuoteRequest::countPending()/pendingWarranties, computado direto no layout). */
    public static function countPendingForUser(array $user): int
    {
        $rows = Database::connection()->query("SELECT * FROM approvals WHERE status = 'pendente'")->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            if (self::canDecide($row, $user)) {
                $count++;
            }
        }
        return $count;
    }

    /** Quantas solicitacoes do PROPRIO usuario (ele e' quem vendeu, nao quem decide) ainda estao
     *  em andamento -- badge do menu pro Vendedor/Gestor/Licenciado acompanharem sem precisar abrir
     *  cada Pedido/Orcamento pra saber se ja foi decidido. */
    public static function countMyPendingRequests(int $userId): int
    {
        $rows = Database::connection()->query("SELECT * FROM approvals WHERE status = 'pendente'")->fetchAll();
        $count = 0;
        foreach ($rows as $row) {
            $seller = self::sellerFor($row);
            if ($seller && (int) $seller['id'] === $userId) {
                $count++;
            }
        }
        return $count;
    }

    /** Todas as pendencias em aberto, com contexto (cliente/vendedor/link) pra listar na tela
     *  central /painel/liberacoes -- o controller filtra por canDecide() pra cada usuario. */
    public static function allPending(): array
    {
        $stmt = Database::connection()->query(
            "SELECT a.*, req.name AS requested_by_name
             FROM approvals a
             LEFT JOIN users req ON req.id = a.requested_by
             WHERE a.status = 'pendente'
             ORDER BY a.created_at DESC"
        );
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $seller = self::sellerFor($row);
            $record = $row['approvable_type'] === 'order' ? Order::find((int) $row['approvable_id']) : Quote::find((int) $row['approvable_id']);
            $row['seller_name'] = $seller['name'] ?? '—';
            $row['client_name'] = $record['client_name'] ?? '—';
            $row['url'] = $row['approvable_type'] === 'order'
                ? '/painel/pedidos/' . (int) $row['approvable_id']
                : '/painel/orcamentos/' . (int) $row['approvable_id'];
        }
        unset($row);

        return $rows;
    }

    /** "Minhas solicitacoes" -- tudo (qualquer status/etapa) em que o PROPRIO usuario e' quem
     *  vendeu (Vendedor pedindo liberacao, ou Gestor/Licenciado pedindo pra si mesmo), mais
     *  recente primeiro. Pedido explicito do usuario: ele precisa acompanhar o status em tempo
     *  real sem depender de alguem te avisar por fora. */
    public static function forOwnRequests(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->query(
            'SELECT a.* FROM approvals a ORDER BY a.created_at DESC, a.id DESC LIMIT 500'
        );
        $rows = $stmt->fetchAll();

        $mine = [];
        foreach ($rows as $row) {
            $seller = self::sellerFor($row);
            if (!$seller || (int) $seller['id'] !== $userId) {
                continue;
            }
            $record = $row['approvable_type'] === 'order' ? Order::find((int) $row['approvable_id']) : Quote::find((int) $row['approvable_id']);
            $row['client_name'] = $record['client_name'] ?? '—';
            $row['url'] = $row['approvable_type'] === 'order'
                ? '/painel/pedidos/' . (int) $row['approvable_id']
                : '/painel/orcamentos/' . (int) $row['approvable_id'];
            $mine[] = $row;
            if (count($mine) >= $limit) {
                break;
            }
        }

        return $mine;
    }

    /** "Decisões que você tomou" -- diferente de forOwnRequests() (onde o usuario e' quem VENDEU):
     *  aqui e' onde o usuario decidiu algo, no nivel 1 (Gestor/Licenciado) ou no nivel 2/decisao
     *  final (Gerente/Supervisor/Admin, ou Gestor/Licenciado decidindo pra si mesmo). Sem isso,
     *  depois de decidir uma pendencia ela simplesmente sumia da tela de quem decidiu -- pedido
     *  explicito do usuario: "depois que aprovei no gerente tbm nao consta". */
    public static function forDecisionsBy(int $userId, int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM approvals WHERE decided_by = :id1 OR level1_approved_by = :id2
             ORDER BY COALESCE(decided_at, level1_approved_at, created_at) DESC LIMIT 500'
        );
        $stmt->execute(['id1' => $userId, 'id2' => $userId]);
        $rows = $stmt->fetchAll();

        $decided = [];
        foreach ($rows as $row) {
            $seller = self::sellerFor($row);
            $record = $row['approvable_type'] === 'order' ? Order::find((int) $row['approvable_id']) : Quote::find((int) $row['approvable_id']);
            $row['seller_name'] = $seller['name'] ?? '—';
            $row['client_name'] = $record['client_name'] ?? '—';
            $row['url'] = $row['approvable_type'] === 'order'
                ? '/painel/pedidos/' . (int) $row['approvable_id']
                : '/painel/orcamentos/' . (int) $row['approvable_id'];
            $decided[] = $row;
            if (count($decided) >= $limit) {
                break;
            }
        }

        return $decided;
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

    /** Ultima linha registrada pra esse pedido/orcamento, seja qual for o status -- diferente de
     *  pendingFor() (so' pendente), usada pra saber se uma RECUSA ainda deve continuar bloqueando. */
    public static function latestFor(string $type, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM approvals WHERE approvable_type = :type AND approvable_id = :id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['type' => $type, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Ainda bloqueia mesmo depois de "decidida" quando a ultima decisao foi RECUSADA e o preco
     *  atual dos itens continua sendo o mesmo que foi recusado -- sem isso, assim que a decisao
     *  cai (aprovada OU recusada) pendingFor() para de bloquear (so' olha status='pendente'),
     *  deixando concluir a venda no preco recusado. Se o preco foi corrigido depois (qualquer
     *  edicao roda checkAndRequest() de novo), o preco atual nao bate mais com o que foi recusado
     *  e a recusa antiga para de valer -- so' um preco novo "libera" de verdade, nunca so' o tempo
     *  passando. */
    public static function blocksCompletion(string $type, int $id, float $currentLowestPrice): ?array
    {
        $latest = self::latestFor($type, $id);
        if (!$latest) {
            return null;
        }
        if ($latest['status'] === 'pendente') {
            return $latest;
        }
        if ($latest['status'] === 'recusado'
            && $latest['requested_price'] !== null
            && abs((float) $latest['requested_price'] - $currentLowestPrice) < 0.01
        ) {
            return $latest;
        }
        return null;
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

    /** @return array A linha ja' atualizada, pra quem chamou poder registrar auditoria/notificar
     *  com o estado real (inclusive a etapa intermediaria de nivel 1). */
    public static function decide(int $id, string $decision, int $decidedBy): array
    {
        $approval = self::find($id);
        if (!$approval || $approval['status'] !== 'pendente') {
            return $approval ?? [];
        }

        $decidedByUser = User::find($decidedBy);

        if ($decision === 'recusado') {
            $stmt = Database::connection()->prepare(
                'UPDATE approvals SET status = "recusado", decided_by = :by, decided_at = NOW() WHERE id = :id'
            );
            $stmt->execute(['by' => $decidedBy, 'id' => $id]);
            $updated = self::find($id);
            \App\Core\Notifier::liberacaoDescontoDecidida($updated ?? $approval, 'Recusado', $decidedByUser);
            return $updated ?? $approval;
        }

        // decision === 'aprovado'
        $needsLevel2 = ($approval['requester_role'] ?? null) === 'vendedor' && empty($approval['level1_approved_by']);

        if ($needsLevel2) {
            $stmt = Database::connection()->prepare(
                'UPDATE approvals SET level1_approved_by = :by, level1_approved_at = NOW() WHERE id = :id'
            );
            $stmt->execute(['by' => $decidedBy, 'id' => $id]);
            $updated = self::find($id);

            \App\Core\Notifier::liberacaoDescontoDecidida(
                $updated ?? $approval,
                'Aprovado pelo Gestor/Licenciado — aguardando aprovação final',
                $decidedByUser
            );
            \App\Core\Notifier::liberacaoNivel2Necessaria($updated ?? $approval);

            return $updated ?? $approval;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE approvals SET status = "aprovado", decided_by = :by, decided_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['by' => $decidedBy, 'id' => $id]);
        $updated = self::find($id);
        \App\Core\Notifier::liberacaoDescontoDecidida($updated ?? $approval, 'Aprovado', $decidedByUser);

        return $updated ?? $approval;
    }
}
