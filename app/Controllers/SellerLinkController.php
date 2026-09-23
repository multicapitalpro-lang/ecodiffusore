<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\User;

/** Tela "Meu Link de Vendas" (Fase 93) -- gera (na primeira vez) e mostra o link publico pessoal
 *  do Licenciado/Gestor/Vendedor. Funcao GRATUITA, fora do pacote pago (decisao explicita do
 *  usuario) -- disponivel pra qualquer um dos 3 papeis, com ou sem assinatura ativa. */
class SellerLinkController
{
    private const ROLES = ['licenciado', 'gestor', 'vendedor'];

    public function index(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();

        $slug = User::assignPublicSlug((int) $user['id']);

        View::render('painel/seller_link/index', [
            'user' => $user,
            'slug' => $slug,
            'publicUrl' => 'https://ecodiffusorebrasil.com.br/v/' . $slug,
        ]);
    }
}
