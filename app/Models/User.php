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

    public static function allByRole(string $roleSlug): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = :slug AND u.status = 'active' ORDER BY u.name"
        );
        $stmt->execute(['slug' => $roleSlug]);
        return $stmt->fetchAll();
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
                commission_type, commission_value_baixo, commission_value_alto, must_change_password, email_verified_at, licenciado_onboarding_status)
             VALUES (:role_id, :manager_id, :name, :email, :whatsapp, :city, :state, :password_hash, :status, :commission_pct,
                :commission_type, :commission_value_baixo, :commission_value_alto, :must_change_password, :email_verified_at, :licenciado_onboarding_status)'
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
            'commission_type' => $data['commission_type'] ?? null,
            'commission_value_baixo' => $data['commission_value_baixo'] ?? null,
            'commission_value_alto' => $data['commission_value_alto'] ?? null,
            'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
            'email_verified_at' => $emailVerified ? date('Y-m-d H:i:s') : null,
            'licenciado_onboarding_status' => $data['licenciado_onboarding_status'] ?? 'nao_aplicavel',
        ]);

        return (int) Database::connection()->lastInsertId();
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
                commission_type = :commission_type, commission_value_baixo = :commission_value_baixo, commission_value_alto = :commission_value_alto,
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
            'commission_type' => $data['commission_type'] ?? null,
            'commission_value_baixo' => $data['commission_value_baixo'] ?? null,
            'commission_value_alto' => $data['commission_value_alto'] ?? null,
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
