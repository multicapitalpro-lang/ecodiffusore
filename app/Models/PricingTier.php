<?php

namespace App\Models;

use App\Core\Database;

/**
 * Faixas de preco negociavel (Fase 31): substitui a antiga tabela por quantidade (Fase 24) por
 * completo -- o preco unitario nao cai mais automaticamente por quantidade comprada; o
 * vendedor/licenciado NEGOCIA o preco unitario com o comprador (dentro de um piso minimo, hoje
 * R$3.450, bloqueado em validacao), e e' esse preco negociado que define a % de comissao do
 * Licenciado (licenciado_commission_pct), independente da quantidade de placas vendidas. Ver
 * forPrice() e App\Models\Commission::createCascadeForOrder().
 */
class PricingTier
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM pricing_tiers ORDER BY min_price ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pricing_tiers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** A faixa que cobre um preco unitario negociado: a de maior min_price que ainda seja <=
     *  $unitPrice E (sem max_price, ou max_price >= $unitPrice) -- null se o preco for menor que
     *  o piso da faixa mais baixa (nesse caso quem chama trata como "sem faixa", mesmo
     *  comportamento de piso bloqueado ja validado na criacao da proposta/pedido). */
    public static function forPrice(float $unitPrice): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pricing_tiers WHERE min_price <= :price AND (max_price IS NULL OR max_price >= :price2)
             ORDER BY min_price DESC LIMIT 1'
        );
        $stmt->execute(['price' => $unitPrice, 'price2' => $unitPrice]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pricing_tiers (min_price, max_price, licenciado_commission_pct, cost_price, tax_pct)
             VALUES (:min_price, :max_price, :licenciado_commission_pct, :cost_price, :tax_pct)'
        );
        $stmt->execute(self::params($data));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pricing_tiers SET min_price = :min_price, max_price = :max_price,
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
            'min_price' => $data['min_price'],
            'max_price' => ($data['max_price'] ?? '') !== '' ? $data['max_price'] : null,
            'licenciado_commission_pct' => $data['licenciado_commission_pct'],
            'cost_price' => $data['cost_price'] ?: 0,
            'tax_pct' => $data['tax_pct'] ?: 0,
        ];
    }
}
