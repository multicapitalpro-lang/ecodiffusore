<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;

class QuoteController
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();

        $filters = $user['role_slug'] === 'licenciado' ? ['seller_id' => $user['id']] : [];

        View::render('painel/quotes/index', [
            'user' => $user,
            'quotes' => Quote::all($filters),
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => User::allByRole('licenciado'),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        Router::redirect('/painel/orcamentos?novo=1');
    }

    public function store(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['client_id' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/orcamentos?erro=1');
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        $errors = $this->validate($_POST, $items);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            Router::redirect('/painel/orcamentos?erro=1');
        }

        $sellerId = $user['role_slug'] === 'licenciado' ? $user['id'] : ($_POST['seller_id'] ?: null);

        $quoteId = Quote::create([
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'quote_date' => $_POST['quote_date'],
            'valid_until' => $_POST['valid_until'] ?? null,
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        $target = "/painel/orcamentos/{$quoteId}?sucesso=1";

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(string $id): void
    {
        $quote = $this->authorize((int) $id);

        View::render('painel/quotes/show', [
            'user' => Auth::user(),
            'quote' => $quote,
            'items' => QuoteItem::forQuote((int) $id),
        ]);
    }

    public function edit(string $id): void
    {
        $quote = $this->authorize((int) $id);

        if ($quote['status'] !== 'aberto') {
            Router::redirect("/painel/orcamentos/{$id}?erro=2");
        }

        View::render('painel/quotes/form', [
            'user' => Auth::user(),
            'editing' => $quote,
            'items' => QuoteItem::forQuote((int) $id),
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => User::allByRole('licenciado'),
            'preselectClientId' => 0,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/orcamentos/{$id}/editar?erro=1");
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        $errors = $this->validate($_POST, $items);

        if ($errors) {
            View::render('painel/quotes/form', [
                'user' => $user,
                'editing' => array_merge(['id' => $id], $_POST),
                'items' => $items,
                'clients' => Client::all(),
                'products' => Product::all(true),
                'sellers' => User::allByRole('licenciado'),
                'preselectClientId' => 0,
                'errors' => $errors,
            ]);
            return;
        }

        $sellerId = $user['role_slug'] === 'licenciado' ? $quote['seller_id'] : ($_POST['seller_id'] ?: null);

        Quote::updateHeaderAndItems($id, [
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'quote_date' => $_POST['quote_date'],
            'valid_until' => $_POST['valid_until'] ?? null,
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        Router::redirect("/painel/orcamentos/{$id}?sucesso=1");
    }

    public function markStatus(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/orcamentos/{$id}");
        }

        $status = $_POST['status'] ?? '';
        if (in_array($status, ['aprovado', 'recusado'], true)) {
            Quote::updateStatus($id, $status);
        }

        Router::redirect("/painel/orcamentos/{$id}?sucesso=1");
    }

    public function convert(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || $quote['status'] === 'convertido') {
            Router::redirect("/painel/orcamentos/{$id}");
        }

        $orderId = Quote::convertToOrder($id);

        Router::redirect("/painel/pedidos/{$orderId}?sucesso=1");
    }

    private function authorize(int $id): array
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();

        $quote = Quote::find($id);
        if (!$quote) {
            Router::redirect('/painel/orcamentos');
        }

        if ($user['role_slug'] === 'licenciado' && (int) $quote['seller_id'] !== (int) $user['id']) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $quote;
    }

    private function parseItems(array $input): array
    {
        $items = [];
        $productIds = $input['product_id'] ?? [];
        $quantities = $input['quantity'] ?? [];
        $unitPrices = $input['unit_price'] ?? [];

        foreach ($productIds as $index => $productId) {
            if ($productId === '' || $productId === null) {
                continue;
            }
            $items[] = [
                'product_id' => (int) $productId,
                'quantity' => max(1, (int) ($quantities[$index] ?? 1)),
                'unit_price' => (float) ($unitPrices[$index] ?? 0),
            ];
        }

        return $items;
    }

    private function validate(array $input, array $items): array
    {
        $errors = [];

        if (empty($input['client_id'])) {
            $errors['client_id'] = 'Selecione um cliente.';
        }
        if (empty($input['quote_date'])) {
            $errors['quote_date'] = 'Informe a data do orçamento.';
        }
        if (!$items) {
            $errors['items'] = 'Adicione pelo menos um produto.';
        }

        return $errors;
    }
}
