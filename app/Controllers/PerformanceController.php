<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DateRange;
use App\Core\Roles;
use App\Core\View;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class PerformanceController
{
    public function sellers(): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

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
        Auth::requireRole(Roles::MANAGEMENT);
        $user = Auth::user();

        // Admin ve todas as regioes; Licenciado/Gerente veem so a propria (ninguem enxerga a
        // estrutura de outra regiao por aqui, mesmo path de escopo usado no Financeiro).
        if ($user['role_slug'] === 'admin') {
            $users = User::all();
            $roots = array_values(array_filter($users, fn ($u) => $u['role_slug'] === Roles::REGIONAL_OWNER));
        } else {
            $downline = User::downlineIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
            $roots = [User::find((int) $user['id'])];
        }

        $byManager = [];
        foreach ($users as $u) {
            $byManager[(int) ($u['manager_id'] ?? 0)][] = $u;
        }

        $totals = [];
        $beneficiaryIds = array_map(fn ($u) => (int) $u['id'], $users);
        foreach (Commission::byBeneficiary(['beneficiary_ids' => $beneficiaryIds]) as $row) {
            $totals[(int) $row['beneficiary_id']] = $row;
        }

        View::render('painel/performance/team', [
            'user' => $user,
            'roots' => $roots,
            'byManager' => $byManager,
            'totals' => $totals,
        ]);
    }
}
