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
use App\Models\LicenciadoFiscalData;
use App\Models\LicenciadoNfeCreditPurchase;

/** Fase 138: "Nota Fiscal Automática" -- ferramenta premium do Licenciado (dados fiscais da
 *  empresa + créditos pré-pagos de nota, comprados via Mercado Pago, mesmo mecanismo já usado
 *  pra assinatura/vaga extra -- ver App\Controllers\SubscriptionController). Fase 1: só coleta
 *  dados e vende crédito. A emissão de verdade via API de terceiro é Fase 2, quando o provedor
 *  (Dados JAH/Notaas/NFE.io) for escolhido -- essa tela já deixa claro pro Licenciado que
 *  precisa ter saldo pra quando a automação entrar no ar. */
class NfeController
{
    public function show(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        View::render('painel/nota_fiscal/index', [
            'user' => $user,
            'hasAccess' => SubscriptionGate::hasAccess($user),
            'fiscalData' => LicenciadoFiscalData::find((int) $user['id']),
            'balance' => LicenciadoNfeCreditPurchase::balanceFor((int) $user['id']),
            'purchases' => LicenciadoNfeCreditPurchase::forUser((int) $user['id']),
            'packages' => SubscriptionPlans::NFE_CREDIT_PACKAGES,
            'sucesso' => $_GET['sucesso'] ?? null,
            'erro' => $_GET['erro'] ?? null,
        ]);
    }

    public function saveFiscalData(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'nota_fiscal');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/nota-fiscal?erro=1');
        }

        $cnpjDigits = preg_replace('/\D/', '', (string) ($_POST['cnpj'] ?? ''));
        if (trim($_POST['razao_social'] ?? '') === '' || strlen($cnpjDigits) !== 14) {
            Router::redirect('/painel/nota-fiscal?erro=dados');
        }

        LicenciadoFiscalData::upsert((int) $user['id'], $_POST);

        Router::redirect('/painel/nota-fiscal?sucesso=dados');
    }

    public function purchaseCredits(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'nota_fiscal');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/nota-fiscal?erro=1');
        }

        $quantity = (int) ($_POST['quantity'] ?? 0);
        if (!isset(SubscriptionPlans::NFE_CREDIT_PACKAGES[$quantity])) {
            Router::redirect('/painel/nota-fiscal?erro=1');
        }

        $amount = SubscriptionPlans::NFE_CREDIT_PACKAGES[$quantity];
        $purchase = LicenciadoNfeCreditPurchase::create((int) $user['id'], $quantity, $amount);

        $checkoutUrl = (new MercadoPagoClient())->createCheckout(
            $purchase['external_reference'],
            "Créditos de Nota Fiscal Ecodiffusore ({$quantity}x)",
            $amount,
            rtrim(Config::get('app_url'), '/') . '/webhooks/mercadopago',
            rtrim(Config::get('app_url'), '/') . '/painel/nota-fiscal?pedido_credito=' . $purchase['id']
        );

        if (!$checkoutUrl) {
            Router::redirect('/painel/nota-fiscal?erro=indisponivel');
        }

        LicenciadoNfeCreditPurchase::setCheckoutUrl((int) $purchase['id'], $checkoutUrl);
        Router::redirect($checkoutUrl);
    }
}
