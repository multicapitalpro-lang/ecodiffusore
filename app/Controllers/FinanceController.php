<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;

class FinanceController
{
    public function accounts(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        $accounts = FinancialAccount::all();
        foreach ($accounts as &$account) {
            $account['balance'] = FinancialAccount::currentBalance((int) $account['id']);
        }
        unset($account);

        View::render('painel/finance/accounts', [
            'user' => Auth::user(),
            'accounts' => $accounts,
            'transactions' => FinancialTransaction::all(),
        ]);
    }

    public function storeAccount(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        if (trim($_POST['name'] ?? '') === '') {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        FinancialAccount::create($_POST);

        Router::redirect('/painel/financeiro/caixas-bancos?sucesso=1');
    }

    public function storeTransaction(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        $errors = [];
        if (empty($_POST['account_id'])) {
            $errors[] = 'conta';
        }
        if (empty($_POST['category'])) {
            $errors[] = 'categoria';
        }
        if (empty($_POST['amount']) || !is_numeric($_POST['amount'])) {
            $errors[] = 'valor';
        }
        if (empty($_POST['due_date'])) {
            $errors[] = 'data';
        }

        if ($errors) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        FinancialTransaction::create($_POST);

        Router::redirect('/painel/financeiro/caixas-bancos?sucesso=1');
    }

    public function payable(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        View::render('painel/finance/ledger', [
            'user' => Auth::user(),
            'title' => 'Contas a Pagar',
            'type' => 'saida',
            'transactions' => FinancialTransaction::all(['type' => 'saida']),
        ]);
    }

    public function receivable(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        View::render('painel/finance/ledger', [
            'user' => Auth::user(),
            'title' => 'Contas a Receber',
            'type' => 'entrada',
            'transactions' => FinancialTransaction::all(['type' => 'entrada']),
        ]);
    }

    public function commissions(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();

        $filters = [];
        if ($user['role_slug'] === 'licenciado') {
            $filters['seller_id'] = $user['id'];
        }

        View::render('painel/finance/commissions', [
            'user' => $user,
            'commissions' => \App\Models\Commission::all($filters),
        ]);
    }
}
