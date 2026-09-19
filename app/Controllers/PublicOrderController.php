<?php

namespace App\Controllers;

use App\Core\AsaasClient;
use App\Core\CardPricing;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Notifier;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentSettings;

/**
 * Fase 63: pagina publica do Pedido (sem login) -- o vendedor manda esse link direto pro
 * comprador assim que cria o Pedido (Proposta Facil ja nao pede CPF/CNH/documentos de cara, ver
 * Fase 60/61) e o cliente, sozinho, no celular, ve os valores, aceita os Termos de Compra (Fase
 * 62), escolhe como pagar e paga -- tudo numa pagina so. Autorizacao inteira pelo token opaco
 * (Order::public_token, 40 hex chars gerados em Order::create()), nunca pelo id cru do pedido.
 * Depois de pago, essa MESMA pagina vira o lugar de enviar CNH/documento do veiculo/fotos/
 * telemetria (mesmo fluxo ja construido em ClientPortalController, so que sem exigir conta/login
 * -- o link e' a "senha").
 */
class PublicOrderController
{
    public function show(string $token): void
    {
        $order = Order::findByToken($token);
        if (!$order) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            exit;
        }

        // Fase 65: o card do Lead anda sozinho no Kanban conforme o comprador avanca aqui --
        // idempotente (Lead::advanceCheckoutStage so' avanca pra frente), entao chamar em toda
        // visita (nao so' a primeira) e' seguro e simples.
        $leadId = Order::leadIdFor((int) $order['id']);
        if ($leadId) {
            Lead::advanceCheckoutStage($leadId, 'checkout_acessado');
        }

        View::render('site/pedido_publico', [
            'order' => $order,
            'items' => OrderItem::forOrder((int) $order['id']),
            'payments' => Payment::forPayable('order', (int) $order['id']),
            'termsText' => CompanySettings::current()['terms_text'] ?? '',
            'maxInstallments' => CardPricing::maxInstallments(),
            'feeSettings' => PaymentSettings::current(),
        ], null);
    }

    /** Fase 66: deixa o comprador trocar de ideia sobre a forma de pagamento -- sem isso, uma
     *  cobranca gerada uma vez (pelo staff ou por ele mesmo) travava a pagina pra sempre nesse
     *  metodo, mesmo que ele preferisse outro (achado testando o Pedido #43, que ja tinha um Pix
     *  gerado antes dessa pagina existir). Cancela a cobranca PENDENTE na Asaas (best-effort -- se
     *  ja tiver expirado/sumido do lado deles, seguimos e marcamos cancelada aqui do mesmo jeito)
     *  e volta pra tela de escolha. Nunca mexe numa cobranca ja PAGA. */
    public function cancelPayment(string $token): void
    {
        $order = Order::findByToken($token);
        if (!$order) {
            http_response_code(404);
            exit('Pedido não encontrado.');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/pedido/{$token}?erro_cobranca=" . urlencode('Sessão expirada, tente de novo.'));
        }

        $pending = null;
        foreach (Payment::forPayable('order', (int) $order['id']) as $p) {
            if ($p['status'] === 'pendente') {
                $pending = $p;
                break;
            }
        }

        if ($pending) {
            try {
                (new AsaasClient())->cancel($pending['asaas_charge_id']);
            } catch (\Throwable $e) {
                // Best-effort -- segue e marca cancelada do nosso lado de qualquer forma, nao
                // trava o cliente por causa de uma cobranca que a Asaas ja tratou sozinha.
            }
            Payment::markCancelled((int) $pending['id']);
        }

        Router::redirect("/pedido/{$token}?cobranca_cancelada=1");
    }

    public function acceptTerms(string $token): void
    {
        $order = Order::findByToken($token);
        if (!$order) {
            http_response_code(404);
            exit('Pedido não encontrado.');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || empty($_POST['aceite'])) {
            Router::redirect("/pedido/{$token}?erro_termos=1");
        }

        if (empty($order['terms_accepted_at'])) {
            Order::acceptTerms((int) $order['id'], CompanySettings::current()['terms_text'] ?? '');
        }

        $leadId = Order::leadIdFor((int) $order['id']);
        if ($leadId) {
            Lead::advanceCheckoutStage($leadId, 'termos_aceitos');
        }

        Router::redirect("/pedido/{$token}?termos_ok=1");
    }

    /** Mesmo formato de ClientPortalController::uploadOrderDocuments() (Fase 61), so' que
     *  autorizado por token em vez de sessao de cliente logado. */
    public function uploadDocuments(string $token): void
    {
        $order = Order::findByToken($token);
        if (!$order) {
            http_response_code(404);
            exit('Pedido não encontrado.');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/pedido/{$token}?erro_docs=1");
        }

        $id = (int) $order['id'];
        $fields = [];

        $plate = strtoupper(trim($_POST['vehicle_plate'] ?? ''));
        if ($plate !== '') {
            $fields['vehicle_plate'] = $plate;
        }

        try {
            $vehicleDocument = FileUpload::storeVehicleDocument($_FILES['vehicle_document'] ?? []);
            $cnhDocument = FileUpload::storeCnhDocument($_FILES['cnh_document'] ?? []);
            $photo1 = FileUpload::storeVehiclePhoto($_FILES['photo1'] ?? []);
            $photo2 = FileUpload::storeVehiclePhoto($_FILES['photo2'] ?? []);
            $photo3 = FileUpload::storeVehiclePhoto($_FILES['photo3'] ?? []);
            $telemetry = FileUpload::storeTelemetryFile($_FILES['telemetry'] ?? []);
        } catch (\RuntimeException $e) {
            Router::redirect("/pedido/{$token}?erro_docs=" . urlencode($e->getMessage()));
        }

        if ($vehicleDocument) {
            $fields['vehicle_document_path'] = $vehicleDocument['stored_name'];
        }
        if ($cnhDocument) {
            $fields['cnh_document_path'] = $cnhDocument['stored_name'];
        }
        if ($photo1) {
            $fields['photo1_path'] = $photo1['stored_name'];
        }
        if ($photo2) {
            $fields['photo2_path'] = $photo2['stored_name'];
        }
        if ($photo3) {
            $fields['photo3_path'] = $photo3['stored_name'];
        }
        if ($telemetry) {
            $fields['telemetry_path'] = $telemetry['stored_name'];
        }

        if (!$fields) {
            Router::redirect("/pedido/{$token}?erro_docs=" . urlencode('Selecione pelo menos um arquivo ou preencha a placa.'));
        }

        Order::updateDocuments($id, $fields);

        $updated = Order::find($id);
        if ($updated && Order::hasRequiredDocuments($updated)) {
            Notifier::pedidoDocumentosEnviados($updated);
        }

        Router::redirect("/pedido/{$token}?docs_sucesso=1");
    }

    /** Diferente de PaymentController::generateForOrder() (STAFF escolhe a forma e gera): aqui e'
     *  o PROPRIO comprador que escolhe. Exige Termos ja aceitos -- gate real no servidor, nao so'
     *  visual -- e CPF/CNPJ (a Asaas exige pra criar cliente/cobranca; se o Cliente ainda nao tem,
     *  pede nesse mesmo formulario e grava via Client::updateDocument()). */
    public function generateCharge(string $token): void
    {
        $order = Order::findByToken($token);
        if (!$order) {
            http_response_code(404);
            exit('Pedido não encontrado.');
        }

        $id = (int) $order['id'];

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/pedido/{$token}?erro_cobranca=" . urlencode('Sessão expirada, tente de novo.'));
        }

        if (empty($order['terms_accepted_at'])) {
            Router::redirect("/pedido/{$token}?erro_cobranca=" . urlencode('Aceite os Termos de Compra antes de continuar.'));
        }

        $document = preg_replace('/\D/', '', (string) ($_POST['document'] ?? ''));
        if ($document !== '' && in_array(strlen($document), [11, 14], true)) {
            Client::updateDocument((int) $order['client_id'], $document);
        }

        $client = Client::find((int) $order['client_id']);
        if (!$client || empty($client['document'])) {
            Router::redirect("/pedido/{$token}?erro_cobranca=" . urlencode('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.'));
        }

        $billingType = in_array($_POST['billing_type'] ?? '', ['PIX', 'BOLETO', 'CREDIT_CARD'], true)
            ? $_POST['billing_type']
            : 'PIX';

        $basePrice = (float) $order['total_value'];
        $installments = 1;
        $chargeAmount = $basePrice;
        if ($billingType === 'CREDIT_CARD') {
            $installments = max(1, min(CardPricing::maxInstallments(), (int) ($_POST['installments'] ?? 1)));
            $chargeAmount = CardPricing::chargeAmount($basePrice, $installments);
        }

        try {
            $asaas = new AsaasClient();
            $customerId = $asaas->createOrFindCustomer($client);

            $dueDate = date('Y-m-d', strtotime('+3 days'));
            $charge = $asaas->createCharge([
                'customer' => $customerId,
                'billing_type' => $billingType,
                'value' => $chargeAmount,
                'due_date' => $dueDate,
                'description' => "Pedido #{$id} — Ecodiffusore Brasil",
                'external_reference' => 'order:' . $id,
                'installment_count' => $installments > 1 ? $installments : null,
            ]);

            $pixPayload = null;
            if ($billingType === 'PIX') {
                $pix = $asaas->getPixQrCode($charge['id']);
                $pixPayload = $pix['payload'] ?? null;
            }

            Payment::create([
                'payable_type' => 'order',
                'payable_id' => $id,
                'asaas_customer_id' => $customerId,
                'asaas_charge_id' => $charge['id'],
                'method' => $billingType,
                'amount' => $chargeAmount,
                'checkout_url' => $charge['invoiceUrl'] ?? null,
                'pix_payload' => $pixPayload,
                'due_date' => $dueDate,
            ]);
        } catch (\Throwable $e) {
            Router::redirect("/pedido/{$token}?erro_cobranca=" . urlencode($e->getMessage()));
        }

        $leadId = Order::leadIdFor($id);
        if ($leadId) {
            Lead::advanceCheckoutStage($leadId, 'pagamento_gerado');
        }

        Router::redirect("/pedido/{$token}?cobranca_ok=1");
    }
}
