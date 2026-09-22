<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Notifier;
use App\Core\Roles;
use App\Models\Role;
use App\Models\User;

/** Fase 76h/j: Minha Rede pro app -- leitura + cadastro/edicao basico, mesmo escopo por
 *  hierarquia de App\Controllers\UserController. Versao reduzida de proposito: sem grade de
 *  comissao por faixa do vendedor, permissao granular de tela e comissao customizada de
 *  influenciador -- essas ficam no painel web (configuracao rara, faz mais sentido no desktop).
 *  O essencial (nome/email/papel/hierarquia/comissao) cobre o uso do dia a dia. */
class UserController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::USER_MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Minha Rede.', 403);
        }

        if ($user['role_slug'] === 'admin') {
            $scoped = User::all();
        } else {
            $ids = $this->scopedUserIds($user);
            $scoped = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $ids, true)));
        }

        ApiResponse::json(['users' => array_map(fn ($u) => $this->publicUser($u), $scoped)]);
    }

    public function show(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::USER_MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Minha Rede.', 403);
        }

        $id = (int) $id;
        $target = User::find($id);
        if (!$target || !$this->isAuthorizedTarget($user, $id)) {
            ApiResponse::error('Usuario nao encontrado.', 404);
        }

        ApiResponse::json(['user' => $this->publicUser($target)]);
    }

    /** Opcoes pro formulario (papeis atribuiveis, "reporta para", supervisores) -- o app monta
     *  os seletores a partir disso, mesmo dado que UserController::create()/edit() ja calculam. */
    public function options(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::USER_MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao.', 403);
        }

        ApiResponse::json([
            'roles' => array_map(fn ($r) => ['id' => (int) $r['id'], 'slug' => $r['slug'], 'name' => $r['name']], $this->creatableRoles($user)),
            'managers' => array_map(fn ($m) => ['id' => (int) $m['id'], 'name' => $m['name'], 'role_slug' => $m['role_slug']], $this->managerOptions($user, null)),
            'supervisors' => array_map(fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $this->supervisorOptions($user)),
            'can_set_commission' => $this->canSetCommission($user),
        ]);
    }

    public function store(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::USER_MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra cadastrar.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $errors = $this->validate($body, null, $user);
        if ($errors) {
            ApiResponse::json(['errors' => $errors], 422);
        }

        $managerId = $this->resolveManagerId($user, $body, null);
        $commissionPct = $this->canSetCommission($user) && !empty($body['commission_pct']) ? $body['commission_pct'] : null;

        $createdRoleSlug = null;
        foreach (Role::all() as $r) {
            if ((int) $r['id'] === (int) $body['role_id']) {
                $createdRoleSlug = $r['slug'];
                break;
            }
        }

        $newUserId = User::create([
            'role_id' => (int) $body['role_id'],
            'manager_id' => $managerId,
            'name' => trim($body['name']),
            'email' => trim($body['email']),
            'whatsapp' => trim($body['whatsapp'] ?? ''),
            'city' => trim($body['city'] ?? ''),
            'state' => trim($body['state'] ?? ''),
            'password' => $body['password'],
            'status' => 'active',
            'commission_pct' => $commissionPct,
            'must_change_password' => true,
            'licenciado_onboarding_status' => $createdRoleSlug === 'licenciado' ? 'aguardando_perfil' : 'nao_aplicavel',
        ]);

        if (in_array($createdRoleSlug, ['gestor', 'vendedor'], true)) {
            $token = User::generateCommissionAcceptToken($newUserId);
            Notifier::propostaComissaoCriada(User::find($newUserId), $token);
        }

        if ($createdRoleSlug === 'licenciado') {
            User::assignLicenciadoCode($newUserId);
            if ($user['role_slug'] === 'supervisor') {
                User::setSupervisor($newUserId, (int) $user['id']);
            } else {
                $supervisorId = $this->resolveSupervisorId($user, $body);
                if ($supervisorId !== null) {
                    User::setSupervisor($newUserId, $supervisorId);
                }
            }
        }

        ApiResponse::json(['user' => $this->publicUser(User::find($newUserId))], 201);
    }

    public function update(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::USER_MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra editar.', 403);
        }

        $id = (int) $id;
        if (!$this->isAuthorizedTarget($user, $id)) {
            ApiResponse::error('Sem permissao pra editar este usuario.', 403);
        }
        $existing = User::find($id);
        if (!$existing) {
            ApiResponse::error('Usuario nao encontrado.', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $errors = $this->validate($body, $id, $user);

        $managerId = $this->resolveManagerId($user, $body, $id);
        if ($managerId !== null && $this->createsCycle($id, $managerId)) {
            $errors['manager_id'] = 'Essa escolha criaria um ciclo na hierarquia.';
        }
        if ($errors) {
            ApiResponse::json(['errors' => $errors], 422);
        }

        $commissionPct = $this->canSetCommission($user) && !empty($body['commission_pct']) ? $body['commission_pct'] : $existing['commission_pct'];

        User::update($id, [
            'role_id' => (int) $body['role_id'],
            'manager_id' => $managerId,
            'name' => trim($body['name']),
            'email' => trim($body['email']),
            'whatsapp' => trim($body['whatsapp'] ?? ''),
            'city' => trim($body['city'] ?? ''),
            'state' => trim($body['state'] ?? ''),
            'status' => $body['status'] ?? $existing['status'],
            'commission_pct' => $commissionPct,
            'influencer_commission_value' => $existing['influencer_commission_value'],
            'commission_type' => $existing['commission_type'],
            'discount_limit_pct' => $existing['discount_limit_pct'],
        ]);

        ApiResponse::json(['user' => $this->publicUser(User::find($id))]);
    }

    private function publicUser(array $u): array
    {
        return [
            'id' => (int) $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'whatsapp' => $u['whatsapp'],
            'role_id' => (int) $u['role_id'],
            'role_slug' => $u['role_slug'],
            'role_name' => $u['role_name'],
            'manager_id' => $u['manager_id'] !== null ? (int) $u['manager_id'] : null,
            'status' => $u['status'],
            'city' => $u['city'] ?? null,
            'state' => $u['state'] ?? null,
            'licenciado_code' => $u['licenciado_code'] ?? null,
            'commission_pct' => $u['commission_pct'] !== null ? (float) $u['commission_pct'] : null,
        ];
    }

    private function validate(array $input, ?int $exceptId, array $creator): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome.';
        }

        $email = trim($input['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail invalido.';
        } elseif (User::emailExists($email, $exceptId)) {
            $errors['email'] = 'Ja existe um usuario com este e-mail.';
        }

        if (empty($input['role_id'])) {
            $errors['role_id'] = 'Selecione um papel.';
        } else {
            $allowedRoleIds = array_map(fn ($r) => (int) $r['id'], $this->creatableRoles($creator));
            $currentRoleId = $exceptId !== null ? (int) (User::find($exceptId)['role_id'] ?? 0) : null;
            $keepsOwnRole = $currentRoleId !== null && $currentRoleId === (int) $input['role_id'];

            if (!$keepsOwnRole && !in_array((int) $input['role_id'], $allowedRoleIds, true)) {
                $errors['role_id'] = 'Voce nao tem permissao pra atribuir este papel.';
            }
        }

        if ($exceptId === null && strlen($input['password'] ?? '') < 8) {
            $errors['password'] = 'A senha precisa ter pelo menos 8 caracteres.';
        }

        return $errors;
    }

    private function creatableRoles(array $user): array
    {
        $all = Role::all();

        if ($user['role_slug'] === 'admin') {
            return $all;
        }
        if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
            return array_values(array_filter($all, fn ($r) => in_array($r['slug'], ['gestor', 'vendedor'], true)));
        }
        if ($user['role_slug'] === 'gerente') {
            return array_values(array_filter($all, fn ($r) => in_array($r['slug'], ['supervisor', 'licenciado'], true)));
        }
        if ($user['role_slug'] === 'supervisor') {
            return array_values(array_filter($all, fn ($r) => $r['slug'] === 'licenciado'));
        }

        return array_values(array_filter($all, fn ($r) => $r['slug'] === 'vendedor'));
    }

    private function managerOptions(array $user, ?int $editingId): array
    {
        if ($user['role_slug'] === 'admin') {
            return User::managerCandidates($editingId);
        }
        if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
            $downline = User::downlineIds((int) $user['id']);
            $gestores = array_values(array_filter(User::allByRole('gestor'), fn ($g) => in_array((int) $g['id'], $downline, true)));
            $gestores = array_map(fn ($g) => $g + ['role_slug' => 'gestor'], $gestores);
            return array_merge([['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'licenciado']], $gestores);
        }
        if ($user['role_slug'] === 'gerente') {
            return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'gerente']];
        }
        if ($user['role_slug'] === 'supervisor') {
            return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'supervisor']];
        }

        return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'gestor']];
    }

    private function supervisorOptions(array $user): array
    {
        if (!in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            return [];
        }
        $supervisors = User::allByRole('supervisor');
        if ($user['role_slug'] === 'gerente') {
            $supervisors = array_values(array_filter($supervisors, fn ($s) => (int) $s['manager_id'] === (int) $user['id']));
        }
        return $supervisors;
    }

    private function resolveSupervisorId(array $user, array $input): ?int
    {
        if (empty($input['supervisor_id'])) {
            return null;
        }
        $allowed = array_map(fn ($s) => (int) $s['id'], $this->supervisorOptions($user));
        $chosen = (int) $input['supervisor_id'];
        return in_array($chosen, $allowed, true) ? $chosen : null;
    }

    private function canSetCommission(array $user): bool
    {
        return in_array($user['role_slug'], ['admin', Roles::REGIONAL_OWNER], true);
    }

    private function resolveManagerId(array $user, array $input, ?int $targetId): ?int
    {
        if ($user['role_slug'] === 'admin') {
            return !empty($input['manager_id']) ? (int) $input['manager_id'] : null;
        }

        if ($targetId !== null && $targetId === (int) $user['id']) {
            $current = User::find($targetId);
            return $current && $current['manager_id'] !== null ? (int) $current['manager_id'] : null;
        }

        if (in_array($user['role_slug'], ['gestor', 'gerente', 'supervisor'], true)) {
            return (int) $user['id'];
        }

        $allowed = array_map(fn ($m) => (int) $m['id'], $this->managerOptions($user, null));
        $chosen = !empty($input['manager_id']) ? (int) $input['manager_id'] : (int) $user['id'];
        return in_array($chosen, $allowed, true) ? $chosen : (int) $user['id'];
    }

    private function isAuthorizedTarget(array $user, int $targetId): bool
    {
        return $user['role_slug'] === 'admin' || in_array($targetId, $this->scopedUserIds($user), true);
    }

    /** Mesmo criterio de App\Controllers\UserController::scopedUserIds() (Fase 79c) -- Gerente/
     *  Supervisor seguem a rede nacional (nationalIds/supervisedIds), nao a downline por
     *  manager_id (que so serve pra Gestor/Licenciado). Bug ja corrigido no painel web; faltava
     *  aqui na API do app. */
    private function scopedUserIds(array $user): array
    {
        if ($user['role_slug'] === 'gerente') {
            return User::nationalIds((int) $user['id']);
        }
        if ($user['role_slug'] === 'supervisor') {
            return User::supervisedIds((int) $user['id']);
        }
        return User::downlineIds((int) $user['id']);
    }

    private function createsCycle(int $userId, int $candidateManagerId): bool
    {
        if ($candidateManagerId === $userId) {
            return true;
        }

        foreach (User::managerChain($candidateManagerId) as $manager) {
            if ((int) $manager['id'] === $userId) {
                return true;
            }
        }

        return false;
    }
}
