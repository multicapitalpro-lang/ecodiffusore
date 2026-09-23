<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\GoalAchievementCheck;
use App\Core\GoalPaceAlert;
use App\Core\Roles;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\Goal;
use App\Models\User;

class GoalController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        // Fase 94: sem cron nesse plano Hostinger -- roda o alerta de ritmo aqui, na tela mais
        // visitada por quem tem meta (mesmo padrao ja usado no Calendario/Dashboard).
        GoalPaceAlert::processDue();
        GoalAchievementCheck::processDue();

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
        $metricType = ($_POST['metric_type'] ?? '') === 'quantidade' ? 'quantidade' : 'valor';

        // "Toda a equipe" cria UMA meta (mesmo nome/valor/premio) pra CADA Vendedor entre os
        // destinatarios permitidos -- nao um alvo agregado dividido entre eles, cada um persegue
        // o mesmo numero de forma independente. So Vendedor (nao Gestor/sub-Licenciado) entram
        // aqui, mesmo que assignableTargets() devolva o downline inteiro.
        $applyToTeam = ($_POST['seller_id'] ?? '') === 'team';
        $allTargets = $this->assignableTargets($user);
        $allowedIds = array_map(fn ($t) => (int) $t['id'], $allTargets);

        $targetIds = [];
        if ($applyToTeam) {
            $vendedores = array_values(array_filter($allTargets, fn ($t) => $t['role_slug'] === Roles::SELLER));
            if (!$vendedores) {
                $errors['seller_id'] = 'Você não tem nenhum Vendedor na equipe pra aplicar essa meta.';
            }
            $targetIds = array_map(fn ($t) => (int) $t['id'], $vendedores);
        } else {
            $targetId = (int) ($_POST['seller_id'] ?? 0);
            if (!$targetId || !in_array($targetId, $allowedIds, true)) {
                $errors['seller_id'] = 'Selecione um destinatário válido pra essa meta.';
            } else {
                $targetIds = [$targetId];
            }
        }

        if ($errors) {
            Router::redirect('/painel/metas?erro=1');
        }

        foreach ($targetIds as $targetId) {
            Goal::create([
                'name' => trim($_POST['name']),
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'target_value' => $_POST['target_value'],
                'metric_type' => $metricType,
                'reward_description' => trim($_POST['reward_description'] ?? ''),
                'reward_amount' => trim($_POST['reward_amount'] ?? ''),
                'seller_id' => $targetId,
                'created_by' => $user['id'],
            ]);
        }

        Router::redirect('/painel/metas?sucesso=1');
    }

    /** Fase 94: detalhe do ritmo de uma meta -- quantos dias faltam, se esta adiantado/no
     *  ritmo/atrasado, quanto precisa vender por dia dai pra frente. Ferramenta premium (atras do
     *  paywall da assinatura), mesmo tratamento do Simulador de Comissao. */
    public function pace(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'alerta_meta');

        $goal = Goal::find((int) $id);
        $canView = $goal && (
            (int) ($goal['seller_id'] ?? 0) === (int) $user['id']
            || (int) ($goal['created_by'] ?? 0) === (int) $user['id']
            || $user['role_slug'] === 'admin'
        );
        if (!$canView) {
            Router::redirect('/painel/metas');
        }

        View::render('painel/goals/pace', [
            'user' => $user,
            'goal' => $goal,
            'pace' => Goal::pace($goal),
            'isModal' => isset($_GET['fragment']),
        ], isset($_GET['fragment']) ? null : 'painel');
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
