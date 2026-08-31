<?php

namespace App\Models;

use App\Core\Database;

class QuoteItem
{
    public static function forQuote(int $quoteId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT qi.*, p.name AS product_name
             FROM quote_items qi JOIN products p ON p.id = qi.product_id
             WHERE qi.quote_id = :quote_id'
        );
        $stmt->execute(['quote_id' => $quoteId]);
        return $stmt->fetchAll();
    }

    public static function create(int $quoteId, int $productId, int $quantity, float $unitPrice): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO quote_items (quote_id, product_id, quantity, unit_price, subtotal)
             VALUES (:quote_id, :product_id, :quantity, :unit_price, :subtotal)'
        );
        $stmt->execute([
            'quote_id' => $quoteId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $quantity,
        ]);
    }

    public static function deleteForQuote(int $quoteId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM quote_items WHERE quote_id = :quote_id');
        $stmt->execute(['quote_id' => $quoteId]);
    }
}
