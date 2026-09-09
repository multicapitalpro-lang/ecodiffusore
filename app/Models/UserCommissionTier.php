<?php

namespace App\Models;

use App\Core\Database;

/**
 * Comissao do Vendedor por faixa de preco negociado (pricing_tiers, Fase 31 -- faixa de
 * quantidade ate a Fase 24), definida pelo Licenciado no cadastro do proprio Vendedor. Uma linha
 * por faixa configurada -- faixa sem linha fica sem valor definido pra ela
 * (Commission::createCascadeForOrder cai no fallback antigo).
 */
class UserCommissionTier
{
    /** [pricing_tier_id => valor] */
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT pricing_tier_id, value FROM user_commission_tiers WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['pricing_tier_id']] = (float) $row['value'];
        }
        return $out;
    }

    public static function valueFor(int $userId, int $pricingTierId): ?float
    {
        $stmt = Database::connection()->prepare(
            'SELECT value FROM user_commission_tiers WHERE user_id = :uid AND pricing_tier_id = :tid'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $pricingTierId]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (float) $value : null;
    }

    /** Substitui todas as faixas configuradas do usuario de uma vez -- $values: [pricing_tier_id => float|null] */
    public static function setForUser(int $userId, array $values): void
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $db->prepare('DELETE FROM user_commission_tiers WHERE user_id = :uid')->execute(['uid' => $userId]);

            $stmt = $db->prepare('INSERT INTO user_commission_tiers (user_id, pricing_tier_id, value) VALUES (:uid, :tid, :val)');
            foreach ($values as $tierId => $value) {
                if ($value === null) {
                    continue;
                }
                $stmt->execute(['uid' => $userId, 'tid' => $tierId, 'val' => $value]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
