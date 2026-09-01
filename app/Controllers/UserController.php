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
        Auth::requireRole(Roles::MANAGEMENT);
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
        Auth::requireRole(Roles::MANAGEMENT);
        $user = Auth::user();

        View::render('painel/users/form', [
            'user' => $user,
            'roles' => $this->creatableRoles($user),
            'managers' => $this->managerOptions($user, null),
            'canSetCommission' => $this->canSetCommission($user),
            'editing' => null,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
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
                'canSetCommission' => $this->canSetCommission($user),
                'editing' => null,
                'errors' => $errors,
                'old' => $_POST,
            ]);
            return;
        }

        $managerId = $this->resolveManagerId($user, $_POST, null);
        $commissionPct = $this->canSetCommission($user) && !empty($_POST['commission_pct']) ? $_POST['commission_pct'] : null;

        User::create([
            'role_id' => (int) $_POST['role_id'],
            'manager_id' => $managerId,
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'whatsapp' => trim($_POST['whatsapp'] ?? ''),
            'password' => $_POST['password'],
            'status' => $_POST['status'] ?? 'active',
            'commission_pct' => $commissionPct,
            'must_change_password' => true,
        ]);

        Router::redirect('/painel/usuarios?sucesso=1');
    }

    public function edit(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
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
            'canSetCommission' => $this->canSetCommission($user),
            'editing' => $editing,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
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
            'status' => $_POST['status'] ?? 'active',
            'commission_pct' => $commissionPct,
            'discount_limit_pct' => $discountLimitPct,
        ]);

        $this->logIfChanged($before, 'commission_pct', $commissionPct, $id);
        $this->logIfChanged($before, 'discount_limit_pct', $discountLimitPct, $id);
        $this->logIfChanged($before, 'manager_id', $managerId, $id);

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
            return array_values(array_filter($all, fn ($r) => in_array($r['slug'], ['gerente', 'vendedor'], true)));
        }

        // gerente: so pode cadastrar vendedor
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
            $gerentes = array_values(array_filter(User::allByRole('gerente'), fn ($g) => in_array((int) $g['id'], $downline, true)));
            $gerentes = array_map(fn ($g) => $g + ['role_slug' => 'gerente'], $gerentes);
            return array_merge([['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'licenciado']], $gerentes);
        }

        // gerente: unica opcao e ele mesmo
        return [['id' => $user['id'], 'name' => $user['name'], 'role_slug' => 'gerente']];
    }

    /** Gerente nao define comissao de ninguem -- so o licenciado controla o quanto repassa do proprio pool */
    private function canSetCommission(array $user): bool
    {
        return $user['role_slug'] !== 'gerente';
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

        if ($user['role_slug'] === 'gerente') {
            return (int) $user['id'];
        }

        // licenciado: so aceita a si mesmo ou um dos proprios gerentes
        $allowed = array_map(fn ($m) => (int) $m['id'], $this->managerOptions($user, null));
        $chosen = !empty($input['manager_id']) ? (int) $input['manager_id'] : (int) $user['id'];
        return in_array($chosen, $allowed, true) ? $chosen : (int) $user['id'];
    }

    /** Bloqueia edicao/leitura de gente fora da propria regiao (licenciado/gerente) */
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
}
