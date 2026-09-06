<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\FinancialAccount;
use App\Models\Remittance;
use App\Models\User;

class RemittanceController
{
    private const ALLOWED_ROLES = Roles::MANAGEMENT;

    public function index(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $user = Auth::user();

        View::render('painel/remittances/index', [
            'user' => $user,
            'remittances' => Remittance::all($user['role_slug'] === 'admin' ? null : (int) $user['id']),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $user = Auth::user();

        $type = ($_GET['type'] ?? 'pagar') === 'receber' ? 'receber' : 'pagar';
        $accountId = (int) ($_GET['account_id'] ?? 0);
        $accounts = FinancialAccount::all();

        if (!$accountId && $accounts) {
            $accountId = (int) $accounts[0]['id'];
        }

        View::render('painel/remittances/form', [
            'user' => $user,
            'accounts' => $accounts,
            'type' => $type,
            'accountId' => $accountId,
            'transactions' => $accountId
                ? Remittance::eligibleTransactions($type === 'receber' ? 'entrada' : 'saida', $accountId, $this->sellerScope($user))
                : [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/remessas');
        }

        $transactionIds = $_POST['transaction_ids'] ?? [];
        $type = ($_POST['type'] ?? 'pagar') === 'receber' ? 'receber' : 'pagar';
        $accountId = (int) ($_POST['account_id'] ?? 0);

        if (!$transactionIds || !$accountId) {
            Router::redirect('/painel/financeiro/remessas/nova?type=' . $type . '&account_id=' . $accountId . '&erro=1');
        }

        // So inclui na remessa os titulos que realmente estao no escopo de quem esta agindo --
        // evita incluir (via POST direto) um titulo de outra rede que nunca apareceu na tela.
        $eligible = Remittance::eligibleTransactions($type === 'receber' ? 'entrada' : 'saida', $accountId, $this->sellerScope($user));
        $eligibleIds = array_map(fn ($t) => (int) $t['id'], $eligible);
        $transactionIds = array_values(array_intersect(array_map('intval', $transactionIds), $eligibleIds));

        if (!$transactionIds) {
            Router::redirect('/painel/financeiro/remessas/nova?type=' . $type . '&account_id=' . $accountId . '&erro=1');
        }

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
        $remittance = $this->authorizeRemittance((int) $id);

        View::render('painel/remittances/show', [
            'user' => Auth::user(),
            'remittance' => $remittance,
            'items' => Remittance::items((int) $id),
        ]);
    }

    public function send(string $id): void
    {
        $this->authorizeRemittance((int) $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/financeiro/remessas/{$id}");
        }

        Remittance::markSent((int) $id);
        Router::redirect("/painel/financeiro/remessas/{$id}?sucesso=1");
    }

    public function returnBack(string $id): void
    {
        $this->authorizeRemittance((int) $id);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/financeiro/remessas/{$id}");
        }

        Remittance::markReturned((int) $id);
        Router::redirect("/painel/financeiro/remessas/{$id}?sucesso=1");
    }

    private function authorizeRemittance(int $id): array
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $user = Auth::user();

        $remittance = Remittance::find($id);
        if (!$remittance) {
            Router::redirect('/painel/financeiro/remessas');
        }

        if ($user['role_slug'] !== 'admin' && (int) $remittance['created_by'] !== (int) $user['id']) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $remittance;
    }

    /** null = sem escopo (Admin); Gestor/Licenciado veem so titulos da propria rede. */
    private function sellerScope(array $user): ?array
    {
        if (in_array($user['role_slug'], ['licenciado', 'gestor'], true)) {
            return User::downlineIds((int) $user['id']);
        }

        return null;
    }
}
