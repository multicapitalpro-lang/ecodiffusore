<?php

namespace App\Models;

use App\Core\Database;

/** Videos tutoriais (instalacao, teste da chave etc.) mostrados no painel do comprador --
 *  Admin/Gerente cadastra o link (embed do YouTube ou URL direta de .mp4/.webm), o cliente ve em
 *  /painel/tutoriais. Comeca vazio de proposito -- sem video real cadastrado ainda (mesmo
 *  tratamento ja dado a logo/video pendente da landing page). */
class TutorialVideo
{
    public static function all(): array
    {
        return Database::connection()->query('SELECT * FROM tutorial_videos ORDER BY sort_order, id')->fetchAll();
    }

    public static function create(string $title, string $videoUrl, ?string $description): int
    {
        $db = Database::connection();
        $nextOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tutorial_videos')->fetchColumn();

        $stmt = $db->prepare(
            'INSERT INTO tutorial_videos (title, video_url, description, sort_order) VALUES (:title, :video_url, :description, :sort_order)'
        );
        $stmt->execute([
            'title' => $title,
            'video_url' => $videoUrl,
            'description' => $description ?: null,
            'sort_order' => $nextOrder,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM tutorial_videos WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
