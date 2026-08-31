<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DateRange;
use App\Core\View;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class PerformanceController
{
    public function sellers(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        [$from, $to] = DateRange::fromRequest();

        View::render('painel/performance/sellers', [
            'user' => Auth::user(),
            'from' => $from,
            'to' => $to,
            'ranking' => Order::sellerRanking($from, $to),
            'topProducts' => OrderItem::topProducts($from, $to),
        ]);
    }

    public function team(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

        $users = User::all();
        $byManager = [];
        foreach ($users as $u) {
            $byManager[(int) ($u['manager_id'] ?? 0)][] = $u;
        }

        $totals = [];
        foreach (Commission::byBeneficiary() as $row) {
            $totals[(int) $row['beneficiary_id']] = $row;
        }

        $gerentes = array_values(array_filter($users, fn ($u) => $u['role_slug'] === 'gerente'));
        $semGerente = array_values(array_filter($users, fn ($u) => $u['role_slug'] === 'supervisor' && empty($u['manager_id'])));

        View::render('painel/performance/team', [
            'user' => Auth::user(),
            'gerentes' => $gerentes,
            'semGerente' => $semGerente,
            'byManager' => $byManager,
            'totals' => $totals,
        ]);
    }
}
