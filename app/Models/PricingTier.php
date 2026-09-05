<?php

namespace App\Models;

use App\Core\Database;

/**
 * Tabela de precos por atacado (Fase 24): preco unitario e % de comissao do Licenciado variam
 * pela quantidade de placas no MESMO pedido (nao acumulado ao longo do tempo). Substitui o
 * esquema anterior de 2 precos fixos (Fase 23) -- esta e' a tabela real informada pelo usuario.
 */
class PricingTier
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM pricing_tiers ORDER BY min_qty ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pricing_tiers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** A faixa que se aplica a uma quantidade: a de maior min_qty que ainda seja <= $qty (ex:
     *  qty=7 cai na faixa min_qty=5, ate a proxima faixa min_qty=8 ser atingida). */
    public static function forQuantity(int $qty): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pricing_tiers WHERE min_qty <= :qty ORDER BY min_qty DESC LIMIT 1'
        );
        $stmt->execute(['qty' => $qty]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pricing_tiers (min_qty, unit_price, licenciado_commission_pct, cost_price, tax_pct)
             VALUES (:min_qty, :unit_price, :licenciado_commission_pct, :cost_price, :tax_pct)'
        );
        $stmt->execute(self::params($data));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pricing_tiers SET min_qty = :min_qty, unit_price = :unit_price,
                licenciado_commission_pct = :licenciado_commission_pct, cost_price = :cost_price, tax_pct = :tax_pct
             WHERE id = :id'
        );
        $stmt->execute(self::params($data) + ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM pricing_tiers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private static function params(array $data): array
    {
        return [
            'min_qty' => $data['min_qty'],
            'unit_price' => $data['unit_price'],
            'licenciado_commission_pct' => $data['licenciado_commission_pct'],
            'cost_price' => $data['cost_price'] ?: 0,
            'tax_pct' => $data['tax_pct'] ?: 0,
        ];
    }
}
