<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\MercadoPagoClient;
use App\Models\LicenciadoNfeCreditPurchase;
use App\Models\LicenciadoSeatAddon;
use App\Models\LicenciadoSubscription;

/**
 * Webhook publico do Mercado Pago (Fase 32). Duas camadas de verificacao, a segunda sempre ativa:
 * (1) assinatura HMAC (header x-signature, "Assinatura secreta" configurada no painel do Mercado
 *     Pago) -- se webhook_secret estiver configurado E o header vier, rejeita direto quando nao
 *     bate. Formato oficial: x-signature = "ts=<ts>,v1=<hash>"; manifest = "id:<data.id>;
 *     request-id:<x-request-id>;ts:<ts>;"; hash = hash_hmac('sha256', manifest, secret).
 * (2) mesmo sem (1) configurada, NUNCA confia no corpo recebido pra creditar algo: sempre busca o
 *     status de verdade direto na API deles (MercadoPagoClient::getPayment()) -- um pagamento
 *     "approved" so existe se realmente foi pago. Por isso (1) e' defesa em profundidade, nao a
 *     unica trava.
 */
class SubscriptionWebhookController
{
    public function mercadopago(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $body = json_decode($rawBody, true) ?? [];
        $paymentId = $body['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? null;
        $type = $body['type'] ?? $_GET['type'] ?? $_GET['topic'] ?? '';

        if (!$paymentId || $type !== 'payment') {
            http_response_code(200);
            exit;
        }

        if (!$this->signatureValid((string) $paymentId)) {
            http_response_code(403);
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

        // Fase 86: vaga extra de colaborador usa o mesmo external_reference + webhook (referencias
        // sao geradas com bin2hex(random_bytes(16)) nos dois modelos -- nunca colidem entre si).
        $seatAddon = LicenciadoSeatAddon::findByExternalReference($payment['external_reference']);
        if ($seatAddon) {
            LicenciadoSeatAddon::markPaid((int) $seatAddon['id'], (string) $paymentId);
        }

        // Fase 138: credito de Nota Fiscal Automatica -- mesmo external_reference unico
        // (bin2hex(random_bytes(16))), nunca colide com os outros dois modelos acima.
        $nfeCredit = LicenciadoNfeCreditPurchase::findByExternalReference($payment['external_reference']);
        if ($nfeCredit) {
            LicenciadoNfeCreditPurchase::markPaid((int) $nfeCredit['id'], (string) $paymentId);
        }

        http_response_code(200);
    }

    /** true quando: sem secret configurado (nada pra validar, cai so na verificacao via API) OU
     *  sem header (mesma coisa) OU a assinatura bate. So retorna false quando ha secret + header
     *  E eles NAO combinam -- esse e' o unico caso de rejeicao. */
    private function signatureValid(string $paymentId): bool
    {
        $secret = Config::get('mercadopago', [])['webhook_secret'] ?? '';
        $signatureHeader = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

        if ($secret === '' || $signatureHeader === '') {
            return true;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $piece) {
            [$key, $value] = array_pad(explode('=', $piece, 2), 2, null);
            if ($key !== null) {
                $parts[trim($key)] = trim((string) $value);
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;
        if (!$ts || !$v1) {
            return true;
        }

        $manifest = "id:{$paymentId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }
}
