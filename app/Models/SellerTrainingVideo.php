<?php

namespace App\Models;

use App\Core\Database;

/** Videos de treinamento obrigatorio pro Vendedor (Fase 42) -- admin/gerente cadastra (mesmo
 *  escopo de gestao de conteudo nacional do TutorialVideo, Roles::SUPERVISOR_ASSIGNMENT), so
 *  aceita arquivo direto (mp4/webm), nao embed do YouTube -- o progresso real de reproducao e'
 *  rastreado no lado do cliente via evento nativo do <video>, e um embed de terceiro nao expoe
 *  isso sem a API do YouTube (fora de escopo aqui). Tabela separada de tutorial_videos de
 *  proposito: publico diferente (Vendedor recem-cadastrado x comprador) e semantica diferente
 *  (bloqueia acesso x so informativo). */
class SellerTrainingVideo
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM seller_training_videos ORDER BY sort_order, id')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM seller_training_videos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** $moduleId null = video "solto", fora de qualquer modulo (Fase 83). sort_order e' por
     *  modulo (cada bucket tem sua propria sequencia 1..N), nao global. */
    public static function create(string $title, string $videoUrl, ?int $moduleId = null): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM seller_training_videos WHERE module_id <=> :module_id');
        $stmt->execute(['module_id' => $moduleId]);
        $nextOrder = (int) $stmt->fetchColumn();

        $stmt = $db->prepare(
            'INSERT INTO seller_training_videos (title, video_url, module_id, sort_order) VALUES (:title, :video_url, :module_id, :sort_order)'
        );
        $stmt->execute(['title' => $title, 'video_url' => $videoUrl, 'module_id' => $moduleId, 'sort_order' => $nextOrder]);
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM seller_training_videos WHERE id = :id');
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

    /** Troca de posicao so' com o vizinho do MESMO modulo (<=> trata NULL=NULL como igual, pra
     *  nao misturar a sequencia de um modulo com a de outro nem com os videos soltos). */
    private static function swap(int $id, string $operator, string $direction): void
    {
        $db = Database::connection();
        $current = self::find($id);
        if (!$current) {
            return;
        }

        $stmt = $db->prepare(
            "SELECT id, sort_order FROM seller_training_videos
             WHERE module_id <=> :module_id AND sort_order {$operator} :sort
             ORDER BY sort_order {$direction} LIMIT 1"
        );
        $stmt->execute(['module_id' => $current['module_id'], 'sort' => $current['sort_order']]);
        $neighbor = $stmt->fetch();
        if (!$neighbor) {
            return;
        }

        $db->prepare('UPDATE seller_training_videos SET sort_order = :sort WHERE id = :id')
            ->execute(['sort' => $neighbor['sort_order'], 'id' => $current['id']]);
        $db->prepare('UPDATE seller_training_videos SET sort_order = :sort WHERE id = :id')
            ->execute(['sort' => $current['sort_order'], 'id' => $neighbor['id']]);
    }
}
