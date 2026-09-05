<?php

namespace App\Models;

use App\Core\Database;

class Product
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM products';
        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name';

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    /** Casa a marca informada pelo cliente (ex: "Scania") contra o nome do produto (ex: "Linha Scania (até 2018)") */
    public static function findByBrandKeyword(string $brand): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM products WHERE active = 1 AND name LIKE :pattern ORDER BY price_cash LIMIT 1"
        );
        $stmt->execute(['pattern' => '%' . $brand . '%']);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    /** Fallback quando nao acha produto pela marca informada -- mostra o mais barato como "a partir de" */
    public static function cheapest(): ?array
    {
        $stmt = Database::connection()->query('SELECT * FROM products WHERE active = 1 ORDER BY price_cash LIMIT 1');
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO products (sku, name, price_cash, price_installment, cost_price, active)
             VALUES (:sku, :name, :price_cash, :price_installment, :cost_price, :active)'
        );
        $stmt->execute([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'price_cash' => $data['price_cash'],
            'price_installment' => $data['price_installment'],
            'cost_price' => $data['cost_price'] ?: 0,
            'active' => !empty($data['active']) ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE products SET sku = :sku, name = :name, price_cash = :price_cash,
                price_installment = :price_installment, cost_price = :cost_price, active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'price_cash' => $data['price_cash'],
            'price_installment' => $data['price_installment'],
            'cost_price' => $data['cost_price'] ?: 0,
            'active' => !empty($data['active']) ? 1 : 0,
        ]);
    }
}
