<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Models\User;

/** Fase 103: "Meu Link de Vendas" pro app -- funcao GRATUITA (Fase 99, fora do pacote pago), mesma
 *  logica de App\Controllers\SellerLinkController: gera (na primeira vez) e devolve o link
 *  publico pessoal do Licenciado/Gestor/Vendedor. */
class SellerLinkController
{
    private const ROLES = ['licenciado', 'gestor', 'vendedor'];

    public function show(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], self::ROLES, true)) {
            ApiResponse::error('Papel sem acesso a essa função.', 403);
        }

        $slug = User::assignPublicSlug((int) $user['id']);
        ApiResponse::json([
            'slug' => $slug,
            'public_url' => 'https://ecodiffusorebrasil.com.br/v/' . $slug,
        ]);
    }
}
