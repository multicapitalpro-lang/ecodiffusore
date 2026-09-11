<?php

namespace App\Controllers;

use App\Core\AsaasClient;
use App\Core\Auth;
use App\Core\CardPricing;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Roles;
use App\Core\Router;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Models\NfeSettings;

class PaymentController
{
    private const ALLOWED_ROLES = Roles::STAFF;

    public function generateForOrder(string $id): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $id = (int) $id;
        $order = Order::find($id);
        if (!$order) {
            Router::redirect('/painel/pedidos');
        }

        $this->authorizeOwnership((int) $order['seller_id']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/pedidos/{$id}?erro=1");
        }

        // CNH + documento do veiculo sao obrigatorios antes de cobrar (Fase 40) -- a fabrica
        // precisa do documento do veiculo pra montar o pedido certo, entao gerar a cobranca sem
        // isso so criaria trabalho de cobrar de novo depois. Redireciona pro Pedido (nao segue com
        // a cobranca) -- o staff anexa os documentos ali e clica em "Gerar cobranca" de novo.
        if (!Order::hasRequiredDocuments($order)) {
            Router::redirect("/painel/pedidos/{$id}?erro_documentos=1");
        }

        try {
            $this->generateCharge('order', $id, (int) $order['client_id'], (float) $order['total_value'], "Pedido #{$id} — Ecodiffusore Brasil");
        } catch (\Throwable $e) {
            Router::redirect("/painel/pedidos/{$id}?erro_cobranca=" . urlencode($e->getMessage()));
        }

        Router::redirect("/painel/pedidos/{$id}?sucesso=1");
    }

    public function generateForQuote(string $id): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $id = (int) $id;
        $quote = Quote::find($id);
        if (!$quote) {
            Router::redirect('/painel/orcamentos');
        }

        $this->authorizeOwnership((int) $quote['seller_id']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/orcamentos/{$id}?erro=1");
        }

        try {
            $this->generateCharge('quote', $id, (int) $quote['client_id'], (float) $quote['total_value'], "Orçamento #{$id} — Ecodiffusore Brasil");
        } catch (\Throwable $e) {
            Router::redirect("/painel/orcamentos/{$id}?erro_cobranca=" . urlencode($e->getMessage()));
        }

        Router::redirect("/painel/orcamentos/{$id}?sucesso=1");
    }

    /** Webhook publico do Asaas — sem Auth::requireLogin, autenticado pelo header asaas-access-token */
    public function webhook(): void
    {
        $expected = Config::get('asaas', [])['webhook_token'] ?? '';
        $received = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';

        if ($expected === '' || !hash_equals($expected, $received)) {
            http_response_code(403);
            exit;
        }

        $body = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
        $event = $body['event'] ?? '';
        $chargeId = $body['payment']['id'] ?? null;

        if (!in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true) || !$chargeId) {
            http_response_code(200);
            exit;
        }

        $payment = Payment::findByChargeId($chargeId);
        if (!$payment || $payment['status'] === 'pago') {
            http_response_code(200);
            exit;
        }

        Payment::markPaid((int) $payment['id']);

        $orderId = null;
        if ($payment['payable_type'] === 'order') {
            $orderId = (int) $payment['payable_id'];
            Order::markVerifiedWithCommission($orderId);
        } elseif ($payment['payable_type'] === 'quote') {
            $quote = Quote::find((int) $payment['payable_id']);
            if ($quote && $quote['status'] !== 'convertido' && !Approval::pendingFor('quote', (int) $payment['payable_id'])) {
                $orderId = Quote::convertToOrder((int) $payment['payable_id']);
                Order::markVerifiedWithCommission($orderId);
            }
        }

        if ($orderId) {
            $this->maybeIssueInvoice((int) $payment['id'], $chargeId, $orderId, (float) $payment['amount']);
        }

        http_response_code(200);
    }

    /** Emite NF-e automaticamente pra um pedido recem-confirmado como pago, se o admin tiver
     *  ligado isso em /painel/configuracoes/nfe. Best-effort: qualquer falha (municipal service
     *  ainda nao configurado do lado da Asaas, etc.) e' engolida -- nunca deve derrubar a
     *  confirmacao do pagamento/comissao, que ja rodou antes desta chamada. */
    private function maybeIssueInvoice(int $paymentId, string $chargeId, int $orderId, float $amount): void
    {
        $settings = NfeSettings::current();
        if (empty($settings['enabled']) || empty($settings['municipal_service_id'])) {
            return;
        }

        try {
            $description = str_replace('{pedido}', (string) $orderId, (string) ($settings['service_description_template'] ?: 'Pedido #{pedido}'));

            $invoice = (new AsaasClient())->createInvoice([
                'payment_id' => $chargeId,
                'service_description' => $description,
                'observations' => $settings['observations_template'] ?: null,
                'value' => $amount,
                'effective_date' => date('Y-m-d'),
                'municipal_service_id' => $settings['municipal_service_id'],
                'municipal_service_name' => $settings['municipal_service_description'] ?: $description,
                'taxes' => [
                    'retain_iss' => (bool) $settings['retain_iss'],
                    'iss' => (float) $settings['iss_pct'],
                    'cofins' => (float) $settings['cofins_pct'],
                    'csll' => (float) $settings['csll_pct'],
                    'inss' => (float) $settings['inss_pct'],
                    'ir' => (float) $settings['ir_pct'],
                    'pis' => (float) $settings['pis_pct'],
                ],
            ]);

            // pdfUrl normalmente so fica disponivel depois da aprovacao municipal (assincrono) --
            // grava o que tiver agora, o lazy-check NfeStatusChecker::processDue() reconsulta
            // depois ate a nota ficar pronta pra fabrica/staff baixarem no pedido.
            Order::updateNfe($orderId, $invoice['id'] ?? null, $invoice['status'] ?? null, $invoice['pdfUrl'] ?? null, $invoice['number'] ?? null);
        } catch (\Throwable $e) {
            // Emissao de NF-e e' secundaria ao pagamento em si -- nao interrompe o webhook.
            // Sem error_log acessivel em producao neste plano (ver reference-ecodiffusore-deploy);
            // falha fica silenciosa do lado do sistema, visivel só como ausencia de nota na Asaas.
        }
    }

    /** $basePrice e' sempre o preco de tabela puro (order/quote.total_value) -- a comissao em
     *  cascata (Commission::createCascadeForOrder) e' calculada sobre esse valor, nunca sobre o
     *  valor com taxa de cartao/antecipacao embutida. So o valor de fato cobrado (Payment::amount)
     *  reflete o repasse -- o cliente nunca ve essas taxas separadas, so o total/parcela final. */
    private function generateCharge(string $payableType, int $payableId, int $clientId, float $basePrice, string $description): void
    {
        $client = Client::find($clientId);
        if (!$client) {
            throw new \RuntimeException('Cliente não encontrado.');
        }

        $billingType = in_array($_POST['billing_type'] ?? '', ['PIX', 'BOLETO', 'CREDIT_CARD'], true)
            ? $_POST['billing_type']
            : 'PIX';

        $installments = 1;
        $chargeAmount = $basePrice;
        if ($billingType === 'CREDIT_CARD') {
            $installments = max(1, min(CardPricing::maxInstallments(), (int) ($_POST['installments'] ?? 1)));
            $chargeAmount = CardPricing::chargeAmount($basePrice, $installments);
        }

        $asaas = new AsaasClient();
        $customerId = $asaas->createOrFindCustomer($client);

        $dueDate = date('Y-m-d', strtotime('+3 days'));
        $charge = $asaas->createCharge([
            'customer' => $customerId,
            'billing_type' => $billingType,
            'value' => $chargeAmount,
            'due_date' => $dueDate,
            'description' => $description,
            'external_reference' => $payableType . ':' . $payableId,
            'installment_count' => $installments > 1 ? $installments : null,
        ]);

        $pixPayload = null;
        if ($billingType === 'PIX') {
            $pix = $asaas->getPixQrCode($charge['id']);
            $pixPayload = $pix['payload'] ?? null;
        }

        Payment::create([
            'payable_type' => $payableType,
            'payable_id' => $payableId,
            'asaas_customer_id' => $customerId,
            'asaas_charge_id' => $charge['id'],
            'method' => $billingType,
            'amount' => $chargeAmount,
            'checkout_url' => $charge['invoiceUrl'] ?? null,
            'pix_payload' => $pixPayload,
            'due_date' => $dueDate,
        ]);

        $items = $payableType === 'quote' ? QuoteItem::forQuote($payableId) : OrderItem::forOrder($payableId);
        $produtos = $items
            ? implode(', ', array_map(fn ($i) => $i['product_name'] . ' (x' . (int) $i['quantity'] . ')', $items))
            : null;

        Notifier::cobrancaGerada($client, [
            'method' => $billingType,
            'installments' => $installments,
            'amount' => $chargeAmount,
            'due_date' => $dueDate,
            'checkout_url' => $charge['invoiceUrl'] ?? null,
            'pix_payload' => $pixPayload,
            'produtos' => $produtos,
        ], $payableType, $payableId);
    }

    private function authorizeOwnership(?int $sellerId): void
    {
        $user = Auth::user();
        if ($user['role_slug'] === 'admin') {
            return;
        }

        // Gerente/Supervisor sao papel de suporte nacional -- so visualizam, nunca geram cobranca
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $allowed = $user['role_slug'] === Roles::SELLER
            ? $sellerId === (int) $user['id']
            : in_array($sellerId, User::downlineIds((int) $user['id']), true);

        if (!$allowed) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }
}
