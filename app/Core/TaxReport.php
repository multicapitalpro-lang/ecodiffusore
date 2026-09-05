<?php

namespace App\Core;

use App\Models\PricingTier;

/**
 * Imposto e custo real por pedido (Fase 25) -- usa a mesma tabela de precos por quantidade
 * (PricingTier, Fase 24) que ja define a comissao do Licenciado: cada pedido cai numa faixa pela
 * quantidade total de placas, e essa faixa tem tax_pct (% sobre o valor do pedido) e cost_price
 * (custo por UNIDADE -- multiplicado pela quantidade, ao contrario da comissao que e sempre %).
 * So considera pedidos com status='verificado' (venda confirmada) -- pendente/cancelado nao gera
 * imposto devido de verdade.
 */
class TaxReport
{
    /** @param array $orders Order::all(['status' => 'verificado', ...]) -- precisa do campo total_qty */
    public static function forOrders(array $orders): array
    {
        $tiers = PricingTier::all();

        $rows = [];
        $totals = ['revenue' => 0.0, 'tax' => 0.0, 'cost' => 0.0, 'net' => 0.0, 'count' => 0];

        foreach ($orders as $order) {
            $qty = (int) ($order['total_qty'] ?? 0);
            $total = (float) $order['total_value'];
            $tier = self::tierForQty($tiers, $qty);

            $tax = $tier ? round($total * (float) $tier['tax_pct'] / 100, 2) : 0.0;
            $cost = $tier ? round((float) $tier['cost_price'] * $qty, 2) : 0.0;
            $net = round($total - $tax - $cost, 2);

            $rows[] = $order + [
                'tax' => $tax,
                'cost' => $cost,
                'net' => $net,
                'tier_min_qty' => $tier['min_qty'] ?? null,
            ];

            $totals['revenue'] += $total;
            $totals['tax'] += $tax;
            $totals['cost'] += $cost;
            $totals['net'] += $net;
            $totals['count']++;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** A mesma logica de "faixa por quantidade" usada em Commission/painel.js -- maior min_qty
     *  que ainda seja <= a quantidade. */
    private static function tierForQty(array $tiers, int $qty): ?array
    {
        $match = null;
        foreach ($tiers as $t) {
            if ((int) $t['min_qty'] <= $qty && (!$match || (int) $t['min_qty'] > (int) $match['min_qty'])) {
                $match = $t;
            }
        }
        return $match;
    }
}
