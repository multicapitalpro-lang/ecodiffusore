<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Notifier;
use App\Core\Roles;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

/** Fase 76c/90: Pedidos pro app -- mesmo escopo por hierarquia de App\Controllers\OrderController
 *  (scopeFilters/canAccessSeller), devolvendo JSON. Fase 90: show() ganhou paridade de conteudo
 *  com o modal do painel web (cliente/vendedor/veiculo/documentos/rastreio/observacoes -- o app so
 *  mostrava um resumo bem mais pobre antes), alem de endpoints pra mudar status e salvar rastreio.
 *  Sem download de anexo aqui (CNH/documento do veiculo/fotos) -- so' indica se cada um existe
 *  (booleano), mesmo corte ja aplicado em MachineQuoteController/Warranty pro app. */
class OrderController
{
    private const STATUS_LABELS = [
        'em_andamento' => 'Em andamento',
        'atendido' => 'Atendido',
        'verificado' => 'Verificado',
        'cancelado' => 'Cancelado',
    ];

    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Pedidos.', 403);
        }

        Order::expireStalePending();

        $filters = array_merge([
            'status' => $_GET['status'] ?? null,
        ], $this->scopeFilters($user));

        $orders = Order::all($filters);

        $orders = array_map(function ($o) {
            $payments = Payment::forPayable('order', (int) $o['id']);
            $situation = Payment::situationFor($o, $payments[0] ?? null);

            return [
                'id' => (int) $o['id'],
                'order_date' => $o['order_date'],
                'status' => $o['status'],
                'client_name' => $o['client_name'],
                'client_city' => $o['client_city'],
                'client_state' => $o['client_state'],
                'seller_name' => $o['seller_name'],
                'product_names' => $o['product_names'],
                'total_value' => (float) $o['total_value'],
                'payment_situation' => $situation,
                'public_token' => $o['public_token'] ?? null,
            ];
        }, $orders);

        ApiResponse::json(['orders' => $orders]);
    }

    public function show(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Pedidos.', 403);
        }

        $id = (int) $id;
        $order = Order::find($id);
        if (!$order) {
            ApiResponse::error('Pedido nao encontrado.', 404);
        }

        if (!$this->canAccessSeller($user, (int) ($order['seller_id'] ?? 0))) {
            ApiResponse::error('Sem permissao pra ver este pedido.', 403);
        }

        $payments = Payment::forPayable('order', $id);
        $order['payment_situation'] = Payment::situationFor($order, $payments[0] ?? null);
        $approval = Approval::pendingFor('order', $id);
        $isViewOnly = in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true);

        ApiResponse::json([
            'order' => [
                'id' => (int) $order['id'],
                'order_date' => $order['order_date'],
                'status' => $order['status'],
                'status_label' => self::STATUS_LABELS[$order['status']] ?? $order['status'],
                'total_value' => (float) $order['total_value'],
                'client_id' => (int) $order['client_id'],
                'client_name' => $order['client_name'],
                'client_whatsapp' => $order['client_whatsapp'] ?? null,
                'client_city' => $order['client_city'] ?? null,
                'client_state' => $order['client_state'] ?? null,
                'seller_id' => $order['seller_id'] !== null ? (int) $order['seller_id'] : null,
                'seller_name' => $order['seller_name'] ?? null,
                'licenciado_name' => User::licenciadoNameFor($order['seller_id'] !== null ? (int) $order['seller_id'] : null),
                'influencer_name' => $order['influencer_name'] ?? null,
                'payment_situation' => $order['payment_situation'],
                'public_link' => !empty($order['public_token']) ? "https://ecodiffusorebrasil.com.br/pedido/{$order['public_token']}" : null,
                'terms_accepted_at' => $order['terms_accepted_at'] ?? null,
                'notes' => $order['notes'] ?? null,
                'vehicle_type' => $order['vehicle_type'] ?? null,
                'vehicle_plate' => $order['vehicle_plate'] ?? null,
                'has_vehicle_document' => !empty($order['vehicle_document_path']),
                'has_cnh' => !empty($order['cnh_document_path']),
                'has_photo1' => !empty($order['photo1_path']),
                'has_photo2' => !empty($order['photo2_path']),
                'has_photo3' => !empty($order['photo3_path']),
                'has_telemetry' => !empty($order['telemetry_path']),
                'missing_document_labels' => Order::missingDocumentLabels($order),
                'tracking_carrier' => $order['tracking_carrier'] ?? null,
                'tracking_code' => $order['tracking_code'] ?? null,
                'prazo_entrega' => $order['prazo_entrega'] ?? null,
                'tracking_status' => $order['tracking_status'] ?? null,
                'tracking_status_date' => $order['tracking_status_date'] ?? null,
                'is_view_only' => $isViewOnly,
            ],
            'items' => array_map(fn ($i) => [
                'product_name' => $i['product_name'],
                'quantity' => (int) $i['quantity'],
                'unit_price' => (float) $i['unit_price'],
            ], OrderItem::forOrder($id)),
            'payments' => array_map(fn ($p) => [
                'id' => (int) $p['id'],
                'billing_type' => $p['billing_type'],
                'status' => $p['status'],
                'amount' => (float) $p['amount'],
                'created_at' => $p['created_at'],
            ], $payments),
            'approval_pending' => $approval ? [
                'id' => (int) $approval['id'],
                'status_label' => Approval::statusLabel($approval),
            ] : null,
        ]);
    }

    /** Mesma maquina de estado de App\Controllers\OrderController::markStatus() -- "verificado"
     *  gera comissao (Order::markVerifiedWithCommission), "cancelado" avisa a rede. */
    public function markStatus(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            ApiResponse::error('Gerente e Supervisor tem acesso de visualizacao.', 403);
        }

        $id = (int) $id;
        $order = Order::find($id);
        if (!$order || !$this->canAccessSeller($user, (int) ($order['seller_id'] ?? 0))) {
            ApiResponse::error('Pedido nao encontrado.', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $status = $body['status'] ?? '';
        if (!in_array($status, ['atendido', 'verificado', 'cancelado'], true)) {
            ApiResponse::error('Status invalido.', 422);
        }

        if ($status === 'verificado') {
            $ok = Order::markVerifiedWithCommission($id);
            if ($ok) {
                AuditLog::record((int) $user['id'], 'pedido_verificado', 'order', $id, ['status' => $order['status']], ['status' => 'verificado']);
            }
            if (!$ok) {
                ApiResponse::error('Nao foi possivel verificar o pedido.', 422);
            }
            ApiResponse::json(['ok' => true]);
        }

        Order::updateStatus($id, $status);
        if ($status === 'cancelado') {
            Notifier::pedidoCancelado($order);
        }

        ApiResponse::json(['ok' => true]);
    }

    /** Mesma logica de App\Controllers\OrderController::updateTracking() -- sem a checagem ao
     *  vivo do status dos Correios aqui (best-effort no painel web; o cron
     *  CorreiosTrackingChecker::processDue() mantem isso atualizado de qualquer forma). */
    public function updateTracking(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            ApiResponse::error('Gerente e Supervisor tem acesso de visualizacao.', 403);
        }

        $id = (int) $id;
        $order = Order::find($id);
        if (!$order || !$this->canAccessSeller($user, (int) ($order['seller_id'] ?? 0))) {
            ApiResponse::error('Pedido nao encontrado.', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        Order::updateTracking(
            $id,
            trim($body['tracking_code'] ?? ''),
            trim($body['tracking_carrier'] ?? ''),
            trim($body['prazo_entrega'] ?? '')
        );

        ApiResponse::json(['ok' => true]);
    }

    /** Fase 76h: Acompanhar Entregas -- mesma consulta de OrderController::deliveries() (so
     *  pedidos verificados, mesmo escopo por hierarquia), devolvendo JSON. */
    public function deliveries(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Entregas.', 403);
        }

        $filters = array_merge(['status' => 'verificado'], $this->scopeFilters($user));
        $orders = Order::all($filters);

        ApiResponse::json(['orders' => array_map(fn ($o) => [
            'id' => (int) $o['id'],
            'order_date' => $o['order_date'],
            'client_name' => $o['client_name'],
            'client_city' => $o['client_city'],
            'client_state' => $o['client_state'],
            'product_names' => $o['product_names'],
            'tracking_carrier' => $o['tracking_carrier'] ?? null,
            'tracking_code' => $o['tracking_code'] ?? null,
            'prazo_entrega' => $o['prazo_entrega'] ?? null,
        ], $orders)]);
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
}
