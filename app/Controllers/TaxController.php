<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DateRange;
use App\Core\TaxReport;
use App\Core\View;
use App\Models\Order;

/**
 * Controle fiscal (Fase 25): quanto foi vendido, quanto de imposto e devido e o custo real do
 * produto, por pedido verificado -- usa a mesma tabela de precos por quantidade (PricingTier) que
 * ja define comissao. So admin e gerente (visao nacional) tem acesso -- e dado sensivel de toda
 * a operacao, nao so da regiao de um Licenciado.
 */
class TaxController
{
    private const ALLOWED_ROLES = ['admin', 'gerente'];

    public function index(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        [$from, $to] = DateRange::fromRequest();

        $orders = Order::all(['status' => 'verificado', 'from' => $from, 'to' => $to]);
        $report = TaxReport::forOrders($orders);

        View::render('painel/finance/taxes', [
            'user' => Auth::user(),
            'from' => $from,
            'to' => $to,
            'rows' => $report['rows'],
            'totals' => $report['totals'],
        ]);
    }
}
