<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\User;

/** Tela "Meu Link de Vendas" (Fase 93) -- gera (na primeira vez) e mostra o link publico pessoal
 *  do Licenciado/Gestor/Vendedor. So' a GERACAO/visualizacao fica atras do paywall -- uma vez
 *  gerado, o link publico em si (App\Controllers\SellerLandingController) continua funcionando
 *  sempre, mesmo que a assinatura vença depois (senao um link ja distribuido em panfleto/QR code
 *  quebraria pro cliente final, e o lead seria perdido). */
class SellerLinkController
{
    private const ROLES = ['licenciado', 'gestor', 'vendedor'];

    public function index(): void
    {
        Auth::requireRole(self::ROLES);
        $user = Auth::user();

        $hasAccess = SubscriptionGate::hasAccess($user);
        $slug = $hasAccess ? User::assignPublicSlug((int) $user['id']) : null;

        View::render('painel/seller_link/index', [
            'user' => $user,
            'hasAccess' => $hasAccess,
            'slug' => $slug,
            'publicUrl' => $slug ? 'https://ecodiffusorebrasil.com.br/v/' . $slug : null,
        ]);
    }
}
