<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\TutorialVideo;

/** Videos tutoriais (instalacao do produto, teste da chave etc.) pro comprador acessar facil
 *  dentro do proprio painel dele -- pedido do usuario. Admin/Gerente cadastra o link (mesmo
 *  papel de gestao de conteudo nacional ja usado em Scripts de Venda/Depoimentos,
 *  Roles::SUPERVISOR_ASSIGNMENT), cliente so visualiza. */
class TutorialController
{
    public function manage(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        View::render('painel/settings/tutoriais', [
            'user' => Auth::user(),
            'videos' => TutorialVideo::all(),
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/tutoriais?erro=1');
        }

        $title = trim($_POST['title'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        if ($title === '' || $videoUrl === '') {
            Router::redirect('/painel/configuracoes/tutoriais?erro=1');
        }

        TutorialVideo::create($title, $videoUrl, trim($_POST['description'] ?? ''));
        Router::redirect('/painel/configuracoes/tutoriais?sucesso=1');
    }

    public function destroy(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/tutoriais?erro=1');
        }

        TutorialVideo::delete((int) $id);
        Router::redirect('/painel/configuracoes/tutoriais?sucesso=1');
    }

    /** Visao do comprador -- so leitura, sem gate de gestao. */
    public function client(): void
    {
        Auth::requireRole(['cliente']);
        View::render('painel/client_portal/tutoriais', [
            'user' => Auth::user(),
            'videos' => TutorialVideo::all(),
        ]);
    }
}
