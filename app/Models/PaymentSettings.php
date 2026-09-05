<?php

namespace App\Models;

use App\Core\Database;

/**
 * Configuracao unica (1 linha so) das taxas de cartao/antecipacao repassadas ao cliente final
 * (Fase 26) -- editavel em /painel/configuracoes/pagamento, sem precisar mexer em codigo. Valores
 * padrao sao as taxas CHEIAS (regulares) da Asaas, nao as promocionais validas so por 3 meses.
 */
class PaymentSettings
{
    public static function current(): array
    {
        $row = Database::connection()->query('SELECT * FROM payment_settings WHERE id = 1')->fetch();

        return $row ?: [
            'card_fee_avista_pct' => 2.99,
            'card_fee_parcelado_pct' => 3.49,
            'card_fixed_fee' => 0.49,
            'antecipacao_avista_mensal_pct' => 1.25,
            'antecipacao_parcelado_mensal_pct' => 1.70,
            'max_installments' => 6,
            'pix_fee' => 1.99,
            'boleto_fee' => 1.99,
        ];
    }

    public static function update(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE payment_settings SET
                card_fee_avista_pct = :card_fee_avista_pct,
                card_fee_parcelado_pct = :card_fee_parcelado_pct,
                card_fixed_fee = :card_fixed_fee,
                antecipacao_avista_mensal_pct = :antecipacao_avista_mensal_pct,
                antecipacao_parcelado_mensal_pct = :antecipacao_parcelado_mensal_pct,
                max_installments = :max_installments,
                pix_fee = :pix_fee,
                boleto_fee = :boleto_fee
             WHERE id = 1'
        );
        $stmt->execute([
            'card_fee_avista_pct' => $data['card_fee_avista_pct'],
            'card_fee_parcelado_pct' => $data['card_fee_parcelado_pct'],
            'card_fixed_fee' => $data['card_fixed_fee'],
            'antecipacao_avista_mensal_pct' => $data['antecipacao_avista_mensal_pct'],
            'antecipacao_parcelado_mensal_pct' => $data['antecipacao_parcelado_mensal_pct'],
            'max_installments' => $data['max_installments'],
            'pix_fee' => $data['pix_fee'],
            'boleto_fee' => $data['boleto_fee'],
        ]);
    }
}
