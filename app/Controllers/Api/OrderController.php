<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Approval;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;

/** Fase 76c: Pedidos pro app -- mesmo escopo por hierarquia de App\Controllers\OrderController
 *  (scopeFilters/canAccessSeller), devolvendo JSON. */
class OrderController
{
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

        ApiResponse::json([
            'order' => [
                'id' => (int) $order['id'],
                'order_date' => $order['order_date'],
                'status' => $order['status'],
                'total_value' => (float) $order['total_value'],
                'client_id' => (int) $order['client_id'],
                'seller_id' => $order['seller_id'] !== null ? (int) $order['seller_id'] : null,
                'payment_situation' => $order['payment_situation'],
                'public_link' => !empty($order['public_token']) ? "https://ecodiffusorebrasil.com.br/pedido/{$order['public_token']}" : null,
                'terms_accepted_at' => $order['terms_accepted_at'] ?? null,
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
