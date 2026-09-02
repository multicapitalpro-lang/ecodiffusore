<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

class UserController
{
    public function index(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        if ($user['role_slug'] === 'admin') {
            $users = User::all();
        } else {
            $downline = User::downlineIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
        }

        View::render('painel/users/index', [
            'user' => $user,
            'users' => $users,
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        View::render('painel/users/form', [
            'user' => $user,
            'roles' => $this->creatableRoles($user),
            'managers' => $this->managerOptions($user, null),
            'supervisors' => $this->supervisorOptions($user),
            'canSetCommission' => $this->canSetCommission($user),
            'editing' => null,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/usuarios/novo?erro=1');
        }

        $errors = $this->validate($_POST, null, $user);

        if ($errors) {
            View::render('painel/users/form', [
                'user' => $user,
                'roles' => $this->creatableRoles($user),
                'managers' => $this->managerOptions($user, null),
                'supervisors' => $this->supervisorOptions($user),
                'canSetCommission' => $this->canSetCommission($user),
                'editing' => null,
                'errors' => $errors,
                'old' => $_POST,
            ]);
            return;
        }

        $managerId = $this->resolveManagerId($user, $_POST, null);
        $commissionPct = $this->canSetCommission($user) && !empty($_POST['commission_pct']) ? $_POST['commission_pct'] : null;

        $createdRoleSlug = null;
        foreach (Role::all() as $r) {
            if ((int) $r['id'] === (int) $_POST['role_id']) {
                $createdRoleSlug = $r['slug'];
                break;
            }
        }

        $newUserId = User::create([
            'role_id' => (int) $_POST['role_id'],
            'manager_id' => $managerId,
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'password' => $_POST['password'],
            'status' => $_POST['status'] ?? 'active',
            'commission_pct' => $commissionPct,
            'must_change_password' => true,
            'licenciado_onboarding_status' => $createdRoleSlug === 'licenciado' ? 'aguardando_perfil' : 'nao_aplicavel',
        ]);

        if ($createdRoleSlug === 'licenciado') {
            // Supervisor cadastrando o proprio Licenciado ja assume a supervisao na hora -- evita
            // um passo manual redundante. Admin/Gerente podem escolher o supervisor direto no
            // formulario agora, em vez de precisar ir na tela /painel/licenciados depois.
            if ($user['role_slug'] === 'supervisor') {
                User::setSupervisor($newUserId, (int) $user['id']);
            } else {
                $supervisorId = $this->resolveSupervisorId($user, $_POST);
                if ($supervisorId !== null) {
                    User::setSupervisor($newUserId, $supervisorId);
                }
            }
        }

        Router::redirect('/painel/usuarios?sucesso=1');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();
        $id = (int) $id;

        $editing = User::find($id);
        if (!$editing) {
            Router::redirect('/painel/usuarios');
        }

        $this->authorizeTarget($user, $id);

        View::render('painel/users/form', [
            'user' => $user,
            'roles' => $this->creatableRoles($user),
            'managers' => $this->managerOptions($user, $id),
            'supervisors' => $this->supervisorOptions($user),
            'canSetCommission' => $this->canSetCommission($user),
            'editing' => $editing,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(Roles::USER_MANAGEMENT);
        $user = Auth::user();
        $id = (int) $id;

        $this->authorizeTarget($user, $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/usuarios/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST, $id, $user);

        $managerId = $this->resolveManagerId($user, $_POST, $id);
        if ($managerId !== null && $this->createsCycle($id, $managerId)) {
            $errors['manager_id'] = 'Essa escolha criaria um ciclo na hierarquia (ex: A reporta pra B que reporta pra A).';
        }

        if ($errors) {
            View::render('painel/users/form', [
                'user' => $user,
                'roles' => $this->creatableRoles($user),
                'managers' => $this->managerOptions($user, $id),
                'supervisors' => $this->supervisorOptions($user),
                'canSetCommission' => $this->canSetCommission($user),
                'editing' => array_merge(['id' => $id], $_POST),
                'errors' => $errors,
            ]);
            return;
        }

        $before = User::find($id);

        $commissionPct = $this->canSetCommission($user) && !empty($_POST['commission_pct']) ? $_POST['commission_pct'] : ($before['commission_pct'] ?? null);
        $discountLimitPct = $_POST['discount_limit_pct'] !== '' ? $_POST['discount_limit_pct'] : null;

        User::update($id, [
            'role_id' => (int) $_POST['role_id'],
            'manager_id' => $managerId,
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
            'commission_pct' => $commissionPct,
            'discount_limit_pct' => $discountLimitPct,
        ]);

        $this->logIfChanged($before, 'commission_pct', $commissionPct, $id);
        $this->logIfChanged($before, 'discount_limit_pct', $discountLimitPct, $id);
        $this->logIfChanged($before, 'manager_id', $managerId, $id);

        if ($before['role_slug'] === 'licenciado' && in_array($user['role_slug'], Roles::SUPERVISOR_ASSIGNMENT, true)) {
            $supervisorId = $this->resolveSupervisorId($user, $_POST);
            if ((string) ($before['supervisor_id'] ?? '') !== (string) $supervisorId) {
                User::setSupervisor($id, $supervisorId);
                AuditLog::record((int) $user['id'], 'licenciado_supervisor_alterado', 'user', $id, ['supervisor_id' => $before['supervisor_id'] ?? null], ['supervisor_id' => $supervisorId]);
            }
        }

        if (!empty($_POST['reset_password'])) {
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            User::resetPassword($id, $temp);
            Router::redirect("/painel/usuarios?sucesso=2&temp={$temp}");
        }

        Router::redirect('/painel/usuarios?sucesso=1');
    }

    /** Papeis que quem esta logado tem permissao de atribuir a um novo/editado usuario */
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

        // gestor: so pode cadastrar vendedor
        return array_values(array_filter($all, fn ($r) => $r['slug'] === 'vendedor'));
    }

    /** Opcoes de "reporta para" restritas a propria regiao (nunca deixa escolher fora dela) */
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

        // gestor: unica opcao e ele mesmo
        return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'gestor']];
    }

    /**
     * Supervisores que quem esta logado pode atribuir a um Licenciado (campo separado de
     * "reporta para" -- supervisor_id nao e manager_id, ver Fase 12). So Admin/Gerente atribuem;
     * Gerente so pode indicar supervisor da propria equipe (mesma regra de assignSupervisor()).
     */
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

    /**
     * So admin e licenciado definem commission_pct de alguem: licenciado controla o quanto repassa
     * do proprio pool (gestor/vendedor); admin define o % contratual do licenciado e o % de
     * gerente/supervisor (pago direto pela Ecodiffusore). Gestor/Gerente nunca definem comissao.
     */
    private function canSetCommission(array $user): bool
    {
        return in_array($user['role_slug'], ['admin', Roles::REGIONAL_OWNER], true);
    }

    private function resolveManagerId(array $user, array $input, ?int $targetId): ?int
    {
        if ($user['role_slug'] === 'admin') {
            return !empty($input['manager_id']) ? (int) $input['manager_id'] : null;
        }

        // Editando o proprio cadastro: mantem o manager_id que ja tinha (nao faz sentido
        // "trocar quem reporta pra si mesmo" pela propria tela de edicao).
        if ($targetId !== null && $targetId === (int) $user['id']) {
            $current = User::find($targetId);
            return $current && $current['manager_id'] !== null ? (int) $current['manager_id'] : null;
        }

        if (in_array($user['role_slug'], ['gestor', 'gerente', 'supervisor'], true)) {
            return (int) $user['id'];
        }

        // licenciado: so aceita a si mesmo ou um dos proprios gestores
        $allowed = array_map(fn ($m) => (int) $m['id'], $this->managerOptions($user, null));
        $chosen = !empty($input['manager_id']) ? (int) $input['manager_id'] : (int) $user['id'];
        return in_array($chosen, $allowed, true) ? $chosen : (int) $user['id'];
    }

    /** Bloqueia edicao/leitura de gente fora da propria regiao/equipe */
    private function authorizeTarget(array $user, int $targetId): void
    {
        if ($user['role_slug'] === 'admin') {
            return;
        }

        if (!in_array($targetId, User::downlineIds((int) $user['id']), true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }

    private function logIfChanged(array $before, string $field, mixed $newValue, int $userId): void
    {
        $oldValue = $before[$field] ?? null;
        if ((string) $oldValue === (string) $newValue) {
            return;
        }

        AuditLog::record(
            (int) Auth::user()['id'],
            "usuario_{$field}_alterado",
            'user',
            $userId,
            [$field => $oldValue],
            [$field => $newValue]
        );
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

    private function validate(array $input, ?int $exceptId, array $creator): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome.';
        }

        $email = trim($input['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        } elseif (User::emailExists($email, $exceptId)) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }

        if (empty($input['role_id'])) {
            $errors['role_id'] = 'Selecione um papel.';
        } else {
            $allowedRoleIds = array_map(fn ($r) => (int) $r['id'], $this->creatableRoles($creator));
            // Editar o proprio cadastro sem trocar o papel atual e sempre permitido (nao exige
            // permissao de "atribuir" um papel que a pessoa ja tem).
            $currentRoleId = $exceptId !== null ? (int) (User::find($exceptId)['role_id'] ?? 0) : null;
            $keepsOwnRole = $currentRoleId !== null && $currentRoleId === (int) $input['role_id'];

            if (!$keepsOwnRole && !in_array((int) $input['role_id'], $allowedRoleIds, true)) {
                $errors['role_id'] = 'Você não tem permissão para atribuir este papel.';
            }
        }

        if ($exceptId === null && strlen($input['password'] ?? '') < 8) {
            $errors['password'] = 'A senha precisa ter pelo menos 8 caracteres.';
        }

        return $errors;
    }

    public function licenciados(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();

        $supervisors = User::allByRole('supervisor');
        if ($user['role_slug'] === 'gerente') {
            $supervisors = array_values(array_filter($supervisors, fn ($s) => (int) $s['manager_id'] === (int) $user['id']));
        }

        View::render('painel/users/licenciados', [
            'user' => $user,
            'licenciados' => User::allByRole('licenciado'),
            'supervisors' => $supervisors,
        ]);
    }

    public function assignSupervisor(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $licenciado = User::find($id);
        if (!$licenciado || $licenciado['role_slug'] !== 'licenciado') {
            Router::redirect('/painel/licenciados?erro=1');
        }

        $supervisorId = !empty($_POST['supervisor_id']) ? (int) $_POST['supervisor_id'] : null;

        if ($supervisorId !== null) {
            $supervisor = User::find($supervisorId);
            // Gerente so pode apontar pra um Supervisor da propria equipe -- sem isso, um Gerente
            // poderia atribuir licenciados a supervisor de outro Gerente via POST direto.
            $allowed = $user['role_slug'] === 'admin'
                || ($supervisor && $supervisor['role_slug'] === 'supervisor' && (int) $supervisor['manager_id'] === (int) $user['id']);
            if (!$supervisor || $supervisor['role_slug'] !== 'supervisor' || !$allowed) {
                Router::redirect('/painel/licenciados?erro=1');
            }
        }

        $before = $licenciado['supervisor_id'] ?? null;
        User::setSupervisor($id, $supervisorId);
        if ((string) $before !== (string) $supervisorId) {
            AuditLog::record((int) $user['id'], 'licenciado_supervisor_alterado', 'user', $id, ['supervisor_id' => $before], ['supervisor_id' => $supervisorId]);
        }

        Router::redirect('/painel/licenciados?sucesso=1');
    }
}
