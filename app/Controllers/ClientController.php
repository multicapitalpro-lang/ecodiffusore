<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\Response;
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
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        View::render('painel/clients/index', [
            'user' => Auth::user(),
            'clients' => Client::all(),
            'sellers' => User::allByRole('licenciado'),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        Router::redirect('/painel/clientes?novo=1');
    }

    public function export(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

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
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

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
                'sellers' => User::allByRole('licenciado'),
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
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
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
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
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
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || trim($_POST['note'] ?? '') === '') {
            Router::redirect("/painel/clientes/{$id}");
        }

        ClientNote::create($id, (int) Auth::user()['id'], trim($_POST['note']));

        Router::redirect("/painel/clientes/{$id}#notas");
    }

    public function edit(string $id): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        $client = Client::find((int) $id);
        if (!$client) {
            Router::redirect('/painel/clientes');
        }

        View::render('painel/clients/form', [
            'user' => Auth::user(),
            'editing' => $client,
            'sellers' => User::allByRole('licenciado'),
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/clientes/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            View::render('painel/clients/form', [
                'user' => Auth::user(),
                'editing' => array_merge(['id' => $id], $_POST),
                'sellers' => User::allByRole('licenciado'),
                'errors' => $errors,
            ]);
            return;
        }

        Client::update($id, $_POST);

        Router::redirect('/painel/clientes?sucesso=1');
    }

    public function bulkAssignSeller(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

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
