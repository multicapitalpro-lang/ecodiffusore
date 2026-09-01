<?php

namespace App\Core;

/**
 * Mesma logica de faixas de economia da calculadora da landing page (5%/10%/20% do gasto mensal
 * com diesel), reaproveitada no servidor pra calcular o payback do orcamento por placa. Aqui o
 * km/litro vem do proprio usuario (mais preciso que o 2.8 fixo usado na calculadora da LP).
 */
class EconomyCalculator
{
    private const TIERS = ['min' => 0.05, 'avg' => 0.10, 'max' => 0.20];

    public static function estimate(float $kmMensal, float $kmPorLitro, float $precoDiesel, float $productPrice): array
    {
        $gastoMensal = $kmPorLitro > 0 ? ($kmMensal / $kmPorLitro) * $precoDiesel : 0.0;

        $tiers = [];
        foreach (self::TIERS as $key => $pct) {
            $monthly = $gastoMensal * $pct;
            $tiers[$key] = [
                'pct' => $pct,
                'monthly' => $monthly,
                'yearly' => $monthly * 12,
                'five_year' => $monthly * 12 * 5,
            ];
        }

        $avgMonthly = $tiers['avg']['monthly'];
        $paybackMonths = $avgMonthly > 0 ? $productPrice / $avgMonthly : null;

        $yearlyBreakdown = [];
        for ($year = 1; $year <= 5; $year++) {
            $cumulative = $avgMonthly * 12 * $year;
            $yearlyBreakdown[] = [
                'year' => $year,
                'cumulative_savings' => $cumulative,
                'net_gain' => $cumulative - $productPrice,
                'payback_reached' => $cumulative >= $productPrice,
            ];
        }

        return [
            'gasto_mensal' => $gastoMensal,
            'tiers' => $tiers,
            'payback_months' => $paybackMonths,
            'yearly_breakdown' => $yearlyBreakdown,
        ];
    }
}
