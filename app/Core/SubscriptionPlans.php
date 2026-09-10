<?php

namespace App\Core;

/**
 * Precos da assinatura do Licenciado (Fase 32) -- valores informados pelo usuario. Centralizados
 * aqui (mesmo espirito de App\Core\CardPricing) pra facilitar reajuste futuro sem tocar em
 * controller/model. Sem tela de admin pra editar por enquanto -- nao foi pedido.
 */
class SubscriptionPlans
{
    public const PRICES = [
        'mensal' => 150.00,
        'semestral' => 600.00,
        'anual' => 1000.00,
    ];

    public const MONTHS = [
        'mensal' => 1,
        'semestral' => 6,
        'anual' => 12,
    ];

    public const LABELS = [
        'mensal' => 'Mensal',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
    ];

    /** % de desconto comparado a pagar o plano mensal repetidamente pelo mesmo periodo. */
    public static function discountPct(string $plan): float
    {
        if ($plan === 'mensal') {
            return 0.0;
        }
        $fullPrice = self::PRICES['mensal'] * self::MONTHS[$plan];
        return round((1 - self::PRICES[$plan] / $fullPrice) * 100);
    }

    public static function monthlyEquivalent(string $plan): float
    {
        return round(self::PRICES[$plan] / self::MONTHS[$plan], 2);
    }

    public static function isValid(string $plan): bool
    {
        return isset(self::PRICES[$plan]);
    }
}
