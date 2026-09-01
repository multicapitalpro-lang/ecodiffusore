<?php

namespace App\Core;

/**
 * Calcula o payback do orcamento por placa a partir do gasto mensal com diesel (km/litro vem do
 * proprio usuario, mais preciso que o 2.8 fixo usado na calculadora da landing page). Faixas
 * (5%/8%/12%) sao especificas desta tela -- a calculadora da LP usa faixas proprias (5%/10%/20%),
 * decisao deliberada do usuario de manter os dois calculadores independentes.
 */
class EconomyCalculator
{
    private const TIERS = ['min' => 0.05, 'avg' => 0.08, 'max' => 0.12];

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
