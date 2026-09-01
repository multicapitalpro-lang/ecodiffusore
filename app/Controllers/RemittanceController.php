<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\FinancialAccount;
use App\Models\Remittance;

class RemittanceController
{
    private const ALLOWED_ROLES = Roles::MANAGEMENT;

    public function index(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        View::render('painel/remittances/index', [
            'user' => Auth::user(),
            'remittances' => Remittance::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        $type = ($_GET['type'] ?? 'pagar') === 'receber' ? 'receber' : 'pagar';
        $accountId = (int) ($_GET['account_id'] ?? 0);
        $accounts = FinancialAccount::all();

        if (!$accountId && $accounts) {
            $accountId = (int) $accounts[0]['id'];
        }

        View::render('painel/remittances/form', [
            'user' => Auth::user(),
            'accounts' => $accounts,
            'type' => $type,
            'accountId' => $accountId,
            'transactions' => $accountId ? Remittance::eligibleTransactions($type === 'receber' ? 'entrada' : 'saida', $accountId) : [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/remessas');
        }

        $transactionIds = $_POST['transaction_ids'] ?? [];
        $type = ($_POST['type'] ?? 'pagar') === 'receber' ? 'receber' : 'pagar';
        $accountId = (int) ($_POST['account_id'] ?? 0);

        if (!$transactionIds || !$accountId) {
            Router::redirect('/painel/financeiro/remessas/nova?type=' . $type . '&account_id=' . $accountId . '&erro=1');
        }

        $user = Auth::user();
        $remittanceId = Remittance::create([
            'account_id' => $accountId,
            'type' => $type,
            'payment_method' => $_POST['payment_method'] ?? null,
            'created_by' => $user['id'],
        ], $transactionIds);

        Router::redirect("/painel/financeiro/remessas/{$remittanceId}?sucesso=1");
    }

    public function show(string $id): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        $remittance = Remittance::find((int) $id);
        if (!$remittance) {
            Router::redirect('/painel/financeiro/remessas');
        }

        View::render('painel/remittances/show', [
            'user' => Auth::user(),
            'remittance' => $remittance,
            'items' => Remittance::items((int) $id),
        ]);
    }

    public function send(string $id): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/financeiro/remessas/{$id}");
        }

        Remittance::markSent((int) $id);
        Router::redirect("/painel/financeiro/remessas/{$id}?sucesso=1");
    }

    public function returnBack(string $id): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/financeiro/remessas/{$id}");
        }

        Remittance::markReturned((int) $id);
        Router::redirect("/painel/financeiro/remessas/{$id}?sucesso=1");
    }
}
