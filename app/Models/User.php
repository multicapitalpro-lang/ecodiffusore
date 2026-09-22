<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Quem "cuida" de um usuario na hierarquia comercial, pra exibicao (ex: coluna Responsavel
     * na tela de Usuarios). Regras dadas pelo cliente: Vendedor/Gestor -> o Licenciado da regiao
     * (sobe a cadeia de manager_id ate achar um); Licenciado -> o Supervisor responsavel
     * (supervisor_id); Supervisor -> o Gerente que o cadastrou (manager_id); Gerente -> ele mesmo
     * (topo da cadeia nacional, sem ninguem acima). Cliente/Admin nao tem "responsavel" nesse
     * sentido comercial.
     */
    /** Registro completo do Licenciado dono da rede de um vendedor/gestor (sobe a cadeia de
     *  manager_id) -- ou o proprio registro, se $sellerId ja for um Licenciado. Base compartilhada
     *  de licenciadoNameFor()/licenciadoIdFor(). */
    public static function licenciadoFor(?int $sellerId): ?array
    {
        if (!$sellerId) {
            return null;
        }

        $current = self::find($sellerId);
        for ($i = 0; $i < 10 && $current; $i++) {
            if ($current['role_slug'] === 'licenciado') {
                return $current;
            }
            if (empty($current['manager_id'])) {
                return null;
            }
            $current = self::find((int) $current['manager_id']);
        }
        return null;
    }

    /** Nome do Licenciado dono da rede de um vendedor/gestor. Usado nas telas de CRM (Leads/
     *  Pedidos/Orcamentos/Clientes) pra Supervisor/Gerente verem de qual rede cada registro e',
     *  sem precisar entrar no CRM de cada Licenciado individualmente (a funcao do Supervisor e'
     *  supervisionar Licenciados, nao Vendedores diretamente). */
    public static function licenciadoNameFor(?int $sellerId): ?string
    {
        return self::licenciadoFor($sellerId)['name'] ?? null;
    }

    /** Id do Licenciado dono da rede de um vendedor/gestor -- usado pra devolver um lead expirado
     *  pra dentro da mesma rede (nunca fica "sem responsavel" global, ver Lead::expireStaleAssignments()). */
    public static function licenciadoIdFor(?int $sellerId): ?int
    {
        $licenciado = self::licenciadoFor($sellerId);
        return $licenciado ? (int) $licenciado['id'] : null;
    }

    public static function responsibleFor(array $target): ?array
    {
        switch ($target['role_slug'] ?? null) {
            case 'gerente':
                return $target;
            case 'supervisor':
                return !empty($target['manager_id']) ? self::find((int) $target['manager_id']) : null;
            case 'licenciado':
                return !empty($target['supervisor_id']) ? self::find((int) $target['supervisor_id']) : null;
            case 'gestor':
            case 'vendedor':
                $current = $target;
                for ($i = 0; $i < 10 && !empty($current['manager_id']); $i++) {
                    $current = self::find((int) $current['manager_id']);
                    if (!$current) {
                        return null;
                    }
                    if ($current['role_slug'] === 'licenciado') {
                        return $current;
                    }
                }
                return null;
            default:
                return null;
        }
    }

    /** Delete de verdade -- FKs sem ON DELETE CASCADE/SET NULL (ex: commissions) bloqueiam com
     * PDOException se o usuario tiver historico financeiro vinculado; o controller trata isso
     * como "nao pode excluir" em vez de deixar o registro sumir e quebrar relatorios antigos. */
    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    /** Busca por nome/CPF/telefone dentro de um ou mais papeis -- usado pela Fabrica (Fase 39) pra
     *  conferir se alguem que apareceu direto com ela ja e' Licenciado/Vendedor/Gestor nosso, sem
     *  dar acesso a nenhum outro dado (nunca email/senha/comissao aqui, so o necessario pra
     *  identificar a pessoa). cpf_representante so existe pra quem passou pelo onboarding de
     *  Licenciado (Fase 17/18) -- Gestor/Vendedor nunca tem CPF cadastrado, so nome+whatsapp mesmo. */
    public static function searchByRoles(array $roleSlugs, string $term, int $limit = 20): array
    {
        $digits = preg_replace('/\D/', '', $term);
        $conditions = ['u.name LIKE :term'];
        $params = ['term' => '%' . $term . '%'];
        if ($digits !== '') {
            $conditions[] = "REGEXP_REPLACE(u.whatsapp, '[^0-9]', '') LIKE :digits";
            $conditions[] = "REGEXP_REPLACE(COALESCE(u.cpf_representante, ''), '[^0-9]', '') LIKE :digits2";
            $params['digits'] = '%' . $digits . '%';
            $params['digits2'] = '%' . $digits . '%';
        }

        $roleNames = [];
        foreach (array_values($roleSlugs) as $i => $slug) {
            $key = "role{$i}";
            $roleNames[] = ":{$key}";
            $params[$key] = $slug;
        }

        $sql = 'SELECT u.id, u.name, u.whatsapp, u.cpf_representante, r.slug AS role_slug, r.name AS role_name
                FROM users u JOIN roles r ON r.id = u.role_id
                WHERE r.slug IN (' . implode(',', $roleNames) . ")
                  AND u.status = 'active' AND (" . implode(' OR ', $conditions) . ')
                ORDER BY u.name LIMIT ' . (int) $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function allByRole(string $roleSlug): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = :slug AND u.status = 'active' ORDER BY u.name"
        );
        $stmt->execute(['slug' => $roleSlug]);
        return $stmt->fetchAll();
    }

    /** UFs distintas onde ja existe Licenciado ATIVO (nao so cadastrado -- passou pelo onboarding
     *  completo) -- usado na LP publica pra mostrar cobertura real, sem inventar numero. */
    public static function activeLicensedStates(): array
    {
        $stmt = Database::connection()->query(
            "SELECT DISTINCT u.state FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'licenciado' AND u.status = 'active' AND u.licenciado_onboarding_status = 'ativo'
                AND u.state IS NOT NULL AND u.state != ''
             ORDER BY u.state"
        );
        return array_column($stmt->fetchAll(), 'state');
    }

    /** Cidades com Licenciado ATIVO dentro de UM estado -- usado nas paginas locais de SEO (Fase 55),
     *  pra listar presenca real por cidade sem inventar dado. */
    public static function activeLicensedCitiesInState(string $stateUf): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT u.city FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'licenciado' AND u.status = 'active' AND u.licenciado_onboarding_status = 'ativo'
                AND u.state = :state AND u.city IS NOT NULL AND u.city != ''
             ORDER BY u.city"
        );
        $stmt->execute(['state' => $stateUf]);
        return array_column($stmt->fetchAll(), 'city');
    }

    /** Candidatos a "reporta para": todo mundo com papel gestor ou licenciado, exceto a propria pessoa */
    public static function managerCandidates(?int $exceptId = null): array
    {
        $sql = "SELECT u.id, u.name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id
                WHERE r.slug IN ('gestor', 'licenciado') AND u.status = 'active'";
        $params = [];
        if ($exceptId !== null) {
            $sql .= ' AND u.id != :id';
            $params['id'] = $exceptId;
        }
        $sql .= ' ORDER BY r.slug, u.name';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Desce a hierarquia: retorna os ids de $userId + todo mundo que reporta (direta ou
     * indiretamente) pra ele, ate 5 niveis pra evitar loop. Usado pra escopar Leads/Orcamentos
     * por papel (vendedor ve so ele mesmo, gestor ve sua equipe, licenciado ve toda a regiao).
     */
    public static function downlineIds(int $userId): array
    {
        $ids = [$userId => true];
        $frontier = [$userId];

        for ($i = 0; $i < 5 && $frontier; $i++) {
            $placeholders = implode(',', array_fill(0, count($frontier), '?'));
            $stmt = Database::connection()->prepare("SELECT id FROM users WHERE manager_id IN ({$placeholders})");
            $stmt->execute($frontier);
            $next = [];
            foreach ($stmt->fetchAll() as $row) {
                $id = (int) $row['id'];
                if (!isset($ids[$id])) {
                    $ids[$id] = true;
                    $next[] = $id;
                }
            }
            $frontier = $next;
        }

        return array_keys($ids);
    }

    /** Fase 86: quantos colaboradores ATIVOS (Gestor+Vendedor, nunca o proprio Licenciado) essa
     *  rede tem hoje -- usado contra SubscriptionPlans::INCLUDED_SEATS + vagas extras compradas
     *  (LicenciadoSeatAddon::activeSeatsFor) pra saber se cadastrar mais um exige comprar vaga. */
    public static function activeStaffCountFor(int $licenciadoId): int
    {
        $ids = array_diff(self::downlineIds($licenciadoId), [$licenciadoId]);
        if (!$ids) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id IN ({$placeholders}) AND u.status = 'active' AND r.slug IN ('gestor', 'vendedor')"
        );
        $stmt->execute(array_values($ids));
        return (int) $stmt->fetchColumn();
    }

    /** Sobe a cadeia de gestao a partir de um usuario (nao inclui ele mesmo), ate 5 niveis pra evitar loop */
    public static function managerChain(int $userId): array
    {
        $chain = [];
        $current = self::find($userId);
        $seen = [$userId => true];

        for ($i = 0; $i < 5 && $current && $current['manager_id']; $i++) {
            $managerId = (int) $current['manager_id'];
            if (isset($seen[$managerId])) {
                break;
            }
            $manager = self::find($managerId);
            if (!$manager) {
                break;
            }
            $chain[] = $manager;
            $seen[$managerId] = true;
            $current = $manager;
        }

        return $chain;
    }

    /**
     * Tudo sob a responsabilidade de um Supervisor: ele mesmo + cada licenciado que o Gerente
     * atribuiu a ele (supervisor_id) + a downline normal (manager_id: gestor/vendedor) de cada um.
     */
    public static function supervisedIds(int $supervisorId): array
    {
        $ids = [$supervisorId];

        $stmt = Database::connection()->prepare('SELECT id FROM users WHERE supervisor_id = :sid');
        $stmt->execute(['sid' => $supervisorId]);

        foreach ($stmt->fetchAll() as $row) {
            $ids = array_merge($ids, self::downlineIds((int) $row['id']));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Tudo sob a responsabilidade nacional de um Gerente: ele mesmo + cada Supervisor que
     * cadastrou (manager_id, 1 nivel) + o supervisedIds() de cada um.
     */
    public static function nationalIds(int $gerenteId): array
    {
        $ids = [$gerenteId];

        $stmt = Database::connection()->prepare('SELECT id FROM users WHERE manager_id = :gid');
        $stmt->execute(['gid' => $gerenteId]);

        foreach ($stmt->fetchAll() as $row) {
            $ids = array_merge($ids, self::supervisedIds((int) $row['id']));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Time de vendas "embaixo" de um usuario qualquer, pra calcular progresso de Meta -- escolhe
     * a travessia certa pra cada papel (supervisor usa supervisedIds, gerente usa nationalIds,
     * os demais usam downlineIds/manager_id). Pra um vendedor, retorna so ele mesmo (meta
     * individual).
     */
    public static function teamIds(int $userId): array
    {
        $target = self::find($userId);
        if (!$target) {
            return [$userId];
        }

        return match ($target['role_slug']) {
            'supervisor' => self::supervisedIds($userId),
            'gerente' => self::nationalIds($userId),
            default => self::downlineIds($userId),
        };
    }

    /**
     * Atribui/troca o supervisor de um licenciado. Acao separada de update() porque quem chama
     * isso (o Gerente) nao tem permissao de editar o resto do cadastro do licenciado.
     */
    public static function setSupervisor(int $licenciadoId, ?int $supervisorId): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET supervisor_id = :supervisor_id WHERE id = :id');
        $stmt->execute(['supervisor_id' => $supervisorId, 'id' => $licenciadoId]);
    }

    public static function create(array $data): int
    {
        $emailVerified = array_key_exists('email_verified', $data) ? !empty($data['email_verified']) : true;

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (role_id, manager_id, name, email, whatsapp, city, state, password_hash, status, commission_pct,
                influencer_commission_value, commission_type, must_change_password, email_verified_at, licenciado_onboarding_status)
             VALUES (:role_id, :manager_id, :name, :email, :whatsapp, :city, :state, :password_hash, :status, :commission_pct,
                :influencer_commission_value, :commission_type, :must_change_password, :email_verified_at, :licenciado_onboarding_status)'
        );
        $stmt->execute([
            'role_id' => $data['role_id'],
            'manager_id' => $data['manager_id'] ?: null,
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?: null,
            'city' => ($data['city'] ?? '') ?: null,
            'state' => ($data['state'] ?? '') ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => $data['status'] ?? 'active',
            'commission_pct' => $data['commission_pct'] ?? null,
            'influencer_commission_value' => $data['influencer_commission_value'] ?? null,
            'commission_type' => $data['commission_type'] ?? null,
            'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
            'email_verified_at' => $emailVerified ? date('Y-m-d H:i:s') : null,
            'licenciado_onboarding_status' => $data['licenciado_onboarding_status'] ?? 'nao_aplicavel',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** Fase 67: codigo curto proprio por Licenciado (LIC-0001, LIC-0002...) -- pedido explicito do
     *  usuario, pra Gerente/Admin acharem rapido em /painel/licenciados sem depender do id interno
     *  cru (que mistura numeracao com todos os outros papeis). MAX(...)+1 em vez de COUNT(...)+1
     *  pra nunca reaproveitar um numero, mesmo se um licenciado antigo for desativado/removido. */
    public static function assignLicenciadoCode(int $userId): string
    {
        $db = Database::connection();
        $max = (int) $db->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(licenciado_code, 5) AS UNSIGNED)), 0) FROM users WHERE licenciado_code IS NOT NULL"
        )->fetchColumn();
        $code = 'LIC-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);

        $stmt = $db->prepare('UPDATE users SET licenciado_code = :code WHERE id = :id');
        $stmt->execute(['code' => $code, 'id' => $userId]);

        return $code;
    }

    public static function setVerificationCode(int $id, string $code): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET verification_code_hash = :hash, verification_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
             WHERE id = :id'
        );
        $stmt->execute(['hash' => password_hash($code, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function verifyEmailCode(int $id, string $code): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT verification_code_hash, verification_expires_at FROM users
             WHERE id = :id AND email_verified_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row || !$row['verification_code_hash'] || !$row['verification_expires_at']) {
            return false;
        }
        if ($row['verification_expires_at'] < date('Y-m-d H:i:s')) {
            return false;
        }
        if (!password_verify($code, $row['verification_code_hash'])) {
            return false;
        }

        $update = Database::connection()->prepare(
            'UPDATE users SET email_verified_at = NOW(), verification_code_hash = NULL, verification_expires_at = NULL
             WHERE id = :id'
        );
        $update->execute(['id' => $id]);

        return true;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET role_id = :role_id, manager_id = :manager_id, name = :name, email = :email,
                whatsapp = :whatsapp, city = :city, state = :state, status = :status, commission_pct = :commission_pct,
                influencer_commission_value = :influencer_commission_value,
                commission_type = :commission_type,
                discount_limit_pct = :discount_limit_pct WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'role_id' => $data['role_id'],
            'manager_id' => $data['manager_id'] ?: null,
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?: null,
            'city' => ($data['city'] ?? '') ?: null,
            'state' => ($data['state'] ?? '') ?: null,
            'commission_pct' => $data['commission_pct'] ?? null,
            'influencer_commission_value' => $data['influencer_commission_value'] ?? null,
            'commission_type' => $data['commission_type'] ?? null,
            'discount_limit_pct' => $data['discount_limit_pct'] ?? null,
            'status' => $data['status'],
        ]);
    }

    public static function resetPassword(int $id, string $newPassword): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    public static function changePassword(int $id, string $newPassword): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    /** Gera (ou renova) o token do link de aceite de comissao (Fase 32) -- Gestor/Vendedor recebe
     *  esse link por WhatsApp quando o Licenciado cadastra/edita as condicoes dele. Zera
     *  commission_accepted_at -- um token novo significa condicoes novas, precisa aceitar de novo. */
    public static function generateCommissionAcceptToken(int $id): string
    {
        $token = bin2hex(random_bytes(24));
        $stmt = Database::connection()->prepare(
            'UPDATE users SET commission_accept_token = :token, commission_accepted_at = NULL WHERE id = :id'
        );
        $stmt->execute(['token' => $token, 'id' => $id]);
        return $token;
    }

    public static function findByCommissionAcceptToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.commission_accept_token = :token'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function acceptCommissionTerms(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET commission_accepted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public static function touchLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Libera o Vendedor do gate de treinamento obrigatorio (Fase 42) depois que
     *  SellerTrainingProgress::hasCompletedAll() confirma que ele assistiu (>=90%) todo video
     *  cadastrado. */
    public static function markTrainingCompleted(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET training_completed_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Salva o perfil completo que o Licenciado preenche no primeiro acesso (pessoa juridica +
     * representante + comprovante) e avanca o onboarding pra "aguardando_assinatura". Metodo
     * dedicado (nao reaproveita update()) porque o conjunto de campos e o contexto de quem chama
     * (o proprio Licenciado, uma vez so) sao bem diferentes do formulario administrativo.
     */
    public static function completeOnboardingProfile(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET razao_social = :razao_social, cnpj = :cnpj,
                endereco_cep = :endereco_cep, endereco_logradouro = :endereco_logradouro,
                endereco_numero = :endereco_numero, endereco_complemento = :endereco_complemento,
                endereco_bairro = :endereco_bairro, endereco_cidade = :endereco_cidade, endereco_uf = :endereco_uf,
                cpf_representante = :cpf_representante, rg_representante = :rg_representante,
                estado_civil = :estado_civil, profissao = :profissao,
                celular = :celular, telefone_fixo = :telefone_fixo,
                comprovante_residencia_path = :comprovante_residencia_path,
                documento_identidade_path = :documento_identidade_path,
                contrato_social_path = :contrato_social_path,
                cartao_cnpj_path = :cartao_cnpj_path,
                licenciado_rejection_reason = NULL,
                licenciado_onboarding_status = \'aguardando_assinatura\'
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'razao_social' => $data['razao_social'],
            'cnpj' => $data['cnpj'],
            'endereco_cep' => $data['endereco_cep'],
            'endereco_logradouro' => $data['endereco_logradouro'],
            'endereco_numero' => $data['endereco_numero'],
            'endereco_complemento' => $data['endereco_complemento'] ?: null,
            'endereco_bairro' => $data['endereco_bairro'],
            'endereco_cidade' => $data['endereco_cidade'],
            'endereco_uf' => $data['endereco_uf'],
            'cpf_representante' => $data['cpf_representante'],
            'rg_representante' => $data['rg_representante'],
            'estado_civil' => $data['estado_civil'],
            'profissao' => $data['profissao'],
            'celular' => $data['celular'],
            'telefone_fixo' => $data['telefone_fixo'] ?: null,
            'comprovante_residencia_path' => $data['comprovante_residencia_path'],
            'documento_identidade_path' => $data['documento_identidade_path'],
            'contrato_social_path' => $data['contrato_social_path'],
            'cartao_cnpj_path' => $data['cartao_cnpj_path'],
        ]);
    }

    public static function setOnboardingStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET licenciado_onboarding_status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Manda o Licenciado de volta pro formulario de perfil com o motivo da reprovacao visivel. */
    public static function rejectOnboarding(int $id, string $reason): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE users SET licenciado_onboarding_status = 'aguardando_perfil', licenciado_rejection_reason = :reason WHERE id = :id"
        );
        $stmt->execute(['reason' => $reason, 'id' => $id]);
    }

    /** Licenciados com cadastro assinado esperando aprovacao manual de Admin/Gerente. */
    public static function pendingApproval(array $viewer): array
    {
        if ($viewer['role_slug'] === 'admin') {
            $stmt = Database::connection()->query(
                "SELECT u.* FROM users u WHERE u.licenciado_onboarding_status = 'aguardando_aprovacao' ORDER BY u.updated_at ASC"
            );
            return $stmt->fetchAll();
        }

        if ($viewer['role_slug'] === 'gerente') {
            $national = self::nationalIds((int) $viewer['id']);
            if (!$national) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($national), '?'));
            $stmt = Database::connection()->prepare(
                "SELECT u.* FROM users u WHERE u.licenciado_onboarding_status = 'aguardando_aprovacao'
                 AND u.supervisor_id IN ({$placeholders}) ORDER BY u.updated_at ASC"
            );
            $stmt->execute($national);
            return $stmt->fetchAll();
        }

        return [];
    }

    public static function pendingApprovalCount(array $viewer): int
    {
        if (!in_array($viewer['role_slug'], ['admin', 'gerente'], true)) {
            return 0;
        }
        return count(self::pendingApproval($viewer));
    }
}
