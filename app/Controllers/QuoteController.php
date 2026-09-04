<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;

class QuoteController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $filters = array_merge([
            'status' => $_GET['status'] ?? null,
            'city' => $_GET['city'] ?? null,
        ], $this->scopeFilters($user));

        if (!empty($_GET['licenciado_id'])) {
            // Filtra pelos vendedores/gestores de baixo desse licenciado especifico -- reaproveita
            // a mesma cadeia de manager_id ja usada pro escopo normal do licenciado.
            $filters['seller_ids'] = User::downlineIds((int) $_GET['licenciado_id']);
            unset($filters['seller_id']);
        } elseif (!empty($_GET['seller_id'])) {
            $filters['seller_id'] = (int) $_GET['seller_id'];
            unset($filters['seller_ids']);
        }

        $quotes = Quote::all($filters);
        [$quotes, $stats] = $this->attachPaymentSituation($quotes);

        View::render('painel/quotes/index', [
            'user' => $user,
            'quotes' => $quotes,
            'stats' => $stats,
            'filters' => array_merge($filters, [
                'licenciado_id' => $_GET['licenciado_id'] ?? '',
                'seller_id' => $_GET['seller_id'] ?? '',
            ]),
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => $this->sellerOptions($user),
            'licenciados' => $this->licenciadoOptions($user),
        ]);
    }

    /** Anexa a situacao de pagamento (Payment::situationFor) em cada orcamento, sem N+1, e ja
     * soma as contagens pros cards do topo. "recusado" faz o papel de "cancelado" aqui. */
    private function attachPaymentSituation(array $quotes): array
    {
        $ids = array_map(fn ($q) => (int) $q['id'], $quotes);
        $latestPayments = Payment::latestByPayableIds('quote', $ids);

        $stats = ['total' => count($quotes), 'pendentes' => 0, 'pagos' => 0, 'cancelados' => 0];

        foreach ($quotes as &$quote) {
            $payment = $latestPayments[(int) $quote['id']] ?? null;
            $situation = Payment::situationFor($quote, $payment, 'recusado');
            $quote['payment_situation'] = $situation;
            $quote['payment_method'] = $payment['method'] ?? null;

            if ($situation['slug'] === 'pendente' || $situation['slug'] === 'expirado') {
                $stats['pendentes']++;
            }
            if ($situation['slug'] === 'pago') {
                $stats['pagos']++;
            }
            if ($situation['slug'] === 'cancelado') {
                $stats['cancelados']++;
            }
        }
        unset($quote);

        return [$quotes, $stats];
    }

    /** Licenciados disponiveis pro filtro -- so faz sentido pra quem enxerga mais de uma regiao
     * (admin/gerente/supervisor); pra um Licenciado ou Gestor olhando so a propria rede, o filtro
     * de Vendedor ja resolve. */
    private function licenciadoOptions(array $user): array
    {
        if (!in_array($user['role_slug'], ['admin', 'gerente', 'supervisor'], true)) {
            return [];
        }

        $licenciados = User::allByRole('licenciado');
        if ($user['role_slug'] === 'admin') {
            return $licenciados;
        }

        $scopeIds = $user['role_slug'] === 'gerente'
            ? User::nationalIds((int) $user['id'])
            : User::supervisedIds((int) $user['id']);

        return array_values(array_filter($licenciados, fn ($l) => in_array((int) $l['id'], $scopeIds, true)));
    }

    public function kanban(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $quotes = Quote::all($this->scopeFilters($user));
        $columns = [];
        foreach (['aberto', 'aprovado', 'recusado', 'convertido'] as $status) {
            $columns[$status] = array_values(array_filter($quotes, fn ($q) => $q['status'] === $status));
        }

        View::render('painel/quotes/kanban', [
            'user' => $user,
            'columns' => $columns,
            'isViewOnly' => in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true),
        ]);
    }

    private function scopeFilters(array $user): array
    {
        if ($user['role_slug'] === 'admin') {
            return [];
        }
        if ($user['role_slug'] === Roles::SELLER) {
            return ['seller_id' => $user['id']];
        }
        if ($user['role_slug'] === 'supervisor') {
            return ['seller_ids' => User::supervisedIds((int) $user['id'])];
        }
        if ($user['role_slug'] === 'gerente') {
            return ['seller_ids' => User::nationalIds((int) $user['id'])];
        }

        return ['seller_ids' => User::downlineIds((int) $user['id'])];
    }

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
        if ($user['role_slug'] === 'supervisor') {
            return in_array($sellerId, User::supervisedIds((int) $user['id']), true);
        }
        if ($user['role_slug'] === 'gerente') {
            return in_array($sellerId, User::nationalIds((int) $user['id']), true);
        }

        return in_array($sellerId, User::downlineIds((int) $user['id']), true);
    }

    /** Gerente/Supervisor sao papel de suporte nacional -- so visualizam, nunca criam/editam orcamento */
    private function assertNotViewOnly(array $user): void
    {
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }

    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        Router::redirect('/painel/orcamentos?novo=1');
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);
        $this->assertNotViewOnly(Auth::user());

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

        $sellerId = $user['role_slug'] === Roles::SELLER ? $user['id'] : ($_POST['seller_id'] ?: null);

        $quoteId = Quote::create([
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'quote_date' => $_POST['quote_date'],
            'valid_until' => $_POST['valid_until'] ?? null,
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        if ($sellerId) {
            Approval::checkAndRequest('quote', $quoteId, $items, (int) $sellerId, (int) $user['id']);
        }

        $target = "/painel/orcamentos/{$quoteId}?sucesso=1";

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $isFragment = isset($_GET['fragment']);

        $payments = Payment::forPayable('quote', (int) $id);
        $quote['payment_situation'] = Payment::situationFor($quote, $payments[0] ?? null, 'recusado');

        View::render('painel/quotes/show', [
            'user' => Auth::user(),
            'quote' => $quote,
            'items' => QuoteItem::forQuote((int) $id),
            'payments' => $payments,
            'approval' => Approval::pendingFor('quote', (int) $id),
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function edit(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $user = Auth::user();

        if ($quote['status'] !== 'aberto') {
            Router::redirect("/painel/orcamentos/{$id}?erro=2");
        }

        $isFragment = isset($_GET['fragment']);

        View::render('painel/quotes/form', [
            'user' => $user,
            'editing' => $quote,
            'items' => QuoteItem::forQuote((int) $id),
            'clients' => Client::all(),
            'products' => Product::all(true),
            'sellers' => $this->sellerOptions($user),
            'preselectClientId' => 0,
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function update(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['client_id' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect("/painel/orcamentos/{$id}/editar?erro=1");
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        $errors = $this->validate($_POST, $items);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/quotes/form', [
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

        $sellerId = $user['role_slug'] === Roles::SELLER ? $quote['seller_id'] : ($_POST['seller_id'] ?: null);

        Quote::updateHeaderAndItems($id, [
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'quote_date' => $_POST['quote_date'],
            'valid_until' => $_POST['valid_until'] ?? null,
            'notes' => $_POST['notes'] ?? '',
        ], $items);

        if ($sellerId) {
            Approval::checkAndRequest('quote', $id, $items, (int) $sellerId, (int) $user['id']);
        }

        $target = "/painel/orcamentos/{$id}?sucesso=1";

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    /** "convertido" fica de fora de proposito -- so acontece de verdade via convert(), que cria
     * o Pedido junto. Deixar escolher "convertido" so' no dropdown criaria um orcamento
     * "convertido" sem nenhum pedido vinculado. */
    private const SETTABLE_STATUSES = ['aberto', 'aprovado', 'recusado'];

    public function markStatus(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            Router::redirect("/painel/orcamentos/{$id}");
        }

        $status = $_POST['status'] ?? '';
        if (in_array($status, self::SETTABLE_STATUSES, true) && $quote['status'] !== 'convertido') {
            Quote::updateStatus($id, $status);
            AuditLog::record((int) Auth::user()['id'], "orcamento_{$status}", 'quote', $id, ['status' => $quote['status']], ['status' => $status]);
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true]);
        }
        Router::redirect("/painel/orcamentos/{$id}?sucesso=1");
    }

    public function convert(string $id): void
    {
        $quote = $this->authorize((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || $quote['status'] === 'convertido') {
            Router::redirect("/painel/orcamentos/{$id}");
        }

        if (Approval::pendingFor('quote', $id)) {
            Router::redirect("/painel/orcamentos/{$id}?erro=3");
        }

        $orderId = Quote::convertToOrder($id);
        AuditLog::record((int) Auth::user()['id'], 'orcamento_convertido', 'quote', $id, ['status' => $quote['status']], ['order_id' => $orderId]);

        Router::redirect("/painel/pedidos/{$orderId}?sucesso=1");
    }

    private function authorize(int $id): array
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $quote = Quote::find($id);
        if (!$quote) {
            Router::redirect('/painel/orcamentos');
        }

        if (!$this->canAccessSeller($user, (int) ($quote['seller_id'] ?? 0))) {
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
