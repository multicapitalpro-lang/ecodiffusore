<?php

namespace App\Models;

use App\Core\Database;

/** Progresso real de reproducao do treinamento obrigatorio (Fase 42) -- percentual maximo ja
 *  assistido de cada video, reportado pelo evento nativo `timeupdate` do <video> no navegador
 *  (ver seller_training_videos.php). Video considerado concluido a partir de 90% assistido
 *  (pedido explicito do usuario: rastrear progresso real do player, nao so' "marcar como
 *  assistido"). */
class SellerTrainingProgress
{
    public const COMPLETION_THRESHOLD_PCT = 90.0;

    /** @return array<int,array> indexado por video_id */
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM seller_training_progress WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        $byVideo = [];
        foreach ($stmt->fetchAll() as $row) {
            $byVideo[(int) $row['video_id']] = $row;
        }
        return $byVideo;
    }

    /** Grava o percentual reportado so' se for MAIOR que o ja registrado -- nunca deixa o
     *  progresso regredir (ex: usuario voltou o video pra revisar uma parte). Marca
     *  completed_at na primeira vez que cruza o limiar de conclusao. */
    public static function reportProgress(int $userId, int $videoId, float $percentWatched): void
    {
        $percentWatched = max(0.0, min(100.0, $percentWatched));
        $completed = $percentWatched >= self::COMPLETION_THRESHOLD_PCT;

        $stmt = Database::connection()->prepare(
            'INSERT INTO seller_training_progress (user_id, video_id, max_percent_watched, completed_at)
             VALUES (:user_id, :video_id, :percent, :completed_at)
             ON DUPLICATE KEY UPDATE
                max_percent_watched = GREATEST(max_percent_watched, VALUES(max_percent_watched)),
                completed_at = COALESCE(completed_at, VALUES(completed_at))'
        );
        $stmt->execute([
            'user_id' => $userId,
            'video_id' => $videoId,
            'percent' => $percentWatched,
            'completed_at' => $completed ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /** Todo video ativo precisa estar concluido (completed_at preenchido) -- se nao existe
     *  nenhum video cadastrado ainda, considera concluido (nao trava o Vendedor por falta de
     *  conteudo, responsabilidade de manter video cadastrado e' do admin/gerente). */
    public static function hasCompletedAll(int $userId): bool
    {
        $totalVideos = (int) Database::connection()->query('SELECT COUNT(*) FROM seller_training_videos')->fetchColumn();
        if ($totalVideos === 0) {
            return true;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM seller_training_progress WHERE user_id = :user_id AND completed_at IS NOT NULL'
        );
        $stmt->execute(['user_id' => $userId]);
        $completedVideos = (int) $stmt->fetchColumn();

        return $completedVideos >= $totalVideos;
    }
}
