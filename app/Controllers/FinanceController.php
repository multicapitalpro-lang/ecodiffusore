<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\FileUpload;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Commission;
use App\Models\FinancialAccount;
use App\Models\FinancialAttachment;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\User;

class FinanceController
{
    /** Licenciado ve so a propria regiao; Admin/Gestor mantem o comportamento que ja tinham */
    private function scopeFilters(array $user): array
    {
        if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
            return ['seller_ids' => User::downlineIds((int) $user['id'])];
        }

        return [];
    }

    public function accounts(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $user = Auth::user();

        $accounts = FinancialAccount::all();
        foreach ($accounts as &$account) {
            $account['balance'] = FinancialAccount::currentBalance((int) $account['id']);
        }
        unset($account);

        $transactions = FinancialTransaction::all($this->scopeFilters($user));

        View::render('painel/finance/accounts', [
            'user' => $user,
            'accounts' => $accounts,
            'transactions' => $transactions,
            'attachmentsByTransaction' => FinancialAttachment::forTransactions(array_column($transactions, 'id')),
            'categoryGroups' => FinancialCategory::grouped(),
            'clients' => Client::all(),
            'errors' => [],
            'values' => [],
        ]);
    }

    public function storeAccount(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

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
        Auth::requireRole(Roles::MANAGEMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['description' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        $errors = $this->validateTransaction($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            $this->renderAccountsWithErrors($errors, $_POST);
            return;
        }

        $type = $_POST['type'] ?? 'saida';
        $dueDate = $_POST['due_date'];

        $transactionId = FinancialTransaction::create([
            'account_id' => (int) $_POST['account_id'],
            'client_id' => $_POST['client_id'] ?: null,
            'category_id' => $_POST['category_id'] ?: null,
            'type' => $type,
            'description' => trim($_POST['description'] ?? ''),
            'amount' => (float) $_POST['amount'],
            'due_date' => $dueDate,
            'competencia' => $_POST['competencia'] ?: null,
            'paid_date' => $dueDate,
            'status' => 'pago',
        ]);

        try {
            $this->storeAttachments($transactionId, $_FILES['attachments'] ?? null);
        } catch (\RuntimeException $e) {
            // Lancamento ja foi salvo; so avisamos sobre o anexo.
            if (Response::isAjax()) {
                Response::json(['ok' => true, 'redirect' => '/painel/financeiro/caixas-bancos?sucesso=1&aviso=' . urlencode($e->getMessage())]);
            }
        }

        $target = '/painel/financeiro/caixas-bancos?sucesso=1';
        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }
        Router::redirect($target);
    }

    public function payable(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $this->renderLedger('saida', 'Contas a Pagar');
    }

    public function receivable(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $this->renderLedger('entrada', 'Contas a Receber');
    }

    private function renderLedger(string $type, string $title, array $errors = [], array $values = []): void
    {
        $user = Auth::user();
        $transactions = FinancialTransaction::all(array_merge(['type' => $type], $this->scopeFilters($user)));
        $today = date('Y-m-d');

        $summary = ['open_count' => 0, 'open_total' => 0.0, 'paid_total' => 0.0, 'overdue_count' => 0, 'overdue_total' => 0.0];
        foreach ($transactions as $t) {
            $total = FinancialTransaction::totalValue($t);
            if ($t['status'] === 'pendente') {
                $summary['open_count']++;
                $summary['open_total'] += $total;
                if ($t['due_date'] < $today) {
                    $summary['overdue_count']++;
                    $summary['overdue_total'] += $total;
                }
            } else {
                $summary['paid_total'] += $total;
            }
        }

        View::render('painel/finance/ledger', [
            'user' => $user,
            'title' => $title,
            'type' => $type,
            'transactions' => $transactions,
            'summary' => $summary,
            'attachmentsByTransaction' => FinancialAttachment::forTransactions(array_column($transactions, 'id')),
            'accounts' => FinancialAccount::all(),
            'categoryGroups' => FinancialCategory::grouped($type),
            'clients' => Client::all(),
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    public function storePayable(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        $type = ($_POST['type'] ?? 'saida') === 'entrada' ? 'entrada' : 'saida';
        $backTo = $type === 'entrada' ? '/painel/financeiro/contas-a-receber' : '/painel/financeiro/contas-a-pagar';

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['client_id' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect($backTo . '?erro=1');
        }

        $errors = $this->validatePayable($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            $this->renderLedger($type, $type === 'entrada' ? 'Contas a Receber' : 'Contas a Pagar', $errors, $_POST);
            return;
        }

        $dandoBaixa = !empty($_POST['salvar_e_dar_baixa']);

        $transactionId = FinancialTransaction::create([
            'account_id' => (int) $_POST['account_id'],
            'client_id' => (int) $_POST['client_id'],
            'category_id' => $_POST['category_id'] ?: null,
            'type' => $type,
            'description' => trim($_POST['description'] ?? ''),
            'amount' => (float) $_POST['amount'],
            'issue_date' => $_POST['issue_date'],
            'competencia' => $_POST['competencia'],
            'due_date' => $_POST['due_date'],
            'payment_method' => $_POST['payment_method'] ?: null,
            'document_number' => $_POST['document_number'] ?: null,
            'interest_pct' => (float) ($_POST['interest_pct'] ?: 0),
            'penalty_pct' => (float) ($_POST['penalty_pct'] ?: 0),
            'status' => $dandoBaixa ? 'pago' : 'pendente',
            'paid_date' => $dandoBaixa ? date('Y-m-d') : null,
        ]);

        try {
            $this->storeAttachments($transactionId, $_FILES['attachments'] ?? null);
        } catch (\RuntimeException $e) {
            // Segue o fluxo; o lancamento principal ja foi salvo.
        }

        $target = $backTo . '?sucesso=1';
        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }
        Router::redirect($target);
    }

    public function markPaid(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect($_SERVER['HTTP_REFERER'] ?? '/painel/financeiro/contas-a-pagar');
        }

        $transaction = FinancialTransaction::find($id);
        if ($transaction) {
            FinancialTransaction::markPaid($id, date('Y-m-d'));
        }

        Router::redirect($_SERVER['HTTP_REFERER'] ?? '/painel/financeiro/contas-a-pagar');
    }

    public function downloadAttachment(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        $attachment = FinancialAttachment::find((int) $id);
        if (!$attachment) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $path = FileUpload::path('financial', $attachment['stored_name']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode($attachment['original_name']) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * Vendedor/Gerente/Supervisor veem so as proprias comissoes (sao beneficiarios individuais,
     * nao gestores de pool). Licenciado ve a propria regiao. Admin/Gestor mantem o comportamento
     * que ja tinham (sem filtro) -- mesmo escopo combinado usado em scopeFilters().
     */
    private function commissionFilters(array $user): array
    {
        if (in_array($user['role_slug'], [Roles::SELLER, 'gerente', 'supervisor'], true)) {
            return ['beneficiary_id' => $user['id']];
        }
        if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
            return ['beneficiary_ids' => User::downlineIds((int) $user['id'])];
        }

        return [];
    }

    public function commissions(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = $this->commissionFilters($user);

        $commissions = Commission::all($filters);
        $summary = ['total' => 0.0, 'pago' => 0.0, 'pendente' => 0.0, 'count' => count($commissions)];
        foreach ($commissions as $c) {
            $summary['total'] += (float) $c['amount'];
            $summary[$c['status']] += (float) $c['amount'];
        }

        View::render('painel/finance/commissions', [
            'user' => $user,
            'commissions' => $commissions,
            'summary' => $summary,
            'bySeller' => Commission::byBeneficiary($filters),
            'canManage' => in_array($user['role_slug'], Roles::MANAGEMENT, true),
        ]);
    }

    public function exportCommissions(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = $this->commissionFilters($user);

        $roleLabels = ['licenciado' => 'Licenciado', 'gestor' => 'Gestor', 'vendedor' => 'Vendedor', 'gerente' => 'Gerente', 'supervisor' => 'Supervisor'];

        $rows = array_map(fn ($c) => [
            $c['order_id'],
            $c['beneficiary_name'],
            $roleLabels[$c['role_slug']] ?? $c['role_slug'],
            $c['client_name'],
            $c['order_date'],
            number_format((float) $c['percentage'], 2, ',', '.'),
            number_format((float) $c['amount'], 2, ',', '.'),
            $c['status'] === 'pago' ? 'Pago' : 'Pendente',
        ], Commission::all($filters));

        Csv::download('comissoes.csv', ['Pedido', 'Beneficiário', 'Papel', 'Cliente', 'Data', '%', 'Valor', 'Situação'], $rows);
    }

    /**
     * "Dar baixa" numa comissao precisa deixar rastro de verdade no financeiro -- antes so
     * mudava o status na tabela commissions, e o dinheiro que saiu do caixa pra pagar
     * vendedor/gestor/licenciado/supervisor/gerente nunca aparecia em Caixas e Bancos nem no
     * DRE (categoria "Comissões" ja existia, mas nada gerava lancamento nela automaticamente).
     */
    public function markCommissionPaid(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/comissoes');
        }

        $commission = Commission::find($id);
        if ($commission && $commission['status'] === 'pendente') {
            $transactionId = null;
            $accountId = FinancialAccount::defaultAccountId();
            if ($accountId) {
                $transactionId = FinancialTransaction::create([
                    'account_id' => $accountId,
                    'order_id' => $commission['order_id'],
                    'category_id' => FinancialCategory::commissionCategoryId(),
                    'type' => 'saida',
                    'description' => 'Comissão · Pedido #' . $commission['order_id'] . ' · ' . $commission['beneficiary_name'],
                    'amount' => (float) $commission['amount'],
                    'due_date' => date('Y-m-d'),
                    'paid_date' => date('Y-m-d'),
                    'status' => 'pago',
                ]);
            }
            Commission::markPaid($id, $transactionId);
        }

        Router::redirect('/painel/financeiro/comissoes?sucesso=1');
    }

    private function storeAttachments(int $transactionId, ?array $filesInput): void
    {
        if (!$filesInput) {
            return;
        }

        $count = count($filesInput['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name' => $filesInput['name'][$i],
                'type' => $filesInput['type'][$i],
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error' => $filesInput['error'][$i],
                'size' => $filesInput['size'][$i],
            ];
            $stored = FileUpload::storeFinancialAttachment($file);
            if ($stored) {
                FinancialAttachment::create($stored + ['transaction_id' => $transactionId]);
            }
        }
    }

    private function renderAccountsWithErrors(array $errors, array $values): void
    {
        $user = Auth::user();
        $accounts = FinancialAccount::all();
        foreach ($accounts as &$account) {
            $account['balance'] = FinancialAccount::currentBalance((int) $account['id']);
        }
        unset($account);

        $transactions = FinancialTransaction::all($this->scopeFilters($user));

        View::render('painel/finance/accounts', [
            'user' => $user,
            'accounts' => $accounts,
            'transactions' => $transactions,
            'attachmentsByTransaction' => FinancialAttachment::forTransactions(array_column($transactions, 'id')),
            'categoryGroups' => FinancialCategory::grouped(),
            'clients' => Client::all(),
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    private function validateTransaction(array $input): array
    {
        $errors = [];

        if (empty($input['account_id'])) {
            $errors['account_id'] = 'Selecione a conta financeira.';
        }
        if (!is_numeric($input['amount'] ?? null) || (float) $input['amount'] <= 0) {
            $errors['amount'] = 'Informe um valor válido.';
        }
        if (trim($input['description'] ?? '') === '') {
            $errors['description'] = 'Informe o histórico do lançamento.';
        }

        return $errors;
    }

    private function validatePayable(array $input): array
    {
        $errors = [];

        if (empty($input['client_id'])) {
            $errors['client_id'] = 'Selecione um cliente/fornecedor.';
        }
        if (empty($input['account_id'])) {
            $errors['account_id'] = 'Selecione a conta financeira.';
        }
        if (!is_numeric($input['amount'] ?? null) || (float) $input['amount'] <= 0) {
            $errors['amount'] = 'Informe um valor válido.';
        }
        if (empty($input['due_date'])) {
            $errors['due_date'] = 'Informe o vencimento.';
        }

        return $errors;
    }
}
