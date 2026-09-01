<?php

namespace App\Core;

/**
 * Calcula o preco cobrado no cartao (checkout publico) de forma que a Ecodiffusore receba o preco
 * de tabela liquido, depois de todas as taxas da Asaas saírem. Pix/Boleto nao usam essa classe --
 * sao cobrados pelo preco de tabela puro.
 *
 * Taxas informadas pelo cliente (conferir contra o extrato real da Asaas na primeira venda
 * parcelada e ajustar as constantes abaixo se nao bater):
 * - 2,99% + R$0,49 por venda no cartao (sempre).
 * - 1,7% ao mes de antecipacao, so quando parcelado (>1x). Media de meses antecipados usada aqui:
 *   (N + 1) / 2 -- parcela 1 vence em ~1 mes, parcela N em ~N meses.
 */
class CardPricing
{
    private const CARD_FEE_PCT = 0.0299;
    private const ANTICIPATION_MONTHLY_PCT = 0.017;
    private const FIXED_FEE = 0.49;

    public static function averageAnticipationMonths(int $installments): float
    {
        return $installments <= 1 ? 0.0 : ($installments + 1) / 2;
    }

    public static function feePercent(int $installments): float
    {
        return self::CARD_FEE_PCT + self::ANTICIPATION_MONTHLY_PCT * self::averageAnticipationMonths($installments);
    }

    public static function chargeAmount(float $basePrice, int $installments): float
    {
        $installments = max(1, $installments);
        $feePct = self::feePercent($installments);

        return round(($basePrice + self::FIXED_FEE) / (1 - $feePct), 2);
    }

    public static function installmentValue(float $basePrice, int $installments): float
    {
        $installments = max(1, $installments);
        return round(self::chargeAmount($basePrice, $installments) / $installments, 2);
    }
}
