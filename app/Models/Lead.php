<?php

namespace App\Models;

use App\Core\Database;

class Lead
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO leads (name, whatsapp, city, truck_brand, message, source)
             VALUES (:name, :whatsapp, :city, :truck_brand, :message, :source)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'whatsapp' => $data['whatsapp'],
            'city' => $data['city'] ?: null,
            'truck_brand' => $data['truck_brand'] ?: null,
            'message' => $data['message'] ?: null,
            'source' => $data['source'] ?? 'landing_page',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT l.*, u.name AS assigned_name FROM leads l
                      LEFT JOIN users u ON u.id = l.assigned_to_user_id
                      ORDER BY l.created_at DESC')
            ->fetchAll();
    }

    /** Fase 71: visao pra Fabrica acompanhar volume de contatos/negociacoes em andamento -- so'
     *  nome, cidade e etapa (nunca telefone/e-mail/mensagem), pedido explicito do usuario: "sem
     *  ter acesso ao lead ou contato, pra que nao efetuem vendas diretas". A query nem seleciona
     *  os campos de contato, entao nao tem como vazar por engano numa view futura. */
    public static function forFactoryOverview(int $limit = 500): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, city, status, created_at FROM leads ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM leads WHERE assigned_to_user_id = :id ORDER BY created_at DESC'
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->fetchAll();
    }

    /** Leads atribuidos a algum dos $userIds, opcionalmente incluindo os ainda sem responsavel */
    public static function forScope(array $userIds, bool $includeUnassigned): array
    {
        if (!$userIds && !$includeUnassigned) {
            return [];
        }

        $conditions = [];
        $params = [];

        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $conditions[] = "l.assigned_to_user_id IN ({$placeholders})";
            $params = array_merge($params, $userIds);
        }
        if ($includeUnassigned) {
            $conditions[] = 'l.assigned_to_user_id IS NULL';
        }

        $sql = 'SELECT l.*, u.name AS assigned_name FROM leads l
                LEFT JOIN users u ON u.id = l.assigned_to_user_id
                WHERE ' . implode(' OR ', $conditions) . '
                ORDER BY l.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Fase 133: contagem de leads "novos" (status ainda no estagio inicial) pro badge do menu --
     *  mesma logica de escopo de forScope()/scopedLeads(). $userIds null = sem restricao (admin,
     *  ve tudo); array vazio + $includeUnassigned false = nao ve nada. */
    public static function countNew(?array $userIds, bool $includeUnassigned): int
    {
        if ($userIds === null) {
            $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM leads WHERE status = 'novo'");
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        }

        if (!$userIds && !$includeUnassigned) {
            return 0;
        }

        $conditions = [];
        $params = ['novo'];

        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $conditions[] = "assigned_to_user_id IN ({$placeholders})";
            $params = array_merge($params, $userIds);
        }
        if ($includeUnassigned) {
            $conditions[] = 'assigned_to_user_id IS NULL';
        }

        $sql = 'SELECT COUNT(*) FROM leads WHERE status = ? AND (' . implode(' OR ', $conditions) . ')';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE leads SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Fase 65: ordem das etapas AUTOMATICAS do checkout publico (App\Controllers\
     *  PublicOrderController/PaymentController/Order::markVerifiedWithCommission) -- so' usada
     *  por advanceCheckoutStage(), nunca pra bloquear o Kanban manual (staff continua podendo
     *  arrastar o card pra qualquer coluna, inclusive uma dessas, a qualquer momento). */
    private const CHECKOUT_STAGE_RANK = [
        'checkout_acessado' => 1,
        'termos_aceitos' => 2,
        'pagamento_gerado' => 3,
        'pagamento_pendente' => 3,
        'convertido' => 4,
    ];

    /** Move o card do Lead sozinho, conforme o comprador avanca no checkout publico -- pedido
     *  explicito do usuario ("o proprio CRM se auto-atualiza... pra que nao haja necessidade do
     *  vendedor ficar atualizando toda vez"). So' avanca pra FRENTE (nunca reverte uma etapa) e
     *  NUNCA mexe num lead que o staff ja moveu manualmente pra um estado final ('descartado' ou
     *  'convertido') -- um card descartado ou ja fechado nao deve "ressuscitar" sozinho so' porque
     *  o comprador reabriu o link antigo. 'pagamento_pendente' e' um caso especial: so' entra a
     *  partir de 'pagamento_gerado' (ver Order::flagStalePaymentPending()), no MESMO nivel dele,
     *  entao nao cabe no ranking crescente comum. */
    public static function advanceCheckoutStage(int $leadId, string $newStage): void
    {
        $lead = self::find($leadId);
        if (!$lead || in_array($lead['status'], ['descartado', 'convertido'], true)) {
            return;
        }

        if ($newStage === 'pagamento_pendente') {
            if ($lead['status'] === 'pagamento_gerado') {
                self::updateStatus($leadId, $newStage);
            }
            return;
        }

        $currentRank = self::CHECKOUT_STAGE_RANK[$lead['status']] ?? 0;
        $newRank = self::CHECKOUT_STAGE_RANK[$newStage] ?? 0;
        if ($newRank > $currentRank) {
            self::updateStatus($leadId, $newStage);
        }
    }

    /** Grava os dados do veiculo (e reconfirma o nome) capturados no wizard de orcamento em /comprar
     *  ou na Proposta Facil -- km_mensal/km_litro/preco_diesel sao os numeros que alimentam o
     *  EconomyCalculator na hora da proposta, mas ate aqui so viviam em $_SESSION (perdidos depois);
     *  gravar no Lead deixa esses dados disponiveis depois (ex: tela da Fabrica). */
    public static function updateVehicleInfo(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE leads SET name = :name, vehicle_plate = :plate, vehicle_year = :year, vehicle_brand = :brand,
                vehicle_model = :model, vehicle_power = :power, vehicle_ecu_status = :ecu_status,
                vehicle_reprogrammed_power = :reprogrammed_power, vehicle_has_arla = :has_arla,
                vehicle_has_telemetry = :has_telemetry, vehicle_km_mensal = :km_mensal,
                vehicle_km_litro = :km_litro, vehicle_preco_diesel = :preco_diesel WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'plate' => ($data['plate'] ?? '') ?: null,
            'year' => ($data['year'] ?? '') ?: null,
            'brand' => ($data['brand'] ?? '') ?: null,
            'model' => ($data['model'] ?? '') ?: null,
            'power' => ($data['power'] ?? '') ?: null,
            'ecu_status' => in_array($data['ecu_status'] ?? '', ['original', 'reprogramado'], true) ? $data['ecu_status'] : null,
            'reprogrammed_power' => ($data['reprogrammed_power'] ?? '') ?: null,
            'has_arla' => in_array($data['has_arla'] ?? '', ['sim', 'nao'], true) ? $data['has_arla'] : null,
            'has_telemetry' => in_array($data['has_telemetry'] ?? '', ['sim', 'nao'], true) ? $data['has_telemetry'] : null,
            'km_mensal' => ((float) ($data['km_mensal'] ?? 0)) > 0 ? $data['km_mensal'] : null,
            'km_litro' => ((float) ($data['km_litro'] ?? 0)) > 0 ? $data['km_litro'] : null,
            'preco_diesel' => ((float) ($data['preco_diesel'] ?? 0)) > 0 ? $data['preco_diesel'] : null,
        ]);
    }

    /** Busca por nome, WhatsApp ou placa do veiculo -- usado na busca global do painel. Mesmo
     *  formato de escopo de forScope() ($userIds null = sem escopo/Admin). */
    public static function search(string $term, ?array $userIds = null, bool $includeUnassigned = false): array
    {
        $digits = preg_replace('/\D/', '', $term);
        $termCondition = 'l.name LIKE :term1 OR l.vehicle_plate LIKE :term2';
        $params = ['term1' => '%' . $term . '%', 'term2' => '%' . $term . '%'];
        if ($digits !== '') {
            $termCondition .= " OR REGEXP_REPLACE(l.whatsapp, '[^0-9]', '') LIKE :digits";
            $params['digits'] = '%' . $digits . '%';
        }
        $conditions = ["({$termCondition})"];

        if ($userIds !== null) {
            $scopeParts = [];
            if ($userIds) {
                $names = [];
                foreach (array_values($userIds) as $i => $uid) {
                    $key = "uid{$i}";
                    $names[] = ":{$key}";
                    $params[$key] = $uid;
                }
                $scopeParts[] = 'l.assigned_to_user_id IN (' . implode(',', $names) . ')';
            }
            if ($includeUnassigned) {
                $scopeParts[] = 'l.assigned_to_user_id IS NULL';
            }
            if (!$scopeParts) {
                return [];
            }
            $conditions[] = '(' . implode(' OR ', $scopeParts) . ')';
        }

        $sql = 'SELECT l.*, u.name AS assigned_name FROM leads l LEFT JOIN users u ON u.id = l.assigned_to_user_id
                WHERE ' . implode(' AND ', $conditions) . ' ORDER BY l.created_at DESC LIMIT 20';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM leads WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Lead existente com o mesmo WhatsApp (so digitos, ignorando mascara) -- usado pra nao
     *  duplicar o pre-lead quando a mesma pessoa preenche o popup de /comprar de novo (Fase 36:
     *  "nao podemos ter o mesmo lead em dois CRMs diferentes"). Pega o mais recente se houver
     *  mais de um (nao deveria, mas dados antigos podem ter). */
    public static function findByWhatsapp(string $whatsapp): ?array
    {
        $digits = preg_replace('/\D/', '', $whatsapp);
        if ($digits === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            "SELECT * FROM leads WHERE REGEXP_REPLACE(whatsapp, '[^0-9]', '') = :wa ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute(['wa' => $digits]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Data do lead mais recente ja atribuido a cada um de $userIds -- usado pelo GeoMatch pra
     *  fazer rodizio meritocratico entre Vendedores empatados no mesmo raio (quem recebeu um lead
     *  ha mais tempo entra na frente da fila). Quem nunca recebeu nenhum nao aparece no resultado
     *  (o caller trata ausencia como "sempre na frente da fila"). */
    public static function lastAssignedAt(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT assigned_to_user_id, MAX(created_at) AS last_at FROM leads
             WHERE assigned_to_user_id IN ({$placeholders}) GROUP BY assigned_to_user_id"
        );
        $stmt->execute(array_values($userIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['assigned_to_user_id']] = $row['last_at'];
        }
        return $result;
    }

    /** Ao atribuir a um Vendedor, comeca a contar 30 dias (leads.expires_at) -- se ele nao
     *  converter a tempo, o lead volta pro Licenciado da rede (ver expireStaleAssignments()).
     *  Atribuir a qualquer outro papel (Licenciado, admin etc) ou desatribuir (null) zera o prazo
     *  -- so o Vendedor tem essa pressao de tempo. */
    public static function assignTo(int $id, ?int $userId): void
    {
        $expiresAt = null;
        if ($userId) {
            $user = User::find($userId);
            if ($user && $user['role_slug'] === 'vendedor') {
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            }
        }

        $stmt = Database::connection()->prepare('UPDATE leads SET assigned_to_user_id = :uid, expires_at = :exp WHERE id = :id');
        $stmt->execute(['uid' => $userId, 'exp' => $expiresAt, 'id' => $id]);
    }

    /** Marca de qual Influenciador esse Lead veio (Fase 56, ?inf=<id> -- nunca afeta o dono/
     *  vendedor do Lead, so' pra estatistica/comissao do influenciador). Mesmo padrao de
     *  assignTo(): sempre reatribui quando um link novo e' clicado (ultimo clique vale, igual
     *  a atribuicao por ?ref= de Licenciado/Vendedor ja funciona). */
    public static function setInfluencer(int $id, ?int $influencerId): void
    {
        $stmt = Database::connection()->prepare('UPDATE leads SET influencer_id = :inf WHERE id = :id');
        $stmt->execute(['inf' => $influencerId, 'id' => $id]);
    }

    /** Leads/orcamentos/vendas originados do link de UM Influenciador -- usado no painel dele
     *  (Fase 56). Junta ate a Venda (via quotes.converted_order_id) e a comissao dele nessa
     *  venda especifica, pra ele ver nome/telefone/orcamento/venda/comissao numa linha so. */
    public static function forInfluencer(int $influencerId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT l.id, l.name, l.whatsapp, l.city, l.created_at,
                    q.id AS quote_id, q.total_value AS quote_value, q.status AS quote_status,
                    o.id AS order_id, o.status AS order_status, o.total_value AS order_value,
                    comm.amount AS commission_amount, comm.status AS commission_status
             FROM leads l
             LEFT JOIN quotes q ON q.lead_id = l.id
             LEFT JOIN orders o ON o.id = q.converted_order_id
             LEFT JOIN commissions comm ON comm.order_id = o.id AND comm.beneficiary_id = l.influencer_id
             WHERE l.influencer_id = :inf
             ORDER BY l.created_at DESC"
        );
        $stmt->execute(['inf' => $influencerId]);
        return $stmt->fetchAll();
    }

    /** Lead com um Vendedor ha mais de 30 dias sem converter/descartar volta pro Licenciado da
     *  MESMA rede (nunca fica "sem responsavel" global -- isso vazaria pra outras redes, mesmo
     *  problema que a Fase 35 corrigiu pro orcamento sem Vendedor no raio). Lazy-check (sem cron
     *  nesse plano Hostinger), chamado em LeadController::index() a cada carga da tela -- mesmo
     *  padrao de Order::expireStalePending(). */
    public static function expireStaleAssignments(): int
    {
        $stale = Database::connection()->query(
            "SELECT l.id, u.id AS vendedor_id
             FROM leads l
             JOIN users u ON u.id = l.assigned_to_user_id
             JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'vendedor' AND l.expires_at IS NOT NULL AND l.expires_at < NOW()
                AND l.status NOT IN ('convertido', 'descartado')"
        )->fetchAll();

        $count = 0;
        foreach ($stale as $row) {
            $licenciadoId = User::licenciadoIdFor((int) $row['vendedor_id']);
            if (!$licenciadoId) {
                continue;
            }
            $vendedorId = (int) $row['vendedor_id'];
            self::assignTo((int) $row['id'], $licenciadoId);
            AuditLog::record($vendedorId, 'lead_expirado_devolvido', 'lead', (int) $row['id'],
                ['assigned_to_user_id' => $vendedorId], ['assigned_to_user_id' => $licenciadoId]);
            $count++;
        }
        return $count;
    }

    /** Justificativa do Vendedor pra nao perder o lead -- estende 30 dias a partir de agora e
     *  deixa um registro permanente (ver LeadExtensionRequest) visivel pro Licenciado/Supervisor/
     *  Gerente/Admin da rede, mesmo sem precisar de aprovacao de ninguem (decisao do usuario:
     *  extensao e' self-service, o historico e' so pra auditoria). */
    public static function requestExtension(int $leadId, int $requestedBy, string $justification, ?array $attachment): void
    {
        $lead = self::find($leadId);
        if (!$lead) {
            throw new \RuntimeException('Lead não encontrado.');
        }

        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        LeadExtensionRequest::create([
            'lead_id' => $leadId,
            'requested_by_user_id' => $requestedBy,
            'justification' => $justification,
            'attachment_path' => $attachment['stored_name'] ?? null,
            'previous_expires_at' => $lead['expires_at'],
            'new_expires_at' => $newExpiresAt,
        ]);

        $stmt = Database::connection()->prepare('UPDATE leads SET expires_at = :exp WHERE id = :id');
        $stmt->execute(['exp' => $newExpiresAt, 'id' => $leadId]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM leads WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
    }

    /** Total de leads criados no periodo -- usado no funil de conversao. $userIds null = sem
     *  escopo (Admin, conta tudo inclusive sem atribuicao); array = so leads atribuidos a alguem
     *  do escopo (nao entra "sem atribuicao", que nao pertence a rede de ninguem especifico). */
    public static function countInRange(string $from, string $to, ?array $userIds = null): int
    {
        $sql = 'SELECT COUNT(*) FROM leads WHERE created_at BETWEEN :from AND :to';
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];

        if ($userIds !== null) {
            if (!$userIds) {
                return 0;
            }
            $names = [];
            foreach (array_values($userIds) as $i => $uid) {
                $key = "uid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $uid;
            }
            $sql .= ' AND assigned_to_user_id IN (' . implode(',', $names) . ')';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Leads criados no periodo, agrupados por quem esta atribuido -- usado na quebra por
     *  vendedor/licenciado do funil de conversao. Ignora leads sem atribuicao (nao pertencem a
     *  ninguem especifico da tabela). */
    public static function funnelBySeller(string $from, string $to, ?array $userIds = null): array
    {
        $sql = 'SELECT assigned_to_user_id AS user_id, COUNT(*) AS lead_count
                FROM leads WHERE created_at BETWEEN :from AND :to AND assigned_to_user_id IS NOT NULL';
        $params = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];

        if ($userIds !== null) {
            if (!$userIds) {
                return [];
            }
            $names = [];
            foreach (array_values($userIds) as $i => $uid) {
                $key = "uid{$i}";
                $names[] = ":{$key}";
                $params[$key] = $uid;
            }
            $sql .= ' AND assigned_to_user_id IN (' . implode(',', $names) . ')';
        }
        $sql .= ' GROUP BY assigned_to_user_id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['user_id']] = (int) $row['lead_count'];
        }
        return $result;
    }
}
