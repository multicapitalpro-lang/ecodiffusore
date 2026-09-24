<?php

namespace App\Models;

use App\Core\Database;

/** Fase 111: cache da cotacao de cambio (par de moedas, ex: 'BRL_PYG') -- 1 linha por par,
 *  atualizada pelo App\Core\ExchangeRateClient. */
class ExchangeRate
{
    public static function find(string $pair): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM exchange_rates WHERE pair = :pair');
        $stmt->execute(['pair' => $pair]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function upsert(string $pair, float $rate): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO exchange_rates (pair, rate, fetched_at) VALUES (:pair, :rate, NOW())
             ON DUPLICATE KEY UPDATE rate = :rate_update, fetched_at = NOW()'
        );
        $stmt->execute(['pair' => $pair, 'rate' => $rate, 'rate_update' => $rate]);
    }
}
