<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\LicenciadoExpense;

/** Controle financeiro completo do Licenciado (Fase 32) -- custos da propria operacao, sempre
 *  liberado (nao faz parte do paywall de Relatorios/Financeiro). */
class LicenciadoExpenseController
{
    public function index(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        $filters = [];
        if (!empty($_GET['from'])) {
            $filters['from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $filters['to'] = $_GET['to'];
        }

        View::render('painel/expenses/index', [
            'user' => $user,
            'expenses' => LicenciadoExpense::forLicenciado((int) $user['id'], $filters),
            'totals' => LicenciadoExpense::totals((int) $user['id'], $filters),
            'categories' => LicenciadoExpense::categoriesFor((int) $user['id']),
            'filters' => $filters,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/meus-custos?erro=1');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            View::render('painel/expenses/index', [
                'user' => $user,
                'expenses' => LicenciadoExpense::forLicenciado((int) $user['id']),
                'totals' => LicenciadoExpense::totals((int) $user['id']),
                'categories' => LicenciadoExpense::categoriesFor((int) $user['id']),
                'filters' => [],
                'errors' => $errors,
                'values' => $_POST,
            ]);
            return;
        }

        LicenciadoExpense::create((int) $user['id'], $_POST);
        Router::redirect('/painel/meus-custos?sucesso=1');
    }

    public function update(string $id): void
    {
        $expense = $this->authorize((int) $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/meus-custos?erro=1');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            Router::redirect('/painel/meus-custos?erro=1');
        }

        LicenciadoExpense::update((int) $expense['id'], $_POST);
        Router::redirect('/painel/meus-custos?sucesso=1');
    }

    public function destroy(string $id): void
    {
        $expense = $this->authorize((int) $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/meus-custos?erro=1');
        }

        LicenciadoExpense::delete((int) $expense['id']);
        Router::redirect('/painel/meus-custos?sucesso=1');
    }

    private function authorize(int $id): array
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        $expense = LicenciadoExpense::find($id);
        if (!$expense || (int) $expense['licenciado_id'] !== (int) $user['id']) {
            Router::redirect('/painel/meus-custos');
        }

        return $expense;
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (trim($input['category'] ?? '') === '') {
            $errors['category'] = 'Informe a categoria.';
        }
        if (!is_numeric($input['amount'] ?? null) || (float) $input['amount'] <= 0) {
            $errors['amount'] = 'Informe um valor válido.';
        }
        if (empty($input['expense_date'])) {
            $errors['expense_date'] = 'Informe a data.';
        }

        return $errors;
    }
}
