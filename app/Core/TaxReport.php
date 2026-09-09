<?php

namespace App\Core;

use App\Models\PricingTier;

/**
 * Imposto e custo real por pedido (Fase 25) -- usa a mesma tabela de faixas de preco negociavel
 * (PricingTier, Fase 31 -- antes era faixa por quantidade na Fase 24) que ja define a comissao do
 * Licenciado: cada pedido cai numa faixa pelo PRECO UNITARIO MEDIO negociado (total/quantidade),
 * e essa faixa tem tax_pct (% sobre o valor do pedido) e cost_price (custo por UNIDADE --
 * multiplicado pela quantidade, ao contrario da comissao que e sempre %). So considera pedidos com
 * status='verificado' (venda confirmada) -- pendente/cancelado nao gera imposto devido de verdade.
 */
class TaxReport
{
    /** @param array $orders Order::all(['status' => 'verificado', ...]) -- precisa do campo total_qty */
    public static function forOrders(array $orders): array
    {
        $rows = [];
        $totals = ['revenue' => 0.0, 'tax' => 0.0, 'cost' => 0.0, 'net' => 0.0, 'count' => 0];

        foreach ($orders as $order) {
            $qty = (int) ($order['total_qty'] ?? 0);
            $total = (float) $order['total_value'];
            $avgUnitPrice = $qty > 0 ? $total / $qty : 0;
            $tier = $qty > 0 ? PricingTier::forPrice($avgUnitPrice) : null;

            $tax = $tier ? round($total * (float) $tier['tax_pct'] / 100, 2) : 0.0;
            $cost = $tier ? round((float) $tier['cost_price'] * $qty, 2) : 0.0;
            $net = round($total - $tax - $cost, 2);

            $rows[] = $order + [
                'tax' => $tax,
                'cost' => $cost,
                'net' => $net,
                'tier_min_price' => $tier['min_price'] ?? null,
            ];

            $totals['revenue'] += $total;
            $totals['tax'] += $tax;
            $totals['cost'] += $cost;
            $totals['net'] += $net;
            $totals['count']++;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
