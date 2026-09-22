<?php

namespace App\Models;

use App\Core\Database;

/** Modulo de organizacao do treinamento obrigatorio do Vendedor (Fase 83) -- agrupa videos de
 *  seller_training_videos (module_id) numa sequencia, definida pelo admin/gerente com botoes
 *  subir/descer (mesmo padrao simples usado em SellerTrainingVideo::moveUp/moveDown). */
class SellerTrainingModule
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM seller_training_modules ORDER BY sort_order, id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM seller_training_modules WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $title): int
    {
        $db = Database::connection();
        $nextOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM seller_training_modules')->fetchColumn();

        $stmt = $db->prepare('INSERT INTO seller_training_modules (title, sort_order) VALUES (:title, :sort_order)');
        $stmt->execute(['title' => $title, 'sort_order' => $nextOrder]);
        return (int) $db->lastInsertId();
    }

    /** Videos dentro do modulo excluido viram "sem modulo" (module_id NULL) via ON DELETE SET NULL
     *  -- nunca some conteudo ja cadastrado so por causa da organizacao. */
    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM seller_training_modules WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function moveUp(int $id): void
    {
        self::swap($id, '<', 'DESC');
    }

    public static function moveDown(int $id): void
    {
        self::swap($id, '>', 'ASC');
    }

    private static function swap(int $id, string $operator, string $direction): void
    {
        $db = Database::connection();
        $current = self::find($id);
        if (!$current) {
            return;
        }

        $stmt = $db->prepare(
            "SELECT id, sort_order FROM seller_training_modules WHERE sort_order {$operator} :sort ORDER BY sort_order {$direction} LIMIT 1"
        );
        $stmt->execute(['sort' => $current['sort_order']]);
        $neighbor = $stmt->fetch();
        if (!$neighbor) {
            return;
        }

        $db->prepare('UPDATE seller_training_modules SET sort_order = :sort WHERE id = :id')
            ->execute(['sort' => $neighbor['sort_order'], 'id' => $current['id']]);
        $db->prepare('UPDATE seller_training_modules SET sort_order = :sort WHERE id = :id')
            ->execute(['sort' => $current['sort_order'], 'id' => $neighbor['id']]);
    }
}
