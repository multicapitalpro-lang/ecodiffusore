<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Pdf;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;

/** Fase 96: Certificacao do Vendedor -- sexta das 7 ferramentas premium aprovadas. Nao cria
 *  rastreio novo de treinamento (users.training_completed_at ja existe e ja e' a trava real de
 *  acesso do Vendedor desde a Fase 42/App\Core\Auth) -- essa tela e' so' a camada de apresentacao
 *  em cima disso: status + certificado em PDF pra baixar/imprimir/mostrar pro cliente. */
class CertificationController
{
    public function index(): void
    {
        Auth::requireRole(['vendedor']);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'certificacao_vendedor');

        View::render('painel/certification/index', ['user' => $user]);
    }

    public function pdf(): void
    {
        Auth::requireRole(['vendedor']);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'certificacao_vendedor');

        if (empty($user['training_completed_at'])) {
            Router::redirect('/painel/certificacao?erro=1');
        }

        ob_start();
        View::render('painel/certification/pdf', [
            'sellerName' => $user['name'],
            'certifiedAt' => $user['training_completed_at'],
            'certNumber' => 'EDB-CERT-' . str_pad((string) $user['id'], 5, '0', STR_PAD_LEFT),
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'certificado-vendedor-ecodiffusore.pdf', 'landscape');
    }
}
