<?php

namespace App\Core;

/** Fase 111: formatacao/conversao de valores monetarios pras calculadoras de economia/comissao
 *  quando o Licenciado (ou rede) tem uma segunda moeda configurada (users.secondary_currency --
 *  hoje so' Guarani paraguaio, PYG). Os motores de calculo (EconomyCalculator/PricingTier) SEMPRE
 *  trabalham em R$ internamente -- a conversao acontece so' na borda (valor digitado -> R$ antes
 *  de calcular, R$ -> moeda escolhida na hora de exibir). Guarani nao usa casas decimais na
 *  pratica (e' a convencao real da moeda), por isso format() arredonda pra PYG. */
class Money
{
    public static function format(float $brlValue, string $currency, float $rate): string
    {
        if ($currency === 'PYG' && $rate > 0) {
            return '₲ ' . number_format($brlValue * $rate, 0, ',', '.');
        }
        return 'R$ ' . number_format($brlValue, 2, ',', '.');
    }

    /** Converte um valor DIGITADO na moeda escolhida pro R$ interno -- usado antes de chamar
     *  EconomyCalculator::estimate() ou qualquer outro calculo que so' entende R$. */
    public static function toBrl(float $value, string $currency, float $rate): float
    {
        if ($currency === 'PYG' && $rate > 0) {
            return $value / $rate;
        }
        return $value;
    }

    /** Versao compacta ("₲ 2,4M"/"R$ 1,3k") -- usada no grafico de barras (App\Core\Chart::bar()),
     *  onde o valor precisa caber num espaco pequeno. Guarani usa milhoes bem mais cedo que R$
     *  (a moeda tem menos poder de compra por unidade), por isso o corte muda por moeda. */
    public static function compact(float $brlValue, string $currency, float $rate): string
    {
        if ($currency === 'PYG' && $rate > 0) {
            $pyg = $brlValue * $rate;
            $symbol = self::symbol($currency);
            if ($pyg >= 1000000) {
                return $symbol . ' ' . rtrim(rtrim(number_format($pyg / 1000000, 1, ',', '.'), '0'), ',') . 'M';
            }
            if ($pyg >= 1000) {
                return $symbol . ' ' . rtrim(rtrim(number_format($pyg / 1000, 1, ',', '.'), '0'), ',') . 'k';
            }
            return $symbol . ' ' . number_format($pyg, 0, ',', '.');
        }

        if ($brlValue >= 1000) {
            return 'R$ ' . rtrim(rtrim(number_format($brlValue / 1000, 1, ',', '.'), '0'), ',') . 'k';
        }
        return 'R$ ' . number_format($brlValue, 0, ',', '.');
    }

    public static function symbol(string $currency): string
    {
        return $currency === 'PYG' ? '₲' : 'R$';
    }

    public static function label(string $currency): string
    {
        return $currency === 'PYG' ? 'Guarani paraguaio (₲)' : 'Real (R$)';
    }
}
