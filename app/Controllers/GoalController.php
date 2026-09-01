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
        Auth::requireRole(Roles::MANAGEMENT);

        $goals = Goal::all();
        $today = date('Y-m-d');

        View::render('painel/goals/index', [
            'user' => Auth::user(),
            'active' => array_values(array_filter($goals, fn ($g) => $g['end_date'] >= $today)),
            'inactive' => array_values(array_filter($goals, fn ($g) => $g['end_date'] < $today)),
            'sellers' => User::allByRole('vendedor'),
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

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

        if ($errors) {
            Router::redirect('/painel/metas?erro=1');
        }

        Goal::create([
            'name' => trim($_POST['name']),
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'target_value' => $_POST['target_value'],
            'seller_id' => $_POST['seller_id'] ?? null,
            'created_by' => Auth::user()['id'],
        ]);

        Router::redirect('/painel/metas?sucesso=1');
    }

    public function delete(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/metas');
        }

        Goal::delete((int) $id);
        Router::redirect('/painel/metas?sucesso=1');
    }
}
