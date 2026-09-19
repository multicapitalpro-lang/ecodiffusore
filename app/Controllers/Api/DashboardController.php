<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\DateRange;
use App\Core\Roles;
use App\Models\Order;
use App\Models\User;

/** Fase 76: resumo do Dashboard pro app -- mesmo escopo por hierarquia de
 *  App\Controllers\DashboardController::index(), so que devolvendo JSON em vez de renderizar view. */
class DashboardController
{
    public function summary(): void
    {
        $user = ApiAuth::requireUser();
        $role = $user['role_slug'];

        if (!in_array($role, Roles::STAFF, true)) {
            ApiResponse::error('Papel sem resumo de vendas.', 403);
        }

        [$from, $to] = DateRange::fromRequest();

        $sellerId = null;
        $sellerIds = null;
        if ($role === Roles::SELLER) {
            $sellerId = (int) $user['id'];
        } elseif ($role === 'supervisor') {
            $sellerIds = User::supervisedIds((int) $user['id']);
        } elseif ($role === 'gerente') {
            $sellerIds = User::nationalIds((int) $user['id']);
        } elseif ($role !== 'admin') {
            $sellerIds = User::downlineIds((int) $user['id']);
        }

        $metrics = Order::metrics($from, $to, $sellerId, $sellerIds);
        $crmScope = $sellerId !== null ? ['seller_id' => $sellerId] : ($sellerIds !== null ? ['seller_ids' => $sellerIds] : []);
        $situation = Order::paymentSituationSummary($crmScope);

        ApiResponse::json([
            'from' => $from,
            'to' => $to,
            'metrics' => $metrics,
            'pedidos_pendentes' => $situation['pending']['count'],
            'pedidos_pendentes_valor' => $situation['pending']['total_value'],
            'pedidos_pagos' => $situation['paid']['count'],
            'pedidos_pagos_valor' => $situation['paid']['total_value'],
        ]);
    }
}
