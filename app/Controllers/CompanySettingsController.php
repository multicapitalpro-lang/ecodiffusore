<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\CompanySettings;

/** Dados legais da empresa (razao social/CNPJ/endereco) -- usados no Termo de Garantia. Admin-only. */
class CompanySettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/settings/empresa', [
            'user' => Auth::user(),
            'settings' => CompanySettings::current(),
        ]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/empresa?erro=1');
        }

        CompanySettings::update(
            trim($_POST['razao_social'] ?? ''),
            trim($_POST['cnpj'] ?? ''),
            trim($_POST['endereco'] ?? '')
        );

        Router::redirect('/painel/configuracoes/empresa?sucesso=1');
    }
}
