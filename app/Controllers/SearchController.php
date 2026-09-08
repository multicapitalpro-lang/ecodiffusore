<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Roles;
use App\Core\View;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;

/**
 * Busca global (nome/telefone/placa) -- achar lead/cliente/pedido rapido sem navegar por telas
 * separadas. Mesmo escopo por hierarquia ja usado em OrderController/LeadController (cada
 * controller mantem sua propria copia da logica de escopo, convencao ja estabelecida no projeto).
 */
class SearchController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $term = trim($_GET['q'] ?? '');

        $clients = [];
        $leads = [];
        $orders = [];

        if (mb_strlen($term) >= 2) {
            [$sellerIds, $includeUnassigned] = $this->scope($user);

            $clientFilters = $sellerIds === null ? [] : ['seller_ids' => $sellerIds];
            $clients = Client::search($term, $clientFilters);
            $leads = Lead::search($term, $sellerIds, $includeUnassigned);
            $orders = Order::search($term, $sellerIds);
        }

        View::render('painel/search/index', [
            'user' => $user,
            'term' => $term,
            'clients' => $clients,
            'leads' => $leads,
            'orders' => $orders,
        ]);
    }

    /** @return array{0: ?array, 1: bool} [sellerIds (null = sem escopo/Admin), includeUnassigned] */
    private function scope(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            return [null, true];
        }
        if ($role === Roles::SELLER) {
            return [User::downlineIds((int) $user['id']), false];
        }
        if ($role === 'supervisor') {
            return [User::supervisedIds((int) $user['id']), false];
        }
        if ($role === 'gerente') {
            return [User::nationalIds((int) $user['id']), false];
        }

        return [User::downlineIds((int) $user['id']), true];
    }
}
