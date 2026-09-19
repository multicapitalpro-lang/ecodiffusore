<?php

namespace App\Controllers;

use App\Core\Router;
use App\Core\View;
use App\Models\PricingTier;
use App\Models\User;
use App\Models\UserCommissionTier;

/** Pagina publica (sem login) do link de aceite de comissao enviado por WhatsApp (Fase 32) --
 *  o token e' a autorizacao, mesmo espirito de link de redefinicao de senha. */
class AcceptanceController
{
    public function show(string $token): void
    {
        $target = User::findByCommissionAcceptToken($token);
        if (!$target) {
            View::render('site/aceite_comissao', ['found' => false], 'site');
            return;
        }

        $tierRows = null;
        if ($target['role_slug'] === 'vendedor' && !empty($target['commission_type'])) {
            $values = UserCommissionTier::forUser((int) $target['id']);
            $tierRows = array_map(fn ($t) => [
                'min_price' => $t['min_price'],
                'max_price' => $t['max_price'],
                'value' => $values[$t['id']] ?? null,
            ], PricingTier::visible());
        }

        View::render('site/aceite_comissao', [
            'found' => true,
            'token' => $token,
            'target' => $target,
            'tierRows' => $tierRows,
        ], 'site');
    }

    public function accept(string $token): void
    {
        $target = User::findByCommissionAcceptToken($token);
        if (!$target) {
            Router::redirect('/aceite-comissao/' . $token);
        }

        User::acceptCommissionTerms((int) $target['id']);
        Router::redirect('/aceite-comissao/' . $token);
    }
}
