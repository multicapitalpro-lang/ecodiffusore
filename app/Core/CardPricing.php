<?php

namespace App\Core;

use App\Models\PaymentSettings;

/**
 * Calcula o preco cobrado no cartao (Fase 26) de forma que a Ecodiffusore receba o preco de
 * tabela liquido, depois de TODAS as taxas da Asaas saírem -- inclusive a de antecipacao, que
 * agora e' repassada ao cliente tanto na compra a vista quanto parcelada (antes so parcelado
 * embutia antecipacao). Pix/Boleto nao usam essa classe -- sao cobrados pelo preco de tabela puro
 * (a taxa fixa por transacao da Asaas nesses dois meios fica por conta da empresa, nao repassada).
 *
 * Taxas configuraveis em /painel/configuracoes/pagamento (App\Models\PaymentSettings), sem
 * precisar mexer em codigo:
 * - Cartao a vista (1x): taxa de cartao (~2,99%) + 1 mes de antecipacao (~1,25%) -- o recebimento
 *   normal do cartao a vista pela Asaas leva ~1 mes; antecipar pra girar estoque custa esse 1,25%
 *   uma vez so (nao ha parcela seguinte pra "esperar mais", entao nao ha media de meses aqui).
 * - Cartao parcelado (2x a max_installments): taxa de cartao parcelado (~3,49%) + antecipacao
 *   parcelado (~1,70%) x media de meses antecipados -- parcela 1 vence em ~1 mes, parcela N em ~N
 *   meses, media = (1+N)/2 (mesma logica de antes, so a taxa mensal e as constantes que mudaram).
 * - R$0,49 fixo por venda, sempre.
 */
class CardPricing
{
    public static function maxInstallments(): int
    {
        return (int) (PaymentSettings::current()['max_installments'] ?? 6);
    }

    /** Media de meses "esperados" pra receber o valor total -- usada so como referencia/debug,
     *  o calculo de fato mora em feePercent() (a vista e parcelado tem taxas mensais diferentes,
     *  entao nao da mais pra multiplicar uma unica taxa por essa media em ambos os casos). */
    public static function averageAnticipationMonths(int $installments): float
    {
        return $installments <= 1 ? 1.0 : ($installments + 1) / 2;
    }

    public static function feePercent(int $installments): float
    {
        $installments = max(1, min(self::maxInstallments(), $installments));
        $settings = PaymentSettings::current();

        if ($installments <= 1) {
            return (float) $settings['card_fee_avista_pct'] / 100
                + (float) $settings['antecipacao_avista_mensal_pct'] / 100;
        }

        $mesesMedios = ($installments + 1) / 2;

        return (float) $settings['card_fee_parcelado_pct'] / 100
            + (float) $settings['antecipacao_parcelado_mensal_pct'] / 100 * $mesesMedios;
    }

    public static function chargeAmount(float $basePrice, int $installments): float
    {
        $installments = max(1, min(self::maxInstallments(), $installments));
        $feePct = self::feePercent($installments);
        $fixedFee = (float) PaymentSettings::current()['card_fixed_fee'];

        return round(($basePrice + $fixedFee) / (1 - $feePct), 2);
    }

    public static function installmentValue(float $basePrice, int $installments): float
    {
        $installments = max(1, min(self::maxInstallments(), $installments));

        return round(self::chargeAmount($basePrice, $installments) / $installments, 2);
    }
}
