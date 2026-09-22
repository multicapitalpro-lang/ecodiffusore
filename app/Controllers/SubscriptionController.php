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
use App\Models\LicenciadoSeatAddon;
use App\Models\LicenciadoSubscription;
use App\Models\SubscriptionPaywallHit;
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
        $licenciadoId = $licenciado['id'] ?? null;

        $feature = $_GET['feature'] ?? null;
        $paywallHit = $licenciadoId ? SubscriptionPaywallHit::latestForLicenciado((int) $licenciadoId) : null;

        View::render('painel/subscription/index', [
            'user' => $user,
            'isLicenciado' => $isLicenciado,
            'licenciadoName' => $licenciado['name'] ?? null,
            'active' => $licenciadoId ? LicenciadoSubscription::activeFor((int) $licenciadoId) : null,
            'history' => $isLicenciado ? LicenciadoSubscription::forUser((int) $user['id']) : [],
            'plans' => SubscriptionPlans::PRICES,
            'feature' => $feature,
            'paywallHit' => $paywallHit,
            'seatCount' => $licenciadoId ? User::activeStaffCountFor((int) $licenciadoId) : 0,
            'includedSeats' => SubscriptionPlans::INCLUDED_SEATS,
            'extraSeats' => $licenciadoId ? LicenciadoSeatAddon::activeSeatsFor((int) $licenciadoId) : 0,
            'seatHistory' => $isLicenciado ? LicenciadoSeatAddon::forUser((int) $user['id']) : [],
        ]);
    }

    /** Fase 86: comprar N vagas extras (acima de SubscriptionPlans::INCLUDED_SEATS), mesmo modelo
     *  de checkout prepago da assinatura base. */
    public function purchaseSeats(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/assinatura?erro=1');
        }

        $plan = $_POST['plan'] ?? '';
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        if (!isset(SubscriptionPlans::SEAT_PRICES[$plan])) {
            Router::redirect('/painel/assinatura?erro=1');
        }

        $addon = LicenciadoSeatAddon::create((int) $user['id'], $plan, $quantity);

        $checkoutUrl = (new MercadoPagoClient())->createCheckout(
            $addon['external_reference'],
            "Vaga extra Ecodiffusore Painel ({$quantity}x) — " . SubscriptionPlans::LABELS[$plan],
            (float) $addon['amount'],
            rtrim(Config::get('app_url'), '/') . '/webhooks/mercadopago',
            rtrim(Config::get('app_url'), '/') . '/painel/assinatura?pedido_vaga=' . $addon['id']
        );

        if (!$checkoutUrl) {
            Router::redirect('/painel/assinatura?erro=indisponivel');
        }

        LicenciadoSeatAddon::setCheckoutUrl((int) $addon['id'], $checkoutUrl);
        Router::redirect($checkoutUrl);
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
