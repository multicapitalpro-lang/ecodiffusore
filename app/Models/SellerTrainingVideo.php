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

    public static function create(string $title, string $videoUrl): int
    {
        $db = Database::connection();
        $nextOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM seller_training_videos')->fetchColumn();

        $stmt = $db->prepare(
            'INSERT INTO seller_training_videos (title, video_url, sort_order) VALUES (:title, :video_url, :sort_order)'
        );
        $stmt->execute(['title' => $title, 'video_url' => $videoUrl, 'sort_order' => $nextOrder]);
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM seller_training_videos WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
