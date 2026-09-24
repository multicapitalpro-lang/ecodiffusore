<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\FileUpload;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\Client;
use App\Models\Commission;
use App\Models\CommissionAttachment;
use App\Models\FinancialAccount;
use App\Models\FinancialAttachment;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\User;
use App\Models\UserCommissionTier;

class FinanceController
{
    /** Licenciado e Gestor veem so a propria rede (downline); Admin ve tudo. Antes Gestor caia no
     *  fallback "sem filtro" igual o Admin -- vazamento real (Gestor e' subordinado de UM
     *  licenciado especifico, nao deveria ver o financeiro de outras redes). */
    private function scopeFilters(array $user): array
    {
        if (in_array($user['role_slug'], [Roles::REGIONAL_OWNER, 'gestor'], true)) {
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

        // Fase 118: card local (currentBalance, calculado por financial_transactions) SEMPRE
        // aparece -- o card "ASAAS API" (saldo real, ao vivo) e' um card A MAIS, nunca substitui
        // o local, pra dar pra comparar os dois e pegar lancamento categorizado na conta errada.
        $accounts = FinancialAccount::all();
        foreach ($accounts as &$account) {
            $account['balance'] = FinancialAccount::currentBalance((int) $account['id']);
        }
        unset($account);
        $asaasApiBalance = FinancialAccount::asaasApiBalance();

        $filters = array_merge($this->requestFilters(), $this->scopeFilters($user));
        $transactions = FinancialTransaction::all($filters);

        View::render('painel/finance/accounts', [
            'user' => $user,
            'accounts' => $accounts,
            'asaasApiBalance' => $asaasApiBalance,
            'transactions' => $transactions,
            'attachmentsByTransaction' => FinancialAttachment::forTransactions(array_column($transactions, 'id')),
            'categoryGroups' => FinancialCategory::grouped(),
            'categoryParents' => FinancialCategory::parents(),
            'clients' => Client::all($this->scopeFilters(Auth::user())),
            'filters' => $filters,
            'errors' => [],
            'values' => [],
            'openSubscriptionModal' => SubscriptionGate::shouldAutoOpenModal($user),
        ]);
    }

    /** Transferencia entre contas proprias (Caixa <-> Banco) -- nao e' receita nem despesa, so
     *  move dinheiro (ver FinancialTransaction::createTransfer, exclui dos relatorios de P&L). */
    public function storeTransfer(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');

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
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/caixas-bancos?erro=1');
        }

        FinancialAccount::setDefault((int) $id);

        Router::redirect('/painel/financeiro/caixas-bancos?sucesso=1');
    }

    public function storeAccount(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');

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
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');

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
        // Fase 100: Gerente ganha acesso (so' a esta tela, nao ao resto do Financeiro) pra ver o
        // custo de fabrica/imposto automatico de todo pedido pago -- pedido explicito do usuario.
        Auth::requireRole([...Roles::MANAGEMENT, 'gerente']);
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
        $today = date('Y-m-d');

        // Fase 114: 'atrasada' e' um status VIRTUAL (pendente + due_date < hoje), nao existe no
        // enum do banco -- passar direto pro model quebraria o match exato de status em
        // FinancialTransaction::all(). Busca como 'pendente' e refiltra por data aqui.
        $overdueOnly = ($filters['status'] ?? null) === 'atrasada';
        $queryFilters = $filters;
        if ($overdueOnly) {
            $queryFilters['status'] = 'pendente';
        }

        $transactions = FinancialTransaction::all($queryFilters);
        if ($overdueOnly) {
            $transactions = array_values(array_filter($transactions, fn ($t) => $t['due_date'] < $today));
        }

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
            'categoryParents' => FinancialCategory::parents(),
            'clients' => Client::all($this->scopeFilters(Auth::user())),
            'filters' => $filters,
            'errors' => $errors,
            'values' => $values,
            'openSubscriptionModal' => SubscriptionGate::shouldAutoOpenModal($user),
        ]);
    }

    public function storePayable(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');

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

    /** Fase 114: usuario pedia uma forma de incluir categoria propria na hora de lancar uma Conta
     *  a Pagar/Receber -- lista vinha fixa (so' as seedadas no schema_fase3), sem nenhuma tela pra
     *  cadastrar mais. Mesmo padrao do "+ Cadastrar cliente" (_client_quick_modal.php + redirect_to
     *  ?novo=1) -- toda categoria nova entra como filha de um grupo (pai) ja existente. */
    public function storeCategory(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');

        $redirectTo = $_GET['redirect_to'] ?? null;
        $backTo = ($redirectTo && str_starts_with($redirectTo, '/painel/')) ? $redirectTo : '/painel/financeiro/contas-a-pagar';

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect($backTo . '?erro=1');
        }

        $name = trim($_POST['name'] ?? '');
        $parentId = (int) ($_POST['parent_id'] ?? 0);

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Informe o nome da categoria.';
        }
        if (!$parentId || !FinancialCategory::find($parentId)) {
            $errors['parent_id'] = 'Selecione um grupo.';
        }

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            Router::redirect($backTo . '?erro=1');
        }

        $categoryId = FinancialCategory::create([
            'parent_id' => $parentId,
            'name' => $name,
            'type' => in_array($_POST['type'] ?? '', ['entrada', 'saida', 'ambos'], true) ? $_POST['type'] : 'ambos',
        ]);

        $sep = str_contains($backTo, '?') ? '&' : '?';
        $target = $backTo . $sep . 'novo=1&categoria_id=' . $categoryId;

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }
        Router::redirect($target);
    }

    public function markPaid(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect($_SERVER['HTTP_REFERER'] ?? '/painel/financeiro/contas-a-pagar');
        }

        $transaction = FinancialTransaction::find($id);
        if ($transaction) {
            FinancialTransaction::markPaid($id, date('Y-m-d'));
            // Fase 116: comprovante de pagamento opcional ao dar baixa -- reaproveita o mesmo
            // helper/model ja usados na criacao da conta (financial_transaction_id aqui e' o
            // proprio $id, sempre existe).
            $this->storeAttachments($id, $_FILES['attachments'] ?? null);
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
        $transaction = $this->authorizeTransaction((int) $id);
        $id = (int) $id;

        if (!empty($transaction['is_transfer'])) {
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
            'clients' => Client::all($this->scopeFilters(Auth::user())),
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function updateTransaction(string $id): void
    {
        $transaction = $this->authorizeTransaction((int) $id);
        $id = (int) $id;

        if (!empty($transaction['is_transfer'])) {
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
                'clients' => Client::all($this->scopeFilters(Auth::user())),
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
        $transaction = $this->authorizeTransaction((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect($_SERVER['HTTP_REFERER'] ?? '/painel/financeiro/caixas-bancos');
        }

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
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');
        $user = Auth::user();

        $attachment = FinancialAttachment::find((int) $id);
        if (!$attachment) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $transaction = FinancialTransaction::find((int) $attachment['transaction_id']);
        $scope = $this->scopeFilters($user);
        if ($transaction && !empty($scope['seller_ids']) && !$this->transactionInScope($transaction, $scope['seller_ids'])) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
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
     * nao gestores de pool). Licenciado/Gestor veem a propria rede (downline) -- mesma correcao de
     * scopeFilters() acima: Gestor tambem NAO deve ver comissao de outra rede. Admin sem filtro.
     */
    private function commissionFilters(array $user): array
    {
        if ($user['role_slug'] === Roles::SELLER) {
            return ['beneficiary_id' => $user['id']];
        }
        if (in_array($user['role_slug'], [Roles::REGIONAL_OWNER, 'gestor'], true)) {
            return ['beneficiary_ids' => User::downlineIds((int) $user['id'])];
        }
        // Gerente/Supervisor: a propria comissao nacional (como sempre) + a comissao de cada
        // Licenciado da rede que supervisionam -- precisam enxergar essas linhas pra poder dar
        // baixa nelas (ver commissionManageScope()), nao so na propria.
        if ($user['role_slug'] === 'gerente') {
            $ids = array_merge([(int) $user['id']], $this->licenciadoIdsWithin(User::nationalIds((int) $user['id'])));
            return ['beneficiary_ids' => array_values(array_unique($ids))];
        }
        if ($user['role_slug'] === 'supervisor') {
            $ids = array_merge([(int) $user['id']], $this->licenciadoIdsWithin(User::supervisedIds((int) $user['id'])));
            return ['beneficiary_ids' => array_values(array_unique($ids))];
        }

        return [];
    }

    /**
     * Quem cada papel pode de fato CONFIRMAR pagamento (dar baixa) -- diferente de
     * commissionFilters() (o que cada um ENXERGA na lista). Duas regras de negocio pedidas pelo
     * usuario: (1) Licenciado/Gestor pagam quem esta ABAIXO deles (Vendedor/Gestor), nunca a
     * propria comissao -- quem paga eles e' quem esta acima (a empresa, via Gerente/Admin), entao
     * autoconfirmar a propria comissao nao faz sentido; (2) Gerente/Supervisor (que antes eram
     * so visualizacao) ganham a funcao de confirmar que a empresa pagou a comissao de cada
     * Licenciado da rede que cuidam.
     * @return int[] ids de beneficiario que esse usuario pode marcar como pago
     */
    private function commissionManageScope(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            // Fase 114: Admin so' confirma pagamento do que a EMPRESA paga direto -- Licenciado
            // (comissao da faixa de preco) e Gerente/Supervisor (comissao nacional, ver
            // createCascadeForOrder()). Vendedor/Gestor sao pagos pelo proprio Licenciado da rede,
            // com o dinheiro que sai do pool dele -- ja cobertos pelo ramo de licenciado/gestor
            // logo abaixo. Pedido explicito do usuario: "eu, como admin, eu so dou baixa em
            // comissao para o licenciado" (vendedor/gestor nunca aparecia com "dar baixa" pro
            // admin antes desta fase por acaso -- era User::all() inteiro, incluindo vendedor).
            return array_map(
                fn ($u) => (int) $u['id'],
                array_filter(User::all(), fn ($u) => in_array($u['role_slug'], ['licenciado', 'gerente', 'supervisor'], true))
            );
        }
        if ($role === 'gerente') {
            return $this->licenciadoIdsWithin(User::nationalIds((int) $user['id']));
        }
        if ($role === 'supervisor') {
            return $this->licenciadoIdsWithin(User::supervisedIds((int) $user['id']));
        }
        if (in_array($role, [Roles::REGIONAL_OWNER, 'gestor'], true)) {
            return array_values(array_diff(User::downlineIds((int) $user['id']), [(int) $user['id']]));
        }

        return [];
    }

    /** @param int[] $ids @return int[] so os que sao Licenciado */
    private function licenciadoIdsWithin(array $ids): array
    {
        return array_values(array_filter($ids, fn ($id) => (User::find($id)['role_slug'] ?? null) === Roles::REGIONAL_OWNER));
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

        // Fase 120: Admin/Gerente/Supervisor veem varias redes de Licenciado misturadas na mesma
        // tabela -- filtro isola so' a rede (Licenciado + Gestor/Vendedor dele) de UM Licenciado
        // por vez. So aparece pra quem ve mais de 1 rede (ver licenciadoOptionsForCommissions()).
        $licenciadoOptions = $this->licenciadoOptionsForCommissions($user);
        $selectedLicenciadoId = (int) ($_GET['licenciado_id'] ?? 0);
        if ($selectedLicenciadoId && isset($licenciadoOptions[$selectedLicenciadoId])) {
            $filters['beneficiary_ids'] = User::downlineIds($selectedLicenciadoId);
            unset($filters['beneficiary_id']);
        }

        $commissions = Commission::all($filters);
        $summary = ['total' => 0.0, 'pago' => 0.0, 'pendente' => 0.0, 'count' => count($commissions)];
        $manageScope = $this->commissionManageScope($user);
        foreach ($commissions as &$c) {
            $summary['total'] += (float) $c['amount'];
            $summary[$c['status']] += (float) $c['amount'];
            $c['can_manage'] = in_array((int) $c['beneficiary_id'], $manageScope, true);
            // Fase 114: `percentage` guardado no Licenciado e' a % INTEGRAL do pool da faixa de
            // preco (ex: 20%), mas `amount` e' so' o que SOBROU depois de descontar Gestor/Vendedor
            // (ver Commission::createCascadeForOrder()) -- exibir o % bruto ao lado de um valor que
            // e' so' uma fracao dele confundia o usuario (2 linhas com % diferente e mesmo R$).
            // effective_percentage e' sempre amount/order_total, o que o valor exibido REALMENTE
            // representa do pedido -- usado so' na exibicao, nunca sobrescreve o percentage salvo.
            $c['effective_percentage'] = (float) $c['order_total'] > 0
                ? round((float) $c['amount'] / (float) $c['order_total'] * 100, 2)
                : (float) $c['percentage'];
        }
        unset($c);

        // Fase 31: mesma tabela de referencia de faixas de preco/comissao mostrada no Dashboard
        // (DashboardController::index()) -- repetida aqui porque o usuario pediu visibilidade nos
        // dois lugares. Licenciado/Gestor veem a % do Licenciado; Vendedor ve so a propria comissao
        // configurada (nunca a % do Licenciado).
        $pricingTiersRef = null;
        $vendorOwnTiers = null;
        if (in_array($user['role_slug'], ['licenciado', 'gestor'], true)) {
            $pricingTiersRef = PricingTier::visible();
        } elseif ($user['role_slug'] === Roles::SELLER) {
            $tierValues = UserCommissionTier::forUser((int) $user['id']);
            $vendorOwnTiers = array_map(fn ($t) => [
                'min_price' => $t['min_price'],
                'max_price' => $t['max_price'],
                'value' => $tierValues[$t['id']] ?? null,
            ], PricingTier::visible());
        }

        View::render('painel/finance/commissions', [
            'user' => $user,
            'commissions' => $commissions,
            'summary' => $summary,
            'bySeller' => Commission::byBeneficiary($filters),
            'byRole' => Commission::byRole($filters),
            'period' => $period,
            'canManageAny' => (bool) $manageScope,
            'pricingTiersRef' => $pricingTiersRef,
            'vendorOwnTiers' => $vendorOwnTiers,
            'vendorCommissionType' => $user['commission_type'] ?? null,
            'attachmentsByCommission' => CommissionAttachment::forCommissions(array_column($commissions, 'id')),
            'licenciadoOptions' => $licenciadoOptions,
            'selectedLicenciadoId' => $selectedLicenciadoId,
        ]);
    }

    /** @return array<int,string> id => nome, so' os Licenciados dentro do que esse usuario ja
     *  pode ver (mesmo escopo de commissionFilters()) -- vazio pra quem ja ve so' 1 rede
     *  (Licenciado/Gestor/Vendedor), o filtro nao faria diferenca pra eles. */
    private function licenciadoOptionsForCommissions(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            $licenciados = User::allByRole('licenciado');
        } elseif ($role === 'gerente') {
            $networkIds = User::nationalIds((int) $user['id']);
            $licenciados = array_filter(User::allByRole('licenciado'), fn ($l) => in_array((int) $l['id'], $networkIds, true));
        } elseif ($role === 'supervisor') {
            $networkIds = User::supervisedIds((int) $user['id']);
            $licenciados = array_filter(User::allByRole('licenciado'), fn ($l) => in_array((int) $l['id'], $networkIds, true));
        } else {
            return [];
        }

        $options = [];
        foreach ($licenciados as $l) {
            $options[(int) $l['id']] = $l['name'];
        }
        return $options;
    }

    public function exportCommissions(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = array_merge($this->commissionFilters($user), $this->periodFilters());

        $licenciadoOptions = $this->licenciadoOptionsForCommissions($user);
        $selectedLicenciadoId = (int) ($_GET['licenciado_id'] ?? 0);
        if ($selectedLicenciadoId && isset($licenciadoOptions[$selectedLicenciadoId])) {
            $filters['beneficiary_ids'] = User::downlineIds($selectedLicenciadoId);
            unset($filters['beneficiary_id']);
        }

        $roleLabels = ['licenciado' => 'Licenciado', 'gestor' => 'Gestor', 'vendedor' => 'Vendedor', 'gerente' => 'Gerente', 'supervisor' => 'Supervisor', 'influenciador' => 'Influenciador'];

        $rows = array_map(function ($c) use ($roleLabels) {
            // Fase 114: mesmo effective_percentage de commissions() -- amount/order_total, nao o
            // percentage bruto salvo (que pro Licenciado e' a % do pool inteiro, nao do valor
            // efetivamente mostrado). Ver comentario em commissions().
            $effectivePct = (float) $c['order_total'] > 0
                ? round((float) $c['amount'] / (float) $c['order_total'] * 100, 2)
                : (float) $c['percentage'];

            return [
                $c['order_id'],
                $c['beneficiary_name'],
                $roleLabels[$c['role_slug']] ?? $c['role_slug'],
                $c['client_name'],
                $c['order_date'],
                number_format($effectivePct, 2, ',', '.'),
                number_format((float) $c['amount'], 2, ',', '.'),
                $c['status'] === 'pago' ? 'Pago' : 'Pendente',
            ];
        }, Commission::all($filters));

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
        Auth::requireRole(array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT));
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/comissoes');
        }

        $commission = Commission::find($id);

        // commissionManageScope() (nao commissionFilters()) -- quem pode CONFIRMAR pagamento e'
        // mais restrito do que quem pode VER: exclui a propria comissao de Licenciado/Gestor
        // (quem paga eles e' quem esta acima), inclui Gerente/Supervisor confirmando a comissao
        // de Licenciado da rede deles (funcao nova que eles nao tinham antes).
        $manageScope = $this->commissionManageScope($user);
        if (!$commission || !in_array((int) $commission['beneficiary_id'], $manageScope, true)) {
            Router::redirect('/painel/financeiro/comissoes?erro=1');
        }

        if ($commission['status'] === 'pendente') {
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

        // Fase 116: comprovante opcional, fora do if(status==='pendente') acima de proposito --
        // se por algum motivo a comissao ja tiver sido dada baixa antes sem anexo, ainda da pra
        // anexar depois reabrindo o mesmo modal.
        $this->storeCommissionAttachments($id, $_FILES['attachments'] ?? null);

        Router::redirect('/painel/financeiro/comissoes?sucesso=1');
    }

    /** Comprovante de pagamento da comissao (Fase 116) -- tabela propria (commission_attachments),
     *  nao financial_attachments: uma comissao pode nao ter financial_transaction_id (accountId
     *  vazio acima), entao o comprovante nao pode depender de uma transacao ter sido criada. */
    private function storeCommissionAttachments(int $commissionId, ?array $filesInput): void
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
            $stored = FileUpload::storeCommissionProof($file);
            if ($stored) {
                CommissionAttachment::create($stored + ['commission_id' => $commissionId]);
            }
        }
    }

    /** Download autenticado do comprovante de comissao -- mesmo padrao de downloadAttachment()
     *  (nunca serve arquivo estatico direto), so' que o escopo de quem pode ver e'
     *  commissionManageScope() (quem podia dar baixa nessa comissao), nao scopeFilters(). */
    public function downloadCommissionAttachment(string $id): void
    {
        Auth::requireRole(array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT));
        $user = Auth::user();

        $attachment = CommissionAttachment::find((int) $id);
        if (!$attachment) {
            http_response_code(404);
            exit('Anexo não encontrado.');
        }

        $commission = Commission::find((int) $attachment['commission_id']);
        $manageScope = $this->commissionManageScope($user);
        if (!$commission || !in_array((int) $commission['beneficiary_id'], $manageScope, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $path = FileUpload::path('commission_proofs', $attachment['stored_name']);
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
            'asaasApiBalance' => FinancialAccount::asaasApiBalance(),
            'transactions' => $transactions,
            'attachmentsByTransaction' => FinancialAttachment::forTransactions(array_column($transactions, 'id')),
            'categoryGroups' => FinancialCategory::grouped(),
            'categoryParents' => FinancialCategory::parents(),
            'clients' => Client::all($this->scopeFilters(Auth::user())),
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    /** Busca uma transacao por id e bloqueia acesso se ela pertence, de forma identificavel
     *  (cliente ou pedido vinculado), a uma rede fora do escopo de quem esta agindo. */
    private function authorizeTransaction(int $id): array
    {
        Auth::requireRole(Roles::MANAGEMENT);
        SubscriptionGate::requireAccess(Auth::user(), 'financeiro');
        $user = Auth::user();

        $transaction = FinancialTransaction::find($id);
        if (!$transaction) {
            Router::redirect('/painel/financeiro/caixas-bancos');
        }

        $scope = $this->scopeFilters($user);
        if (!empty($scope['seller_ids']) && !$this->transactionInScope($transaction, $scope['seller_ids'])) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $transaction;
    }

    /** Um lancamento sem cliente/pedido vinculado (avulso, direto em Caixas e Bancos) nao tem
     *  "dono" identificavel no schema hoje -- fica visivel/editavel por qualquer MANAGEMENT, igual
     *  ja acontecia antes desta correcao (a conta em si e' compartilhada, ver scopeFilters()). So
     *  bloqueia quando da pra saber de qual rede o lancamento e' (via cliente ou pedido vinculado)
     *  e essa rede nao bate com quem esta tentando agir. */
    private function transactionInScope(array $transaction, array $sellerIds): bool
    {
        $hasIdentifiableOwner = false;

        if (!empty($transaction['client_id'])) {
            $client = Client::find((int) $transaction['client_id']);
            if ($client && $client['seller_id']) {
                $hasIdentifiableOwner = true;
                if (in_array((int) $client['seller_id'], $sellerIds, true)) {
                    return true;
                }
            }
        }

        if (!empty($transaction['order_id'])) {
            $order = Order::find((int) $transaction['order_id']);
            if ($order && $order['seller_id']) {
                $hasIdentifiableOwner = true;
                if (in_array((int) $order['seller_id'], $sellerIds, true)) {
                    return true;
                }
            }
        }

        return !$hasIdentifiableOwner;
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
