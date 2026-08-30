<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DateRange;
use App\Core\View;
use App\Models\Order;
use App\Models\OrderItem;

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
}
