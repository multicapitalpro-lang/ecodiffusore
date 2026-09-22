<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Client;
use App\Models\LeadRoutingSettings;
use App\Models\Order;
use App\Models\User;

/** Fase 76e: Clientes pro app -- mesmo escopo de App\Controllers\ClientController, devolvendo
 *  JSON. */
class ClientController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Clientes.', 403);
        }

        $clients = Client::all($this->scopeFilters($user));

        $items = array_map(fn ($c) => [
            'id' => (int) $c['id'],
            'name' => $c['name'],
            'whatsapp' => $c['whatsapp'],
            'city' => $c['city'],
            'state' => $c['state'],
            'status' => $c['status'],
            'seller_id' => $c['seller_id'] !== null ? (int) $c['seller_id'] : null,
        ], $clients);

        ApiResponse::json(['clients' => $items]);
    }

    public function show(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Clientes.', 403);
        }

        $id = (int) $id;
        $client = Client::find($id);
        if (!$client) {
            ApiResponse::error('Cliente nao encontrado.', 404);
        }

        if (!$this->canAccessSeller($user, (int) ($client['seller_id'] ?? 0))) {
            ApiResponse::error('Sem permissao pra ver este cliente.', 403);
        }

        $orders = Order::all(['client_id' => $id]);

        ApiResponse::json([
            'client' => [
                'id' => (int) $client['id'],
                'name' => $client['name'],
                'whatsapp' => $client['whatsapp'],
                'email' => $client['email'] ?? null,
                'document' => $client['document'] ?? null,
                'city' => $client['city'],
                'state' => $client['state'],
                'address' => $client['address'] ?? null,
                'status' => $client['status'],
            ],
            'orders' => array_map(fn ($o) => [
                'id' => (int) $o['id'],
                'order_date' => $o['order_date'],
                'status' => $o['status'],
                'total_value' => (float) $o['total_value'],
            ], $orders),
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

        return ['seller_ids' => User::downlineIds((int) $user['id']), 'include_unassigned' => LeadRoutingSettings::canSeeUnassigned($user)];
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

        return $sellerId === 0 || in_array($sellerId, User::downlineIds((int) $user['id']), true);
    }
}
