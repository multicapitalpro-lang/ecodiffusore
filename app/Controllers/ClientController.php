<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;

class ClientController
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        View::render('painel/clients/index', [
            'user' => Auth::user(),
            'clients' => Client::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        View::render('painel/clients/form', [
            'user' => Auth::user(),
            'editing' => null,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/clientes/novo?erro=1');
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            View::render('painel/clients/form', [
                'user' => Auth::user(),
                'editing' => null,
                'errors' => $errors,
                'old' => $_POST,
            ]);
            return;
        }

        $clientId = Client::create($_POST);

        $redirectTo = $_GET['redirect_to'] ?? null;
        if ($redirectTo === 'pedido-novo') {
            Router::redirect('/painel/pedidos/novo?cliente_id=' . $clientId);
        }

        Router::redirect('/painel/clientes?sucesso=1');
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
                'errors' => $errors,
            ]);
            return;
        }

        Client::update($id, $_POST);

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
