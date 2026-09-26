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

    /** Fase 86: a assinatura cobre o Licenciado + ate esta quantidade de colaboradores (Gestor +
     *  Vendedor, somados) sem custo adicional -- acima disso, cada vaga extra e' cobrada por fora
     *  (ver SEAT_PRICES). Valor informado pelo usuario. */
    public const INCLUDED_SEATS = 5;

    /** Preco por VAGA extra (1 colaborador a mais que INCLUDED_SEATS), no mesmo formato de
     *  periodo/desconto da assinatura base -- mesma proporcao de desconto semestral/anual de
     *  PRICES, aplicada sobre o valor mensal informado pelo usuario (R$25). */
    public const SEAT_PRICES = [
        'mensal' => 25.00,
        'semestral' => 100.00,
        'anual' => 167.00,
    ];

    /** Fase 138: pacotes de credito pre-pago da ferramenta "Nota Fiscal Automatica" -- R$3,00 por
     *  nota unitario (valor de exemplo dado pelo usuario), com desconto crescente nos pacotes
     *  maiores pro mesmo espirito de incentivo dos planos semestral/anual acima. */
    public const NFE_CREDIT_PACKAGES = [
        10 => 30.00,
        25 => 70.00,
        50 => 130.00,
    ];

    /** % de desconto comparado a pagar nota por nota a R$3,00. */
    public static function nfeCreditDiscountPct(int $quantity): float
    {
        if (!isset(self::NFE_CREDIT_PACKAGES[$quantity])) {
            return 0.0;
        }
        $unitPrice = 3.00;
        $fullPrice = $unitPrice * $quantity;
        return round((1 - self::NFE_CREDIT_PACKAGES[$quantity] / $fullPrice) * 100);
    }

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

    /** Mesmo calculo de discountPct(), pro preco da VAGA EXTRA (por unidade). */
    public static function seatDiscountPct(string $plan): float
    {
        if ($plan === 'mensal') {
            return 0.0;
        }
        $fullPrice = self::SEAT_PRICES['mensal'] * self::MONTHS[$plan];
        return round((1 - self::SEAT_PRICES[$plan] / $fullPrice) * 100);
    }
}
