<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Approval;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;

/** Fase 76f: Orcamentos pro app -- mesmo escopo/autorizacao de App\Controllers\QuoteController,
 *  devolvendo JSON. Praticamente o mesmo formato de Api\OrderController (Pedidos), ja que
 *  orcamento e pedido compartilham a mesma estrutura de negocio. */
class QuoteController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Orcamentos.', 403);
        }

        $filters = array_merge([
            'status' => $_GET['status'] ?? null,
        ], $this->scopeFilters($user));

        $quotes = Quote::all($filters);

        $items = array_map(function ($q) {
            $payments = Payment::forPayable('quote', (int) $q['id']);
            $situation = Payment::situationFor($q, $payments[0] ?? null, 'recusado');

            return [
                'id' => (int) $q['id'],
                'quote_date' => $q['quote_date'],
                'valid_until' => $q['valid_until'],
                'status' => $q['status'],
                'client_name' => $q['client_name'],
                'client_city' => $q['client_city'],
                'client_state' => $q['client_state'],
                'seller_name' => $q['seller_name'],
                'total_value' => (float) $q['total_value'],
                'payment_situation' => $situation,
            ];
        }, $quotes);

        ApiResponse::json(['quotes' => $items]);
    }

    public function show(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Orcamentos.', 403);
        }

        $id = (int) $id;
        $quote = Quote::find($id);
        if (!$quote) {
            ApiResponse::error('Orcamento nao encontrado.', 404);
        }

        if (!$this->canAccessSeller($user, (int) ($quote['seller_id'] ?? 0))) {
            ApiResponse::error('Sem permissao pra ver este orcamento.', 403);
        }

        $payments = Payment::forPayable('quote', $id);
        $situation = Payment::situationFor($quote, $payments[0] ?? null, 'recusado');
        $approval = Approval::pendingFor('quote', $id);

        ApiResponse::json([
            'quote' => [
                'id' => (int) $quote['id'],
                'quote_date' => $quote['quote_date'],
                'valid_until' => $quote['valid_until'],
                'status' => $quote['status'],
                'total_value' => (float) $quote['total_value'],
                'client_id' => (int) $quote['client_id'],
                'seller_id' => $quote['seller_id'] !== null ? (int) $quote['seller_id'] : null,
                'licenciado_name' => User::licenciadoNameFor($quote['seller_id'] !== null ? (int) $quote['seller_id'] : null),
                'payment_situation' => $situation,
            ],
            'items' => array_map(fn ($i) => [
                'product_name' => $i['product_name'],
                'quantity' => (int) $i['quantity'],
                'unit_price' => (float) $i['unit_price'],
            ], QuoteItem::forQuote($id)),
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
