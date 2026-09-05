<?php

namespace App\Models;

use App\Core\Database;

/**
 * Configuracao unica (1 linha so) da emissao automatica de NF-e por pedido pago (Fase 27) --
 * editavel em /painel/configuracoes/nfe. "enabled" comeca sempre em 0: emitir nota fiscal e uma
 * acao com efeito fiscal real, so deve ligar depois de configurar o servico municipal certo e
 * confirmar as aliquotas com o contador.
 */
class NfeSettings
{
    public static function current(): array
    {
        $row = Database::connection()->query('SELECT * FROM nfe_settings WHERE id = 1')->fetch();

        return $row ?: [
            'enabled' => 0,
            'municipal_service_id' => null,
            'municipal_service_description' => null,
            'iss_pct' => 0,
            'retain_iss' => 0,
            'cofins_pct' => 0,
            'csll_pct' => 0,
            'inss_pct' => 0,
            'ir_pct' => 0,
            'pis_pct' => 0,
            'service_description_template' => 'Venda de dispositivo Ecodiffusore -- Pedido #{pedido}',
            'observations_template' => null,
        ];
    }

    public static function update(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE nfe_settings SET
                enabled = :enabled,
                municipal_service_id = :municipal_service_id,
                municipal_service_description = :municipal_service_description,
                iss_pct = :iss_pct,
                retain_iss = :retain_iss,
                cofins_pct = :cofins_pct,
                csll_pct = :csll_pct,
                inss_pct = :inss_pct,
                ir_pct = :ir_pct,
                pis_pct = :pis_pct,
                service_description_template = :service_description_template,
                observations_template = :observations_template
             WHERE id = 1'
        );
        $stmt->execute([
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'municipal_service_id' => $data['municipal_service_id'] ?: null,
            'municipal_service_description' => $data['municipal_service_description'] ?: null,
            'iss_pct' => $data['iss_pct'],
            'retain_iss' => !empty($data['retain_iss']) ? 1 : 0,
            'cofins_pct' => $data['cofins_pct'],
            'csll_pct' => $data['csll_pct'],
            'inss_pct' => $data['inss_pct'],
            'ir_pct' => $data['ir_pct'],
            'pis_pct' => $data['pis_pct'],
            'service_description_template' => $data['service_description_template'] ?: null,
            'observations_template' => $data['observations_template'] ?: null,
        ]);
    }
}
