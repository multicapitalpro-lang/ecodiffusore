<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\CorreiosClient;
use App\Core\Csrf;
use App\Core\Csv;
use App\Core\FileUpload;
use App\Core\Notifier;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\LeadRoutingSettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\User;

class OrderController
{
    private const DOCUMENT_APPROVER_ROLES = ['licenciado', 'gerente', 'admin'];

    /** Fase 99: fila de pedidos pagos com documento do veiculo completo, esperando revisao antes
     *  de ir pra fabrica -- Licenciado ve so' a propria rede, Gerente a rede nacional, Admin tudo. */
    public function pendingDocuments(): void
    {
        Auth::requireRole(self::DOCUMENT_APPROVER_ROLES);
        $user = Auth::user();

        $sellerIds = match ($user['role_slug']) {
            'admin' => null,
            'gerente' => User::nationalIds((int) $user['id']),
            default => User::downlineIds((int) $user['id']),
        };

        View::render('painel/orders/pending_documents', [
            'user' => $user,
            'orders' => Order::pendingDocumentApproval($sellerIds),
        ]);
    }

    /** Fase 99: aprova os documentos revisados -- so' a partir daqui o pedido entra na fila de
     *  despacho da Fabrica (ver Order::forFactory()). */
    public function approveDocuments(string $id): void
    {
        Auth::requireRole(self::DOCUMENT_APPROVER_ROLES);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        $order = Order::find($id);
        if (!$order || $order['status'] !== 'verificado' || !empty($order['is_cost_price']) || !empty($order['documents_approved_at'])) {
            Router::redirect('/painel/pedidos/aprovar-documentos?erro=1');
        }
        if (!Order::hasRequiredDocuments($order)) {
            Router::redirect('/painel/pedidos/aprovar-documentos?erro=1');
        }
        if ($user['role_slug'] !== 'admin' && !$this->canAccessSeller($user, (int) ($order['seller_id'] ?? 0))) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        Order::approveDocuments($id, (int) $user['id']);
        AuditLog::record((int) $user['id'], 'pedido_documentos_aprovados', 'order', $id, ['documents_approved_at' => null], ['documents_approved_at' => date('Y-m-d H:i:s')]);

        $freshOrder = Order::find($id);
        Notifier::pedidoDocumentosAprovadosFabrica($freshOrder);
        Notifier::documentosAprovadosCliente($freshOrder);

        Router::redirect('/painel/pedidos/aprovar-documentos?sucesso=1');
    }

    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        Order::expireStalePending();

        $filters = array_merge([
            'status' => $_GET['status'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
            'city' => $_GET['city'] ?? null,
        ], $this->scopeFilters($user));

        $orders = Order::all($filters);
        [$orders, $stats] = $this->attachPaymentSituation($orders);

        // Filtro vindo dos cards clicaveis do Dashboard (Pedidos pendentes/pagos) -- so' faz
        // sentido em cima da situacao ja calculada (Payment::situationFor), que nao e' uma coluna
        // do banco, entao filtra a lista ja carregada em vez de mexer em Order::all().
        $situacaoPagamento = $_GET['situacao_pagamento'] ?? null;
        if ($situacaoPagamento === 'pendente') {
            $orders = array_values(array_filter($orders, fn ($o) => in_array($o['payment_situation']['slug'], ['pendente', 'expirado'], true)));
        } elseif ($situacaoPagamento === 'pago') {
            $orders = array_values(array_filter($orders, fn ($o) => $o['payment_situation']['slug'] === 'pago'));
        }

        $showLicenciadoColumn = in_array($user['role_slug'], ['supervisor', 'gerente'], true);
        if ($showLicenciadoColumn) {
            foreach ($orders as &$o) {
                $o['licenciado_name'] = User::licenciadoNameFor((int) ($o['seller_id'] ?? 0));
            }
            unset($o);
        }

        View::render('painel/orders/index', [
            'user' => $user,
            'orders' => $orders,
            'stats' => $stats,
            'situacaoPagamento' => $situacaoPagamento,
            'filters' => $filters,
            'clients' => Client::all(array_merge($this->scopeFilters(Auth::user()), ['include_unassigned' => LeadRoutingSettings::canSeeUnassigned(Auth::user())])),
            'products' => Product::all(true),
            'pricingTiers' => PricingTier::all(),
            'sellers' => $this->sellerOptions($user),
            'showLicenciadoColumn' => $showLicenciadoColumn,
        ]);
    }

    /** Anexa a situacao de pagamento (Payment::situationFor) em cada pedido, sem N+1 (1 query so
     * pra buscar o pagamento mais recente de todos os pedidos da pagina), e ja soma as
     * contagens pros cards do topo. */
    private function attachPaymentSituation(array $orders): array
    {
        $ids = array_map(fn ($o) => (int) $o['id'], $orders);
        $latestPayments = Payment::latestByPayableIds('order', $ids);

        $stats = ['concluidos' => 0, 'pendentes' => 0, 'pagos' => 0, 'cancelados' => 0];

        foreach ($orders as &$order) {
            $payment = $latestPayments[(int) $order['id']] ?? null;
            $situation = Payment::situationFor($order, $payment);
            $order['payment_situation'] = $situation;
            $order['payment_method'] = $payment['method'] ?? null;

            if ($order['status'] === 'verificado') {
                $stats['concluidos']++;
            }
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
        unset($order);

        return [$orders, $stats];
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
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['client_id' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/pedidos?erro=1');
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        // Fase 98: pedido a preco de custo (mostruario) -- so' Admin, sem vendedor/comissao/
        // aprovacao, e o piso de preco normal (PricingTier::forPrice) nao se aplica.
        $isCostPrice = $user['role_slug'] === 'admin' && !empty($_POST['is_cost_price']);
        $errors = $this->validate($_POST, $items, $user['role_slug'], $isCostPrice);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            [$ordersWithSituation, $stats] = $this->attachPaymentSituation(Order::all($this->scopeFilters($user)));
            View::render('painel/orders/index', [
                'user' => $user,
                'orders' => $ordersWithSituation,
                'stats' => $stats,
                'filters' => [],
                'clients' => Client::all(array_merge($this->scopeFilters(Auth::user()), ['include_unassigned' => LeadRoutingSettings::canSeeUnassigned(Auth::user())])),
                'products' => Product::all(true),
            'pricingTiers' => PricingTier::all(),
                'sellers' => $this->sellerOptions($user),
                'errors' => $errors,
                'values' => $_POST,
                'items' => $items,
            ]);
            return;
        }

        $sellerId = $isCostPrice ? null : ($user['role_slug'] === Roles::SELLER ? $user['id'] : ($_POST['seller_id'] ?: null));

        $vehicleDocument = null;
        try {
            $vehicleDocument = FileUpload::storeVehicleDocument($_FILES['vehicle_document'] ?? []);
        } catch (\RuntimeException $e) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['vehicle_document' => $e->getMessage()]]);
            }
        }

        $cnhDocument = null;
        try {
            $cnhDocument = FileUpload::storeCnhDocument($_FILES['cnh_document'] ?? []);
        } catch (\RuntimeException $e) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['cnh_document' => $e->getMessage()]]);
            }
        }

        $orderId = Order::create([
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'order_date' => $_POST['order_date'],
            'notes' => $_POST['notes'] ?? '',
            'vehicle_type' => $_POST['vehicle_type'] ?? '',
            'vehicle_plate' => $_POST['vehicle_plate'] ?? '',
            'vehicle_document_path' => $vehicleDocument['stored_name'] ?? null,
            'cnh_document_path' => $cnhDocument['stored_name'] ?? null,
            'is_cost_price' => $isCostPrice,
        ], $items);

        if ($sellerId) {
            Approval::checkAndRequest('order', $orderId, $items, (int) $sellerId, (int) $user['id'], trim($_POST['motivo_desconto'] ?? '') ?: null);
            $createdOrder = Order::find($orderId);
            if ($createdOrder) {
                Notifier::pedidoRealizado($createdOrder);
            }
        }

        // Cliente nao precisa de cadastro previo pra comprar -- a conta de acesso ao portal so
        // nasce automaticamente aqui, quando o pedido de fato e' registrado (decisao explicita do
        // usuario, Fase 27b). Sem efeito se o cliente ja tiver conta, nao tiver e-mail valido, ou
        // o e-mail ja estar em uso (ver Client::autoCreatePortalAccess).
        $orderClient = Client::find((int) $_POST['client_id']);
        $tempPassword = $orderClient ? Client::autoCreatePortalAccess($orderClient) : null;
        if ($tempPassword) {
            Notifier::acessoPortalCriado($orderClient, $tempPassword);
        }

        $target = "/painel/pedidos/{$orderId}?sucesso=1";
        if ($tempPassword) {
            $target .= '&acesso_criado=1&temp=' . urlencode($tempPassword);
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);
        $isFragment = isset($_GET['fragment']);

        $payments = Payment::forPayable('order', (int) $id);
        $order['payment_situation'] = Payment::situationFor($order, $payments[0] ?? null);
        // Fase 82: Gerente/Supervisor (rede nacional, so visualizam) so viam o Vendedor no
        // detalhe do Pedido -- pedido explicito do usuario pra tambem mostrar o Licenciado da
        // rede daquele pedido.
        $order['licenciado_name'] = User::licenciadoNameFor($order['seller_id'] !== null ? (int) $order['seller_id'] : null);

        View::render('painel/orders/show', [
            'user' => Auth::user(),
            'order' => $order,
            'items' => OrderItem::forOrder((int) $id),
            'payments' => $payments,
            'approval' => Approval::pendingFor('order', (int) $id),
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function edit(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);

        if ($order['status'] !== 'em_andamento') {
            Router::redirect("/painel/pedidos/{$id}?erro=2");
        }

        $isFragment = isset($_GET['fragment']);

        View::render('painel/orders/form', [
            'user' => Auth::user(),
            'editing' => $order,
            'items' => OrderItem::forOrder((int) $id),
            'clients' => Client::all(array_merge($this->scopeFilters(Auth::user()), ['include_unassigned' => LeadRoutingSettings::canSeeUnassigned(Auth::user())])),
            'products' => Product::all(true),
            'pricingTiers' => PricingTier::all(),
            'sellers' => $this->sellerOptions(Auth::user()),
            'preselectClientId' => 0,
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function update(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['client_id' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect("/painel/pedidos/{$id}/editar?erro=1");
        }

        $user = Auth::user();
        $items = $this->parseItems($_POST);
        // Fase 98: is_cost_price e' definido so' na criacao (nunca via POST de edicao) -- reusa o
        // valor ja gravado no pedido, senao uma edicao de quantidade num pedido de custo ja
        // existente esbarraria de novo no piso de preco normal.
        $isCostPrice = !empty($order['is_cost_price']);
        $errors = $this->validate($_POST, $items, $user['role_slug'], $isCostPrice);

        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            View::render('painel/orders/form', [
                'user' => $user,
                'editing' => array_merge(['id' => $id], $_POST),
                'items' => $items,
                'clients' => Client::all(array_merge($this->scopeFilters(Auth::user()), ['include_unassigned' => LeadRoutingSettings::canSeeUnassigned(Auth::user())])),
                'products' => Product::all(true),
            'pricingTiers' => PricingTier::all(),
                'sellers' => $this->sellerOptions($user),
                'preselectClientId' => 0,
                'errors' => $errors,
            ]);
            return;
        }

        $sellerId = $isCostPrice ? null : ($user['role_slug'] === Roles::SELLER ? $order['seller_id'] : ($_POST['seller_id'] ?: null));

        $orderData = [
            'client_id' => (int) $_POST['client_id'],
            'seller_id' => $sellerId,
            'order_date' => $_POST['order_date'],
            'notes' => $_POST['notes'] ?? '',
            'vehicle_type' => $_POST['vehicle_type'] ?? '',
            'vehicle_plate' => $_POST['vehicle_plate'] ?? '',
        ];

        if (!empty($_FILES['vehicle_document']['name'])) {
            try {
                $vehicleDocument = FileUpload::storeVehicleDocument($_FILES['vehicle_document']);
                $orderData['vehicle_document_path'] = $vehicleDocument['stored_name'] ?? null;
            } catch (\RuntimeException $e) {
                if (Response::isAjax()) {
                    Response::json(['ok' => false, 'errors' => ['vehicle_document' => $e->getMessage()]]);
                }
            }
        }

        if (!empty($_FILES['cnh_document']['name'])) {
            try {
                $cnhDocument = FileUpload::storeCnhDocument($_FILES['cnh_document']);
                $orderData['cnh_document_path'] = $cnhDocument['stored_name'] ?? null;
            } catch (\RuntimeException $e) {
                if (Response::isAjax()) {
                    Response::json(['ok' => false, 'errors' => ['cnh_document' => $e->getMessage()]]);
                }
            }
        }

        // Fase 79c: registra em Auditoria toda edicao de Pedido (pedido explicito do usuario --
        // "pra podermos ter registro do que ele alterou e em que momento, pra caso haja interesse
        // de conflito"). Guarda cabecalho + itens ANTES e DEPOIS, nao so o status (que ja tinha
        // log em markStatus/refundPayment) -- e' a edicao de preco/produto que mais importa aqui.
        $itemsBefore = array_map(fn ($i) => ['product_id' => (int) $i['product_id'], 'quantity' => (int) $i['quantity'], 'unit_price' => (float) $i['unit_price']], OrderItem::forOrder($id));

        Order::updateHeaderAndItems($id, $orderData, $items);

        $itemsAfter = array_map(fn ($i) => ['product_id' => (int) $i['product_id'], 'quantity' => (int) $i['quantity'], 'unit_price' => (float) $i['unit_price']], OrderItem::forOrder($id));
        AuditLog::record(
            (int) $user['id'],
            'pedido_editado',
            'order',
            $id,
            [
                'client_id' => (int) $order['client_id'],
                'seller_id' => $order['seller_id'] !== null ? (int) $order['seller_id'] : null,
                'order_date' => $order['order_date'],
                'notes' => $order['notes'],
                'items' => $itemsBefore,
            ],
            [
                'client_id' => $orderData['client_id'],
                'seller_id' => $orderData['seller_id'] !== null ? (int) $orderData['seller_id'] : null,
                'order_date' => $orderData['order_date'],
                'notes' => $orderData['notes'],
                'items' => $itemsAfter,
            ]
        );

        if ($sellerId) {
            Approval::checkAndRequest('order', $id, $items, (int) $sellerId, (int) $user['id'], trim($_POST['motivo_desconto'] ?? '') ?: null);
        }

        $target = "/painel/pedidos/{$id}?sucesso=1";

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    /** Fase 98: Admin anexa o comprovante do Pix que mandou pra Fabrica, num pedido a preco de
     *  custo ja verificado -- so' depois disso o pedido entra na fila de despacho dela (ver
     *  Order::forFactory()) e ela recebe o aviso (Notifier::pedidoCustoProntoParaFabrica()).
     *  Tambem registra a saida ja paga em Caixas e Bancos, pra o fluxo de caixa bater sozinho. */
    public function attachFactoryPaymentProof(string $id): void
    {
        Auth::requireRole(['admin']);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        $order = Order::find($id);
        if (!$order || empty($order['is_cost_price']) || $order['status'] !== 'verificado') {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        $amount = is_numeric($_POST['factory_payment_amount'] ?? null) ? (float) $_POST['factory_payment_amount'] : 0.0;
        if ($amount <= 0) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        try {
            $proof = FileUpload::storeFactoryPaymentProof($_FILES['factory_payment_proof'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }
        if (!$proof) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        Order::setFactoryPaymentProof($id, $amount, $proof['stored_name'], $proof['original_name']);

        $accountId = FinancialAccount::defaultAccountId();
        if ($accountId) {
            FinancialTransaction::createForFactoryPayment($id, $accountId, $amount, date('Y-m-d'));
        }

        Notifier::pedidoCustoProntoParaFabrica(Order::find($id));

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    /** Download autenticado do comprovante -- so' Admin, nunca serve arquivo estatico direto
     *  (mesmo padrao ja usado nos outros anexos do sistema). */
    public function downloadFactoryPaymentProof(string $id): void
    {
        Auth::requireRole(['admin']);
        $id = (int) $id;

        $order = Order::find($id);
        if (!$order || !$order['factory_payment_proof_path']) {
            Router::redirect('/painel/pedidos');
        }

        $path = FileUpload::path('factory_payment_proofs', $order['factory_payment_proof_path']);
        if (!file_exists($path)) {
            Router::redirect('/painel/pedidos');
        }

        header('Content-Type: ' . (mime_content_type($path) ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . rawurlencode($order['factory_payment_proof_original_name'] ?: 'comprovante.pdf') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function markStatus(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

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

        if ($status === 'cancelado') {
            Notifier::pedidoCancelado($order);
        }

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    /** Rastreio (transportadora + codigo) -- metadado livre sem efeito colateral (sem comissao/
     *  financeiro/Notifier), por isso e' um metodo separado de markStatus() em vez de uma branch
     *  nova dele, mesmo raciocinio que ja separou refundPayment(). */
    public function updateTracking(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        $trackingCode = trim($_POST['tracking_code'] ?? '');
        Order::updateTracking($id, $trackingCode, trim($_POST['tracking_carrier'] ?? ''), trim($_POST['prazo_entrega'] ?? ''));

        // Mesmo feedback imediato de status que a Fabrica ganha (FactoryController::updateDelivery) --
        // o CorreiosTrackingChecker::processDue() mantem atualizado depois disso.
        if ($trackingCode !== '') {
            try {
                $result = (new CorreiosClient())->track($trackingCode);
                if ($result) {
                    Order::updateTrackingStatus($id, $result['status'] ?? null, $result['date'] ?? null, $result['entregue'] ?? false);
                }
            } catch (\Throwable $e) {
                // Best-effort -- sem status na hora nao deve impedir o salvamento do rastreio.
            }
        }

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    /** Lista de pedidos pagos pra toda a cadeia comercial acompanhar entrega (Fase 28) -- reaproveita
     *  o mesmo escopo por hierarquia de scopeFilters(), so leitura, sem os dados financeiros. */
    public function deliveries(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $filters = array_merge(['status' => 'verificado'], $this->scopeFilters($user));

        View::render('painel/orders/deliveries', ['user' => $user, 'orders' => Order::all($filters)]);
    }

    /** Marca o pagamento mais recente do pedido como reembolsado -- so muda a "situacao" exibida
     * (ver Payment::situationFor), nao mexe no status do pedido nem desfaz comissao/lancamento
     * ja gerados (isso e uma decisao financeira separada, fora do escopo deste botao). */
    public function refundPayment(string $id): void
    {
        $this->authorizeOrder((int) $id);
        $id = (int) $id;
        $this->assertNotViewOnly(Auth::user());

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        $payments = Payment::forPayable('order', $id);
        $latest = $payments[0] ?? null;
        if ($latest) {
            Payment::markRefunded((int) $latest['id']);
            AuditLog::record((int) Auth::user()['id'], 'pagamento_reembolsado', 'order', $id, ['payment_id' => $latest['id']], []);
        }

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    public function downloadVehicleDocument(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);

        if (!$order['vehicle_document_path']) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $path = FileUpload::path('vehicle_docs', $order['vehicle_document_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        // Sem a extensao real e o Content-Type certo, o navegador salvava um arquivo generico
        // que nao abria em nenhum programa (mesmo bug corrigido em LicenciadoOnboardingController).
        $extension = pathinfo($order['vehicle_document_path'], PATHINFO_EXTENSION);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="documento-veiculo-' . (int) $id . ($extension ? '.' . $extension : '') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function downloadCnhDocument(string $id): void
    {
        $order = $this->authorizeOrder((int) $id);

        if (!$order['cnh_document_path']) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $path = FileUpload::path('cnh_docs', $order['cnh_document_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $extension = pathinfo($order['cnh_document_path'], PATHINFO_EXTENSION);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="cnh-' . (int) $id . ($extension ? '.' . $extension : '') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Fase 61: mesmo padrao dos 2 downloads acima, generico pros campos novos (fotos +
     *  telemetria) enviados pelo proprio cliente -- todo STAFF com acesso ao pedido (Vendedor,
     *  Gestor, Licenciado, Supervisor, Gerente, Admin) consegue ver, via a mesma tela do Pedido. */
    public function downloadOrderFile(string $id, string $field): void
    {
        $order = $this->authorizeOrder((int) $id);

        if (!array_key_exists($field, Order::VEHICLE_FILE_SUBDIRS) || empty($order[$field])) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $path = FileUpload::path(Order::VEHICLE_FILE_SUBDIRS[$field], $order[$field]);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $extension = pathinfo($order[$field], PATHINFO_EXTENSION);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $field . '-pedido-' . (int) $id . ($extension ? '.' . $extension : '') . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
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

    /** Filtro de escopo pra listar pedidos: vendedor so os proprios, gestor/licenciado a regiao,
     *  supervisor/gerente a rede que cuidam (visualizacao, nao vendem), admin tudo */
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

    /** Gerente/Supervisor sao papel de suporte nacional -- so visualizam, nunca criam/editam pedido */
    private function assertNotViewOnly(array $user): void
    {
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
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

    private function validate(array $input, array $items, string $role = '', bool $isCostPrice = false): array
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

        // Fase 31: preco unitario negociado livremente, mas com piso bloqueado -- mesma regra da
        // Proposta Facil (ver PropostaController::validate()), agora tambem pro Pedido manual.
        // Fase 98: pedido a preco de custo (so' Admin) fica de fora desse piso de proposito -- e'
        // exatamente pra isso que ele existe (compra a preco de fabrica, sem vendedor/comissao).
        $lowestPrice = null;
        foreach ($items as $item) {
            if (!$isCostPrice && $item['unit_price'] > 0 && !PricingTier::forPrice($item['unit_price'])) {
                $floorTier = PricingTier::all()[0] ?? null;
                $floor = $floorTier ? number_format((float) $floorTier['min_price'], 2, ',', '.') : '0,00';
                $errors['items'] = "Preço unitário abaixo do mínimo negociável (R$ {$floor}).";
                break;
            }
            if ($item['unit_price'] > 0 && ($lowestPrice === null || $item['unit_price'] < $lowestPrice)) {
                $lowestPrice = $item['unit_price'];
            }
        }

        // Fase 80: Vendedor pedindo abaixo do proprio piso (vira pendencia de aprovacao) precisa
        // explicar o motivo pro Licenciado/Gerente analisarem com contexto -- pedido explicito do
        // usuario.
        if ($role === Roles::SELLER && $lowestPrice !== null && $lowestPrice < PricingTier::VENDOR_STANDARD_PRICE
            && trim($input['motivo_desconto'] ?? '') === '') {
            $errors['motivo_desconto'] = 'Explique o motivo do preço abaixo do padrão -- o Gestor/Licenciado e o Gerente vão ver isso pra decidir.';
        }

        return $errors;
    }
}
