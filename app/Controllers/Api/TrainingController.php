<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Models\SellerTrainingModule;
use App\Models\SellerTrainingProgress;
use App\Models\SellerTrainingVideo;
use App\Models\User;

/** Treinamento obrigatorio do Vendedor pro app (Fase 85) -- mesmo conteudo de
 *  App\Controllers\SellerTrainingController::show()/reportProgress(), sem a logica de
 *  redirecionamento (o app decide se mostra a tela de treinamento a partir de
 *  user.must_complete_training, devolvido em /me e /login). */
class TrainingController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();

        $videos = SellerTrainingVideo::all();
        $modules = SellerTrainingModule::all();
        $progress = SellerTrainingProgress::forUser((int) $user['id']);

        $byModule = [];
        foreach ($videos as $v) {
            $byModule[(int) ($v['module_id'] ?? 0)][] = $v;
        }

        $groups = [];
        foreach ($modules as $m) {
            $groups[] = [
                'title' => $m['title'],
                'videos' => $this->videosWithProgress($byModule[$m['id']] ?? [], $progress),
            ];
        }
        if (!empty($byModule[0])) {
            $groups[] = ['title' => null, 'videos' => $this->videosWithProgress($byModule[0], $progress)];
        }

        ApiResponse::json([
            'groups' => $groups,
            'threshold_pct' => SellerTrainingProgress::COMPLETION_THRESHOLD_PCT,
            'all_completed' => SellerTrainingProgress::hasCompletedAll((int) $user['id']),
        ]);
    }

    public function reportProgress(): void
    {
        $user = ApiAuth::requireUser();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        $videoId = (int) ($body['video_id'] ?? 0);
        $percent = (float) ($body['percent'] ?? 0);
        if (!$videoId || !SellerTrainingVideo::find($videoId)) {
            ApiResponse::error('Video invalido.', 422);
        }

        SellerTrainingProgress::reportProgress((int) $user['id'], $videoId, $percent);

        $allCompleted = SellerTrainingProgress::hasCompletedAll((int) $user['id']);
        if ($allCompleted) {
            User::markTrainingCompleted((int) $user['id']);
        }

        ApiResponse::json(['all_completed' => $allCompleted]);
    }

    private function videosWithProgress(array $videos, array $progress): array
    {
        return array_map(function ($v) use ($progress) {
            $p = $progress[$v['id']] ?? null;
            return [
                'id' => (int) $v['id'],
                'title' => $v['title'],
                'video_url' => $v['video_url'],
                'percent_watched' => $p ? (float) $p['max_percent_watched'] : 0.0,
                'completed' => $p ? !empty($p['completed_at']) : false,
            ];
        }, $videos);
    }
}
