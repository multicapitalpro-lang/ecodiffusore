<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

class OrderController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = array_merge([
            'status' => $_GET['status'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
        ], $this->scopeFilters($user));

        View::render('painel/orders/index', [
            'user' => $user,
            'orders' => Order::all($filters),
            'filters' => $filters,
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => $this->sellerOptions($user),
        ]);
    }

    public function export(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = array_merge([
            'status' => $_GET['status'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
        ], $this->scopeFilters($user));

        $statusLabels = ['em_andamento' => 'Em andamento', 'atendido' => 'Atendido', 'verificado' => 'Verificado', 'cancelado' => 'Cancelado'];

        $rows = array_map(fn ($o) => [
            $o['id'],
            $o['client_name'],
            $o['seller_name'] ?: '',
            $o['order_date'],
            number_format((float) $o['total_value'], 2, ',', '.'),
            $statusLabels[$o['status']] ?? $o['status'],
        ], Order::all($filters));

        Csv::download('pedidos.csv', ['ID', 'Cliente', 'Vendedor', 'Data', 'Total', 'Situação'], $rows);
    }

    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        $qs = isset($_GET['cliente_id']) ? '&cliente_id=' . (int) $_GET['cliente_id'] : '';
        Router::redirect('/painel/pedidos?novo=1' . $qs);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);

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
                'orders' => Order::all($this->scopeFilters($user)),
                'filters' => [],
                'clients' => Client::all(),
                'products' => Product::all(true),
                'sellers' => $this->sellerOptions($user),
                'errors' => $errors,
                'values' => $_POST,
                'items' => $items,
            ]);
            return;
        }

        $sellerId = $user['role_slug'] === Roles::SELLER ? $user['id'] : ($_POST['seller_id'] ?: null);

        $orderId = Order::create([
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'order_date' => $_POST['order_date'],
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        if ($sellerId) {
            Approval::checkAndRequest('order', $orderId, $items, (int) $sellerId, (int) $user['id']);
        }

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
            'payments' => Payment::forPayable('order', (int) $id),
            'approval' => Approval::pendingFor('order', (int) $id),
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
            'sellers' => $this->sellerOptions(Auth::user()),
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
                'sellers' => $this->sellerOptions($user),
                'preselectClientId' => 0,
                'errors' => $errors,
            ]);
            return;
        }

        $sellerId = $user['role_slug'] === Roles::SELLER ? $order['seller_id'] : ($_POST['seller_id'] ?: null);

        Order::updateHeaderAndItems($id, [
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'order_date' => $_POST['order_date'],
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        if ($sellerId) {
            Approval::checkAndRequest('order', $id, $items, (int) $sellerId, (int) $user['id']);
        }

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

        if ($status === 'verificado') {
            $ok = Order::markVerifiedWithCommission($id);
            if ($ok) {
                AuditLog::record((int) Auth::user()['id'], 'pedido_verificado', 'order', $id, ['status' => $order['status']], ['status' => 'verificado']);
            }
            Router::redirect("/painel/pedidos/{$id}?" . ($ok ? 'sucesso=1' : 'erro=3'));
            return;
        }

        Order::updateStatus($id, $status);
        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    private function authorizeOrder(int $id): array
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $order = Order::find($id);
        if (!$order) {
            Router::redirect('/painel/pedidos');
        }

        if (!$this->canAccessSeller($user, (int) ($order['seller_id'] ?? 0))) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        return $order;
    }

    /** Filtro de escopo pra listar pedidos: vendedor so os proprios, gerente/licenciado a regiao, admin tudo */
    private function scopeFilters(array $user): array
    {
        if ($user['role_slug'] === 'admin') {
            return [];
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return ['seller_id' => $user['id']];
        }

        return ['seller_ids' => User::downlineIds((int) $user['id'])];
    }

    /** Vendedores disponiveis pro dropdown de "vendedor" no form de pedido, escopado por regiao */
    private function sellerOptions(array $user): array
    {
        $sellers = User::allByRole(Roles::SELLER);
        if ($user['role_slug'] === 'admin') {
            return $sellers;
        }

        $downline = User::downlineIds((int) $user['id']);
        return array_values(array_filter($sellers, fn ($s) => in_array((int) $s['id'], $downline, true)));
    }

    private function canAccessSeller(array $user, int $sellerId): bool
    {
        if ($user['role_slug'] === 'admin') {
            return true;
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return $sellerId === (int) $user['id'];
        }

        return in_array($sellerId, User::downlineIds((int) $user['id']), true);
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
