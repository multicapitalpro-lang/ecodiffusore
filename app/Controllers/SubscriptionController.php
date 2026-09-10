<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\MercadoPagoClient;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\SubscriptionPlans;
use App\Core\View;
use App\Models\LicenciadoSubscription;
use App\Models\User;

/** Paywall do Licenciado (Fase 32) -- ver App\Core\SubscriptionGate pro que fica bloqueado. */
class SubscriptionController
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!in_array($user['role_slug'], ['licenciado', 'gestor', 'vendedor'], true)) {
            Router::redirect('/painel');
        }

        $licenciado = User::licenciadoFor((int) $user['id']);
        $isLicenciado = $user['role_slug'] === 'licenciado';

        View::render('painel/subscription/index', [
            'user' => $user,
            'isLicenciado' => $isLicenciado,
            'licenciadoName' => $licenciado['name'] ?? null,
            'active' => $licenciado ? LicenciadoSubscription::activeFor((int) $licenciado['id']) : null,
            'history' => $isLicenciado ? LicenciadoSubscription::forUser((int) $user['id']) : [],
            'plans' => SubscriptionPlans::PRICES,
        ]);
    }

    public function purchase(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/assinatura?erro=1');
        }

        $plan = $_POST['plan'] ?? '';
        if (!SubscriptionPlans::isValid($plan)) {
            Router::redirect('/painel/assinatura?erro=1');
        }

        $subscription = LicenciadoSubscription::create((int) $user['id'], $plan);

        $checkoutUrl = (new MercadoPagoClient())->createCheckout(
            $subscription['external_reference'],
            'Assinatura Ecodiffusore Painel — ' . SubscriptionPlans::LABELS[$plan],
            (float) $subscription['amount'],
            rtrim(Config::get('app_url'), '/') . '/webhooks/mercadopago',
            rtrim(Config::get('app_url'), '/') . '/painel/assinatura?pedido=' . $subscription['id']
        );

        if (!$checkoutUrl) {
            Router::redirect('/painel/assinatura?erro=indisponivel');
        }

        LicenciadoSubscription::setCheckoutUrl((int) $subscription['id'], $checkoutUrl);
        Router::redirect($checkoutUrl);
    }
}
