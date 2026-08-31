<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Commission;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

class OrderController
{
    private const MANAGER_ROLES = ['admin', 'gerente', 'supervisor'];

    public function index(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();

        $filters = [
            'status' => $_GET['status'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
        ];

        if ($user['role_slug'] === 'licenciado') {
            $filters['seller_id'] = $user['id'];
        }

        View::render('painel/orders/index', [
            'user' => $user,
            'orders' => Order::all($filters),
            'filters' => $filters,
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => User::allByRole('licenciado'),
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $qs = isset($_GET['cliente_id']) ? '&cliente_id=' . (int) $_GET['cliente_id'] : '';
        Router::redirect('/painel/pedidos?novo=1' . $qs);
    }

    public function store(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['client_id' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/pedidos?erro=1');
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        $errors = $this->validate($_POST, $items);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/orders/index', [
                'user' => $user,
                'orders' => Order::all($user['role_slug'] === 'licenciado' ? ['seller_id' => $user['id']] : []),
                'filters' => [],
                'clients' => Client::all(),
                'products' => Product::all(true),
                'sellers' => User::allByRole('licenciado'),
                'errors' => $errors,
                'values' => $_POST,
                'items' => $items,
            ]);
            return;
        }

        $sellerId = $user['role_slug'] === 'licenciado' ? $user['id'] : ($_POST['seller_id'] ?: null);

        $orderId = Order::create([
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'order_date' => $_POST['order_date'],
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        $target = "/painel/pedidos/{$orderId}?sucesso=1";

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);

        View::render('painel/orders/show', [
            'user' => Auth::user(),
            'order' => $order,
            'items' => OrderItem::forOrder((int) $id),
        ]);
    }

    public function edit(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);

        if ($order['status'] !== 'em_andamento') {
            Router::redirect("/painel/pedidos/{$id}?erro=2");
        }

        View::render('painel/orders/form', [
            'user' => Auth::user(),
            'editing' => $order,
            'items' => OrderItem::forOrder((int) $id),
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => User::allByRole('licenciado'),
            'preselectClientId' => 0,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}/editar?erro=1");
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        $errors = $this->validate($_POST, $items);

        if ($errors) {
            View::render('painel/orders/form', [
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

        $sellerId = $user['role_slug'] === 'licenciado' ? $order['seller_id'] : ($_POST['seller_id'] ?: null);

        Order::updateHeaderAndItems($id, [
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'order_date' => $_POST['order_date'],
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    public function markStatus(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['atendido', 'verificado', 'cancelado'], true)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        Order::updateStatus($id, $status);

        if ($status === 'verificado' && $order['seller_id']) {
            Commission::createCascadeForOrder($id, (int) $order['seller_id'], (float) $order['total_value']);

            $accountId = FinancialAccount::defaultAccountId();
            if ($accountId) {
                FinancialTransaction::createForOrderReceivable(
                    $id,
                    $accountId,
                    (float) $order['total_value'],
                    date('Y-m-d')
                );
            }
        }

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    private function authorizeOrder(int $id): array
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();

        $order = Order::find($id);
        if (!$order) {
            Router::redirect('/painel/pedidos');
        }

        if ($user['role_slug'] === 'licenciado' && (int) $order['seller_id'] !== (int) $user['id']) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $order;
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
        if (empty($input['order_date'])) {
            $errors['order_date'] = 'Informe a data do pedido.';
        }
        if (!$items) {
            $errors['items'] = 'Adicione pelo menos um produto ao pedido.';
        }

        return $errors;
    }
}
