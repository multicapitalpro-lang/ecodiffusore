<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\LeadRoutingSettings;
use App\Models\User;

/** Configuracao de pra onde vao os leads sem Vendedor no raio de 100km (Fase 35) -- admin-only. */
class LeadRoutingSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/settings/lead_routing', [
            'user' => Auth::user(),
            'settings' => LeadRoutingSettings::current(),
            'licenciados' => User::allByRole('licenciado'),
        ]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/roteamento?erro=1');
        }

        $licenciadoId = !empty($_POST['central_licenciado_id']) ? (int) $_POST['central_licenciado_id'] : null;

        if ($licenciadoId !== null) {
            $licenciado = User::find($licenciadoId);
            if (!$licenciado || $licenciado['role_slug'] !== 'licenciado') {
                Router::redirect('/painel/configuracoes/roteamento?erro=1');
            }
        }

        LeadRoutingSettings::update($licenciadoId);

        Router::redirect('/painel/configuracoes/roteamento?sucesso=1');
    }
}
