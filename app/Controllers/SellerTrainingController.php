<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\SellerTrainingModule;
use App\Models\SellerTrainingProgress;
use App\Models\SellerTrainingVideo;
use App\Models\User;

/** Treinamento obrigatorio do Vendedor recem-cadastrado (Fase 42) -- pre-tela que precisa ser
 *  concluida (>=90% de cada video assistido de verdade, ver SellerTrainingProgress) antes do
 *  acesso completo ao painel ser liberado. O bloqueio de verdade fica em Auth::requireRole()
 *  (central, cobre qualquer rota de funcao); esta tela em si so' usa Auth::requireLogin(), pra
 *  ficar sempre acessivel enquanto o Vendedor esta preso no gate. */
class SellerTrainingController
{
    public function show(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== Roles::SELLER) {
            Router::redirect('/painel');
        }

        if (!empty($user['training_completed_at'])) {
            Router::redirect('/painel');
        }

        $videos = SellerTrainingVideo::all();
        if (!$videos) {
            // Nenhum video cadastrado ainda -- nao ha' o que assistir, entao nao faz sentido
            // travar o Vendedor numa tela vazia sem saida (mesma logica de
            // SellerTrainingProgress::hasCompletedAll() aplicada aqui na entrada do gate).
            User::markTrainingCompleted((int) $user['id']);
            Router::redirect('/painel');
        }

        // Fase 83: agrupa por modulo pra exibir em blocos, na sequencia definida pelo admin/
        // gerente -- videos sem modulo (module_id null) caem no bucket 0, mostrado por ultimo.
        $modules = SellerTrainingModule::all();
        $byModule = [];
        foreach ($videos as $v) {
            $byModule[(int) ($v['module_id'] ?? 0)][] = $v;
        }

        View::render('painel/training/show', [
            'user' => $user,
            'modules' => $modules,
            'byModule' => $byModule,
            'progress' => SellerTrainingProgress::forUser((int) $user['id']),
        ], null);
    }

    /** AJAX chamado pelo player a cada poucos segundos (timeupdate) -- reporta o percentual
     *  maximo de reproducao ja alcancado nesse video. Se isso completar TODOS os videos
     *  cadastrados, libera o acesso do Vendedor na hora (sem precisar relogar). */
    public function reportProgress(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== Roles::SELLER || !Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            Response::json(['error' => 'invalido']);
        }

        $videoId = (int) ($_POST['video_id'] ?? 0);
        $percent = (float) ($_POST['percent'] ?? 0);
        if (!$videoId || !SellerTrainingVideo::find($videoId)) {
            http_response_code(422);
            Response::json(['error' => 'video invalido']);
        }

        SellerTrainingProgress::reportProgress((int) $user['id'], $videoId, $percent);

        $allCompleted = SellerTrainingProgress::hasCompletedAll((int) $user['id']);
        if ($allCompleted) {
            User::markTrainingCompleted((int) $user['id']);
        }

        Response::json(['allCompleted' => $allCompleted]);
    }

    /** Gestao de conteudo (admin/gerente) -- mesmo escopo do TutorialController::manage(),
     *  papel nacional de suporte a conteudo (Roles::SUPERVISOR_ASSIGNMENT). Fase 83: videos
     *  organizados em modulos -- agrupa aqui pra view nao precisar repetir a logica. */
    public function manage(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);

        $videos = SellerTrainingVideo::all();
        $byModule = [];
        foreach ($videos as $v) {
            $byModule[(int) ($v['module_id'] ?? 0)][] = $v;
        }

        View::render('painel/settings/treinamento', [
            'user' => Auth::user(),
            'modules' => SellerTrainingModule::all(),
            'byModule' => $byModule,
        ]);
    }

    public function storeVideo(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        $title = trim($_POST['title'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        if ($title === '' || $videoUrl === '') {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        $moduleId = !empty($_POST['module_id']) ? (int) $_POST['module_id'] : null;
        if ($moduleId !== null && !SellerTrainingModule::find($moduleId)) {
            $moduleId = null;
        }

        SellerTrainingVideo::create($title, $videoUrl, $moduleId);
        Router::redirect('/painel/configuracoes/treinamento?sucesso=1');
    }

    public function destroyVideo(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        SellerTrainingVideo::delete((int) $id);
        Router::redirect('/painel/configuracoes/treinamento?sucesso=1');
    }

    public function moveVideo(string $id, string $direction): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        $direction === 'subir' ? SellerTrainingVideo::moveUp((int) $id) : SellerTrainingVideo::moveDown((int) $id);
        Router::redirect('/painel/configuracoes/treinamento?sucesso=1');
    }

    public function storeModule(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            Router::redirect('/painel/configuracoes/treinamento?erro=2');
        }

        SellerTrainingModule::create($title);
        Router::redirect('/painel/configuracoes/treinamento?sucesso=1');
    }

    public function destroyModule(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        SellerTrainingModule::delete((int) $id);
        Router::redirect('/painel/configuracoes/treinamento?sucesso=1');
    }

    public function moveModule(string $id, string $direction): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/treinamento?erro=1');
        }

        $direction === 'subir' ? SellerTrainingModule::moveUp((int) $id) : SellerTrainingModule::moveDown((int) $id);
        Router::redirect('/painel/configuracoes/treinamento?sucesso=1');
    }
}
