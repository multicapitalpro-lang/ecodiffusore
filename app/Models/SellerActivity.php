<?php

namespace App\Models;

use App\Core\Database;

/**
 * Ultima atividade de venda de cada Vendedor -- pedido/orcamento mexido OU nota de lead escrita
 * (ver LeadNote, Fase 50), o que for mais recente. Usado pro Licenciado/Gestor enxergarem quem da
 * equipe esfriou antes que vire um problema maior (mesmo espirito do aviso de lead esfriando, so
 * que olhando pro Vendedor em vez do Lead).
 */
class SellerActivity
{
    public const INACTIVITY_DAYS = 10;

    /** @return array<int, string|null> seller_id => data/hora da ultima atividade, null se nunca teve nenhuma */
    public static function lastActivity(array $sellerIds): array
    {
        $sellerIds = array_values(array_unique(array_map('intval', $sellerIds)));
        if (!$sellerIds) {
            return [];
        }

        $result = array_fill_keys($sellerIds, null);
        $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
        $db = Database::connection();

        $sources = [
            "SELECT seller_id, MAX(updated_at) AS last_at FROM orders WHERE seller_id IN ({$placeholders}) GROUP BY seller_id",
            "SELECT seller_id, MAX(updated_at) AS last_at FROM quotes WHERE seller_id IN ({$placeholders}) GROUP BY seller_id",
            "SELECT user_id AS seller_id, MAX(created_at) AS last_at FROM lead_notes WHERE user_id IN ({$placeholders}) GROUP BY user_id",
        ];

        foreach ($sources as $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute($sellerIds);
            foreach ($stmt->fetchAll() as $row) {
                $sid = (int) $row['seller_id'];
                if ($result[$sid] === null || $row['last_at'] > $result[$sid]) {
                    $result[$sid] = $row['last_at'];
                }
            }
        }

        return $result;
    }

    /**
     * Vendedores (com id/name/created_at) sem atividade ha INACTIVITY_DAYS dias ou mais -- quem
     * nunca teve nenhuma atividade usa a data de criacao da conta como ponto de partida, pra dar
     * um prazo de adaptacao em vez de flagar no primeiro dia.
     * @param array $sellers linhas de User (precisam de id/created_at)
     * @return array<int, array{id:int, name:string, last_activity:?string, days_inactive:int}>
     */
    public static function inactiveAmong(array $sellers): array
    {
        $ids = array_map(fn ($s) => (int) $s['id'], $sellers);
        $lastActivity = self::lastActivity($ids);
        $now = time();

        $inactive = [];
        foreach ($sellers as $seller) {
            $id = (int) $seller['id'];
            $baseline = $lastActivity[$id] ?? $seller['created_at'];
            $daysInactive = (int) floor(($now - strtotime($baseline)) / 86400);

            if ($daysInactive >= self::INACTIVITY_DAYS) {
                $inactive[] = [
                    'id' => $id,
                    'name' => $seller['name'],
                    'last_activity' => $lastActivity[$id] ?? null,
                    'days_inactive' => $daysInactive,
                ];
            }
        }

        return $inactive;
    }
}
