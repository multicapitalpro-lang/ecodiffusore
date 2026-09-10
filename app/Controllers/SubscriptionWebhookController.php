<?php

namespace App\Controllers;

use App\Core\MercadoPagoClient;
use App\Models\LicenciadoSubscription;

/**
 * Webhook publico do Mercado Pago (Fase 32) -- ao contrario do Asaas (token fixo por header) e do
 * ClickSign (HMAC), o padrao recomendado pelo Mercado Pago pra notificacao de pagamento e' nunca
 * confiar no corpo recebido: so usa o id do pagamento pra buscar o status de verdade direto na API
 * deles (MercadoPagoClient::getPayment()) -- um pagamento "approved" so existe se realmente foi
 * pago, entao a auto-verificacao vem da propria chamada de volta pra API, sem precisar validar
 * assinatura separada.
 */
class SubscriptionWebhookController
{
    public function mercadopago(): void
    {
        $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
        $paymentId = $body['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? null;
        $type = $body['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '';

        if (!$paymentId || $type !== 'payment') {
            http_response_code(200);
            exit;
        }

        $payment = (new MercadoPagoClient())->getPayment((string) $paymentId);
        if (!$payment || $payment['status'] !== 'approved' || empty($payment['external_reference'])) {
            http_response_code(200);
            exit;
        }

        $subscription = LicenciadoSubscription::findByExternalReference($payment['external_reference']);
        if ($subscription) {
            LicenciadoSubscription::markPaid((int) $subscription['id'], (string) $paymentId);
        }

        http_response_code(200);
    }
}
