<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Goal;
use App\Models\User;

class GoalController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $goals = Goal::all();
        $today = date('Y-m-d');

        // Cada um so ve as metas que sao PRA ele, as que ELE criou, ou (admin) tudo.
        if ($user['role_slug'] !== 'admin') {
            $goals = array_values(array_filter($goals, fn ($g) =>
                (int) ($g['seller_id'] ?? 0) === (int) $user['id']
                || (int) ($g['created_by'] ?? 0) === (int) $user['id']
            ));
        }

        View::render('painel/goals/index', [
            'user' => $user,
            'active' => array_values(array_filter($goals, fn ($g) => $g['end_date'] >= $today)),
            'inactive' => array_values(array_filter($goals, fn ($g) => $g['end_date'] < $today)),
            'targets' => $this->assignableTargets($user),
            'errors' => [],
        ]);
    }

    /** Quem cada papel pode definir uma meta pra -- reflete a hierarquia pedida: Licenciado cria
     * pro Gestor/Vendedor dele; Supervisor cria pros Licenciados que cuida; Gerente cria pros
     * Supervisores que cadastrou; Gestor cria pro Vendedor; Vendedor so cria meta pra si mesmo;
     * Admin pode escolher qualquer um. */
    private function assignableTargets(array $user): array
    {
        switch ($user['role_slug']) {
            case 'admin':
                return array_values(array_filter(User::all(), fn ($u) => !in_array($u['role_slug'], ['admin', 'cliente'], true)));
            case 'gerente':
                return array_values(array_filter(User::all(), fn ($u) => (int) ($u['manager_id'] ?? 0) === (int) $user['id'] && $u['role_slug'] === 'supervisor'));
            case 'supervisor':
                return array_values(array_filter(User::all(), fn ($u) => (int) ($u['supervisor_id'] ?? 0) === (int) $user['id'] && $u['role_slug'] === 'licenciado'));
            case 'licenciado':
                $downline = array_diff(User::downlineIds((int) $user['id']), [(int) $user['id']]);
                return array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
            case 'gestor':
                $downline = array_diff(User::downlineIds((int) $user['id']), [(int) $user['id']]);
                return array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
            case Roles::SELLER:
                return [$user];
            default:
                return [];
        }
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/metas?erro=1');
        }

        $errors = [];
        if (trim($_POST['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome da meta.';
        }
        if (empty($_POST['start_date']) || empty($_POST['end_date']) || $_POST['start_date'] > $_POST['end_date']) {
            $errors['end_date'] = 'Verifique as datas de início e fim.';
        }
        if (!is_numeric($_POST['target_value'] ?? null) || (float) $_POST['target_value'] <= 0) {
            $errors['target_value'] = 'Informe um valor de meta válido.';
        }

        $targetId = (int) ($_POST['seller_id'] ?? 0);
        $allowedIds = array_map(fn ($t) => (int) $t['id'], $this->assignableTargets($user));
        if (!$targetId || !in_array($targetId, $allowedIds, true)) {
            $errors['seller_id'] = 'Selecione um destinatário válido pra essa meta.';
        }

        if ($errors) {
            Router::redirect('/painel/metas?erro=1');
        }

        Goal::create([
            'name' => trim($_POST['name']),
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'target_value' => $_POST['target_value'],
            'reward_description' => trim($_POST['reward_description'] ?? ''),
            'reward_amount' => trim($_POST['reward_amount'] ?? ''),
            'seller_id' => $targetId,
            'created_by' => $user['id'],
        ]);

        Router::redirect('/painel/metas?sucesso=1');
    }

    public function delete(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/metas');
        }

        $goal = Goal::find((int) $id);
        if ($goal && ($user['role_slug'] === 'admin' || (int) $goal['created_by'] === (int) $user['id'])) {
            Goal::delete((int) $id);
        }

        Router::redirect('/painel/metas?sucesso=1');
    }

    public function markRewardPaid(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/metas');
        }

        $goal = Goal::find((int) $id);
        if ($goal && ($user['role_slug'] === 'admin' || (int) $goal['created_by'] === (int) $user['id'])) {
            Goal::markRewardPaid((int) $id);
        }

        Router::redirect('/painel/metas?sucesso=1');
    }
}
