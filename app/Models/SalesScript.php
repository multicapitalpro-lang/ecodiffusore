<?php

namespace App\Models;

use App\Core\Database;

/** Biblioteca de respostas prontas pra objecoes comuns de venda -- Admin/Gerente mantem, todo
 *  STAFF ve e manda por WhatsApp direto da tela de Materiais de Venda. */
class SalesScript
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM sales_scripts ORDER BY sort_order, id')->fetchAll();
    }

    public static function create(string $title, string $responseText): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO sales_scripts (title, response_text, sort_order) VALUES (:title, :text, :sort)'
        );
        $maxOrder = (int) Database::connection()->query('SELECT COALESCE(MAX(sort_order), -1) FROM sales_scripts')->fetchColumn();
        $stmt->execute(['title' => $title, 'text' => $responseText, 'sort' => $maxOrder + 1]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM sales_scripts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
