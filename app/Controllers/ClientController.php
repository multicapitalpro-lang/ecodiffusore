<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;

class ClientController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $clients = Client::all();

        $filters = [
            'q' => trim($_GET['q'] ?? ''),
            'seller_id' => $_GET['seller_id'] ?? '',
            'status' => $_GET['status'] ?? '',
            'city' => trim($_GET['city'] ?? ''),
        ];

        [$clients, $stats] = $this->attachPurchaseStatus($clients);

        $filtered = array_values(array_filter($clients, function ($c) use ($filters) {
            if ($filters['q'] !== '' && stripos($c['name'] . ' ' . $c['email'] . ' ' . $c['document'], $filters['q']) === false) {
                return false;
            }
            if ($filters['seller_id'] !== '' && (string) ($c['seller_id'] ?? '') !== (string) $filters['seller_id']) {
                return false;
            }
            if ($filters['status'] !== '' && $c['status'] !== $filters['status']) {
                return false;
            }
            if ($filters['city'] !== '' && stripos((string) $c['city'], $filters['city']) === false) {
                return false;
            }
            return true;
        }));

        View::render('painel/clients/index', [
            'user' => $user,
            'clients' => $filtered,
            'stats' => $stats,
            'filters' => $filters,
            'sellers' => User::allByRole('vendedor'),
        ]);
    }

    /** Anexa o resumo de compras (Order::purchaseSummaryByClientIds) em cada cliente e ja soma
     * as contagens pros cards do topo -- "status relacionado a compra": nunca comprou, tem
     * pedido em aberto, ja pagou algum pedido. */
    private function attachPurchaseStatus(array $clients): array
    {
        $ids = array_map(fn ($c) => (int) $c['id'], $clients);
        $summaries = Order::purchaseSummaryByClientIds($ids);

        $stats = ['total' => count($clients), 'pagos' => 0, 'abertos' => 0, 'nunca_compraram' => 0];

        foreach ($clients as &$client) {
            $summary = $summaries[(int) $client['id']] ?? null;
            $orderCount = (int) ($summary['order_count'] ?? 0);
            $openCount = (int) ($summary['open_count'] ?? 0);
            $paidCount = (int) ($summary['paid_count'] ?? 0);

            if ($orderCount === 0) {
                $purchaseStatus = ['slug' => 'nunca_comprou', 'label' => 'Nunca comprou', 'badge' => 'inactive'];
                $stats['nunca_compraram']++;
            } elseif ($openCount > 0) {
                $purchaseStatus = ['slug' => 'em_aberto', 'label' => 'Pedido em aberto', 'badge' => 'novo'];
                $stats['abertos']++;
            } elseif ($paidCount > 0) {
                $purchaseStatus = ['slug' => 'pago', 'label' => 'Já pagou', 'badge' => 'active'];
                $stats['pagos']++;
            } else {
                $purchaseStatus = ['slug' => 'sem_pagamento', 'label' => 'Comprou, sem pagamento confirmado', 'badge' => 'novo'];
            }

            $client['purchase_status'] = $purchaseStatus;
            $client['order_count'] = $orderCount;
        }
        unset($client);

        return [$clients, $stats];
    }

    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        Router::redirect('/painel/clientes?novo=1');
    }

    public function export(): void
    {
        Auth::requireRole(Roles::STAFF);

        $rows = array_map(fn ($c) => [
            $c['id'],
            $c['name'],
            $c['document'] ?: '',
            $c['person_type'] === 'juridica' ? 'Jurídica' : 'Física',
            $c['email'] ?: '',
            $c['whatsapp'] ?: '',
            $c['city'] ?: '',
            $c['state'] ?: '',
            $c['seller_name'] ?: '',
            $c['status'] === 'ativo' ? 'Ativo' : 'Inativo',
        ], Client::all());

        Csv::download('clientes.csv', ['ID', 'Nome', 'Documento', 'Tipo', 'E-mail', 'WhatsApp', 'Cidade', 'UF', 'Vendedor', 'Status'], $rows);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/clientes?erro=1');
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/clients/index', [
                'user' => Auth::user(),
                'clients' => Client::all(),
                'sellers' => User::allByRole('vendedor'),
                'errors' => $errors,
                'values' => $_POST,
            ]);
            return;
        }

        $clientId = Client::create($_POST);
        $redirectTo = $_GET['redirect_to'] ?? null;

        if ($redirectTo && str_starts_with($redirectTo, '/painel/')) {
            $sep = str_contains($redirectTo, '?') ? '&' : '?';
            $target = $redirectTo . $sep . 'novo=1&cliente_id=' . $clientId;
        } else {
            $target = '/painel/clientes?sucesso=1';
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'id' => $clientId, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $id = (int) $id;

        $client = Client::find($id);
        if (!$client) {
            Router::redirect('/painel/clientes');
        }

        View::render('painel/clients/show', [
            'user' => Auth::user(),
            'client' => $client,
            'orders' => Order::all(['client_id' => $id]),
            'transactions' => FinancialTransaction::all(['client_id' => $id]),
            'notes' => ClientNote::forClient($id),
        ]);
    }

    public function createAccess(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $id = (int) $id;

        $client = Client::find($id);
        if (!$client) {
            Router::redirect('/painel/clientes');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/clientes/{$id}?erro=1");
        }

        if (!empty($client['user_id'])) {
            Router::redirect("/painel/clientes/{$id}");
        }

        if (empty($client['email']) || !filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
            Router::redirect("/painel/clientes/{$id}?erro_acesso=1");
        }

        if (User::emailExists($client['email'])) {
            Router::redirect("/painel/clientes/{$id}?erro_acesso=2");
        }

        $tempPassword = substr(bin2hex(random_bytes(6)), 0, 10);

        $userId = User::create([
            'role_id' => Role::idBySlug('cliente'),
            'name' => $client['name'],
            'email' => $client['email'],
            'whatsapp' => $client['whatsapp'] ?? '',
            'password' => $tempPassword,
            'status' => 'active',
            'must_change_password' => true,
            'email_verified' => true,
        ]);

        Client::linkUser($id, $userId);

        Router::redirect("/painel/clientes/{$id}?acesso_criado=1&temp=" . urlencode($tempPassword));
    }

    public function storeNote(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || trim($_POST['note'] ?? '') === '') {
            Router::redirect("/painel/clientes/{$id}");
        }

        ClientNote::create($id, (int) Auth::user()['id'], trim($_POST['note']));

        Router::redirect("/painel/clientes/{$id}#notas");
    }

    public function edit(string $id): void
    {
        Auth::requireRole(Roles::STAFF);

        $client = Client::find((int) $id);
        if (!$client) {
            Router::redirect('/painel/clientes');
        }

        $isFragment = isset($_GET['fragment']);

        View::render('painel/clients/form', [
            'user' => Auth::user(),
            'editing' => $client,
            'sellers' => User::allByRole('vendedor'),
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function update(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['name' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect("/painel/clientes/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/clients/form', [
                'user' => Auth::user(),
                'editing' => array_merge(['id' => $id], $_POST),
                'sellers' => User::allByRole('vendedor'),
                'errors' => $errors,
            ]);
            return;
        }

        Client::update($id, $_POST);

        $target = '/painel/clientes?sucesso=1';
        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function destroy(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes?erro=csrf');
        }

        try {
            Client::delete($id);
        } catch (\PDOException $e) {
            Router::redirect('/painel/clientes?erro=vinculo');
        }

        Router::redirect('/painel/clientes?sucesso=2');
    }

    public function destroyBulk(): void
    {
        Auth::requireRole(Roles::STAFF);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes?erro=csrf');
        }

        $ids = array_unique(array_map('intval', $_POST['ids'] ?? []));
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                Client::delete($id);
                $deleted++;
            } catch (\PDOException $e) {
                $failed++;
            }
        }

        Router::redirect("/painel/clientes?sucesso=3&deletados={$deleted}&falhas={$failed}");
    }

    public function bulkAssignSeller(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes?erro=1');
        }

        $clientIds = $_POST['client_ids'] ?? [];
        $sellerId = !empty($_POST['seller_id']) ? (int) $_POST['seller_id'] : null;

        if ($clientIds) {
            Client::bulkAssignSeller($clientIds, $sellerId);
        }

        Router::redirect('/painel/clientes?sucesso=1');
    }

    private function validate(array $input): array
    {
        $errors = [];

        if (trim($input['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome do cliente.';
        }

        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        return $errors;
    }
}
