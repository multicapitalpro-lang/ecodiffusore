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

    /** Filtros lidos da querystring, comuns a Caixas e Bancos / Contas a Pagar / Contas a Receber. */
    private function requestFilters(): array
    {
        $filters = [];
        foreach (['from', 'to', 'account_id', 'category_id', 'client_id', 'status'] as $key) {
            if (!empty($_GET[$key])) {
                $filters[$key] = $_GET[$key];
            }
        }
        return $filters;
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

        $filters = array_merge($this->requestFilters(), $this->scopeFilters($user));
        $transactions = FinancialTransaction::all($filters);

        View::render('painel/finance/accounts', [
            'user' => $user,
            'accounts' => $accounts,
            'transactions' => $transactions,
            'attachmentsByTransaction' => FinancialAttachment::forTransactions(array_column($transactions, 'id')),
            'categoryGroups' => FinancialCategory::grouped(),
            'clients' => Client::all(),
            'filters' => $filters,
            'errors' => [],
            'values' => [],
        ]);
    }

    /** Transferencia entre contas proprias (Caixa <-> Banco) -- nao e' receita nem despesa, so
     *  move dinheiro (ver FinancialTransaction::createTransfer, exclui dos relatorios de P&L). */
    public function storeTransfer(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        $fromId = (int) ($_POST['from_account_id'] ?? 0);
        $toId = (int) ($_POST['to_account_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);

        if (!$fromId || !$toId || $fromId === $toId || $amount <= 0) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        FinancialTransaction::createTransfer(
            $fromId,
            $toId,
            $amount,
            $_POST['date'] ?: date('Y-m-d'),
            trim($_POST['description'] ?? '')
        );

        Router::redirect('/painel/financeiro/caixas-bancos?sucesso=1');
    }

    /** "Tornar padrao": pra onde vai automaticamente o recebimento de pedido pago e a saida de
     *  comissao paga (antes era sempre a conta mais antiga, sem controle nenhum pro usuario). */
    public function setDefaultAccount(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        FinancialAccount::setDefault((int) $id);

        Router::redirect('/painel/financeiro/caixas-bancos?sucesso=1');
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
        $filters = array_merge(
            ['type' => $type, 'exclude_transfers' => true],
            $this->requestFilters(),
            $this->scopeFilters($user)
        );
        $transactions = FinancialTransaction::all($filters);
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
            'filters' => $filters,
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
        $recurrenceFrequency = in_array($_POST['recurrence_frequency'] ?? '', ['semanal', 'mensal', 'anual'], true)
            ? $_POST['recurrence_frequency']
            : null;
        $recurrenceCount = max(1, min(60, (int) ($_POST['recurrence_count'] ?? 1)));

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
            'recurrence_frequency' => $recurrenceFrequency,
        ]);

        try {
            $this->storeAttachments($transactionId, $_FILES['attachments'] ?? null);
        } catch (\RuntimeException $e) {
            // Segue o fluxo; o lancamento principal ja foi salvo.
        }

        if ($recurrenceFrequency && $recurrenceCount > 1) {
            $this->generateRecurrences($transactionId, $recurrenceFrequency, $recurrenceCount, $_POST, $type);
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

    /** Gera as ocorrencias seguintes de uma conta recorrente (aluguel mensal etc) de uma vez so
     *  -- sem cron nesse plano, e' mais simples e previsivel gerar tudo na criacao do que tentar
     *  criar sob demanda. A primeira ocorrencia (a que o usuario preencheu) ja foi criada antes
     *  de chamar isso; aqui so as N-1 seguintes, sempre 'pendente'. */
    private function generateRecurrences(int $parentId, string $frequency, int $count, array $input, string $type): void
    {
        $interval = match ($frequency) {
            'semanal' => '+1 week',
            'anual' => '+1 year',
            default => '+1 month',
        };

        $dueDate = strtotime($input['due_date']);
        $issueDate = !empty($input['issue_date']) ? strtotime($input['issue_date']) : null;
        $competencia = !empty($input['competencia']) ? strtotime($input['competencia']) : null;

        for ($i = 1; $i < $count; $i++) {
            $dueDate = strtotime($interval, $dueDate);
            if ($issueDate) {
                $issueDate = strtotime($interval, $issueDate);
            }
            if ($competencia) {
                $competencia = strtotime($interval, $competencia);
            }

            FinancialTransaction::create([
                'account_id' => (int) $input['account_id'],
                'client_id' => (int) $input['client_id'],
                'category_id' => $input['category_id'] ?: null,
                'type' => $type,
                'description' => trim($input['description'] ?? ''),
                'amount' => (float) $input['amount'],
                'issue_date' => $issueDate ? date('Y-m-d', $issueDate) : null,
                'competencia' => $competencia ? date('Y-m-d', $competencia) : null,
                'due_date' => date('Y-m-d', $dueDate),
                'payment_method' => $input['payment_method'] ?: null,
                'document_number' => $input['document_number'] ?: null,
                'interest_pct' => (float) ($input['interest_pct'] ?: 0),
                'penalty_pct' => (float) ($input['penalty_pct'] ?: 0),
                'status' => 'pendente',
                'recurrence_frequency' => $frequency,
                'recurrence_parent_id' => $parentId,
            ]);
        }
    }

    /** Form de edicao generico -- serve tanto pro lancamento simples de Caixas e Bancos quanto
     *  pra conta a pagar/receber, o mesmo _payable_fields.php reaproveitado com $isEdit=true
     *  (sem os campos de recorrencia, que so fazem sentido na criacao da serie). */
    public function editTransaction(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $id = (int) $id;

        $transaction = FinancialTransaction::find($id);
        if (!$transaction || !empty($transaction['is_transfer'])) {
            // Transferencia tem duas pernas que precisam ficar sempre em espelho -- editar so
            // uma desbalancearia a outra conta. Pra corrigir, exclui (as duas juntas) e refaz.
            Router::redirect('/painel/financeiro/caixas-bancos');
        }

        $isFragment = isset($_GET['fragment']);

        View::render('painel/finance/edit_transaction', [
            'user' => Auth::user(),
            'transaction' => $transaction,
            'accounts' => FinancialAccount::all(),
            'categoryGroups' => FinancialCategory::grouped($transaction['type']),
            'clients' => Client::all(),
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function updateTransaction(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $id = (int) $id;

        $transaction = FinancialTransaction::find($id);
        if (!$transaction || !empty($transaction['is_transfer'])) {
            Router::redirect('/painel/financeiro/caixas-bancos');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['amount' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        $errors = $this->validateTransactionUpdate($_POST);
        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/finance/edit_transaction', [
                'user' => Auth::user(),
                'transaction' => array_merge($transaction, $_POST),
                'accounts' => FinancialAccount::all(),
                'categoryGroups' => FinancialCategory::grouped($transaction['type']),
                'clients' => Client::all(),
                'errors' => $errors,
                'isModal' => false,
            ]);
            return;
        }

        FinancialTransaction::update($id, [
            'account_id' => (int) $_POST['account_id'],
            'client_id' => $_POST['client_id'] ?: null,
            'category_id' => $_POST['category_id'] ?: null,
            'description' => trim($_POST['description'] ?? ''),
            'amount' => (float) $_POST['amount'],
            'issue_date' => $_POST['issue_date'] ?: null,
            'competencia' => $_POST['competencia'] ?: null,
            'due_date' => $_POST['due_date'],
            'payment_method' => $_POST['payment_method'] ?: null,
            'document_number' => $_POST['document_number'] ?: null,
            'interest_pct' => (float) ($_POST['interest_pct'] ?: 0),
            'penalty_pct' => (float) ($_POST['penalty_pct'] ?: 0),
        ]);

        $target = ($_SERVER['HTTP_REFERER'] ?? null) ?: '/painel/financeiro/caixas-bancos';
        $target .= (str_contains($target, '?') ? '&' : '?') . 'sucesso=1';
        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }
        Router::redirect($target);
    }

    /** So deixa excluir lancamento solto -- linha gerada pelo sistema (recebimento de pedido,
     *  comissao paga) sempre tem order_id preenchido, e apagar ela quebraria a rastreabilidade
     *  sem desfazer o pedido/comissao de origem. */
    public function destroyTransaction(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect($_SERVER['HTTP_REFERER'] ?? '/painel/financeiro/caixas-bancos');
        }

        $transaction = FinancialTransaction::find($id);
        if ($transaction && empty($transaction['order_id'])) {
            // Transferencia tem duas pernas (uma por conta) -- excluir uma sem a outra deixaria
            // a movimentacao desbalanceada (dinheiro "aparecendo" ou "sumindo" de uma conta so).
            if (!empty($transaction['is_transfer']) && !empty($transaction['transfer_pair_id'])) {
                FinancialTransaction::delete((int) $transaction['transfer_pair_id']);
            }
            FinancialTransaction::delete($id);
        }

        Router::redirect($_SERVER['HTTP_REFERER'] ?? '/painel/financeiro/caixas-bancos');
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

    /** Filtro de periodo (Hoje/Semana/Mes/Ano/personalizado) pra Comissoes -- sem filtro nenhum
     *  na querystring, mantem o comportamento de sempre (historico completo, sem corte de data). */
    private function periodFilters(): array
    {
        $filters = [];
        if (!empty($_GET['from'])) {
            $filters['from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $filters['to'] = $_GET['to'];
        }
        return $filters;
    }

    public function commissions(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $period = $this->periodFilters();
        $filters = array_merge($this->commissionFilters($user), $period);

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
            'byRole' => Commission::byRole($filters),
            'period' => $period,
            'canManage' => in_array($user['role_slug'], Roles::MANAGEMENT, true),
        ]);
    }

    public function exportCommissions(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = array_merge($this->commissionFilters($user), $this->periodFilters());

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

    /** Cliente/fornecedor NAO e' obrigatorio aqui -- o mesmo form de edicao tambem atende o
     *  lancamento simples de Caixas e Bancos, que nunca teve esse campo preenchido. */
    private function validateTransactionUpdate(array $input): array
    {
        $errors = [];

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
