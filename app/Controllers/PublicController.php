<?php

namespace App\Controllers;

use App\Core\AsaasClient;
use App\Core\CardPricing;
use App\Core\Csrf;
use App\Core\DataflowClient;
use App\Core\GeoMatch;
use App\Core\Router;
use App\Core\VehicleCatalog;
use App\Core\View;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;

class PublicController
{
    private const REF_COOKIE = 'eco_ref';

    public function home(): void
    {
        $this->trackReferral();
        View::render('site/home', [], 'site');
    }

    public function submitLead(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/?erro=csrf#contato');
        }

        $name = trim($_POST['name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $truckBrand = trim($_POST['truck_brand'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name === '' || $whatsapp === '') {
            Router::redirect('/?erro=1#contato');
        }

        $leadId = Lead::create([
            'name' => mb_substr($name, 0, 120),
            'whatsapp' => mb_substr($whatsapp, 0, 30),
            'city' => mb_substr($city, 0, 120),
            'truck_brand' => mb_substr($truckBrand, 0, 60),
            'message' => mb_substr($message, 0, 2000),
            'source' => 'landing_page',
        ]);

        $ref = $this->trackReferral();
        if ($ref) {
            Lead::assignTo($leadId, $ref);
        }

        Router::redirect('/?sucesso=1#contato');
    }

    public function buy(): void
    {
        $ref = $this->trackReferral();

        View::render('site/buy', [
            'showPopup' => empty($_SESSION['checkout_lead_id']),
            'checkoutName' => $_SESSION['checkout_name'] ?? '',
            'checkoutWhatsapp' => $_SESSION['checkout_whatsapp'] ?? '',
            'checkoutCity' => $_SESSION['checkout_city'] ?? '',
            'vehicleCatalog' => VehicleCatalog::all(),
            'ref' => $ref,
            'erro' => $_GET['erro'] ?? null,
        ], 'site');
    }

    /** AJAX: tenta identificar o veiculo pela placa. Stub por enquanto (ver App\Core\DataflowClient) --
     *  sempre responde "nao encontrado", o que faz o front cair no formulario manual passo a passo. */
    public function lookupPlate(): void
    {
        header('Content-Type: application/json');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            echo json_encode(['found' => false]);
            return;
        }

        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $vehicle = (new DataflowClient())->lookup($plate);

        echo json_encode(['found' => $vehicle !== null, 'vehicle' => $vehicle]);
    }

    /** Recebe o formulario passo a passo do veiculo, atualiza o Lead da sessao e calcula o
     *  orcamento (produto aproximado pela marca + vendedor mais proximo, se houver). */
    public function submitOrcamento(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/comprar?erro=csrf');
        }

        if (empty($_SESSION['checkout_lead_id'])) {
            Router::redirect('/comprar');
        }

        $name = trim($_POST['name'] ?? '');
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $year = trim($_POST['year'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $power = trim($_POST['power'] ?? '');
        $ecuStatus = $_POST['ecu_status'] ?? '';
        $reprogrammedPower = trim($_POST['reprogrammed_power'] ?? '');
        $hasArla = $_POST['has_arla'] ?? '';

        $ecuValid = $ecuStatus === 'original' || ($ecuStatus === 'reprogramado' && $reprogrammedPower !== '');

        if ($name === '' || $plate === '' || $year === '' || $brand === '' || !$ecuValid || !in_array($hasArla, ['sim', 'nao'], true)) {
            Router::redirect('/comprar?erro=1');
        }

        Lead::updateVehicleInfo((int) $_SESSION['checkout_lead_id'], [
            'name' => $name,
            'plate' => $plate,
            'year' => $year,
            'brand' => $brand,
            'model' => $model,
            'power' => $power,
            'ecu_status' => $ecuStatus,
            'reprogrammed_power' => $ecuStatus === 'reprogramado' ? $reprogrammedPower : null,
            'has_arla' => $hasArla,
        ]);

        $_SESSION['checkout_name'] = $name;

        $product = Product::findByBrandKeyword($brand) ?? Product::cheapest();
        $seller = GeoMatch::nearestSeller($_SESSION['checkout_city'] ?? '');

        // Mesmo que o cliente nao chame o vendedor pelo WhatsApp, o orcamento ja foi gerado --
        // atribui o Lead ao Licenciado mais proximo pra ele aparecer no CRM/hierarquia dele (ate o
        // admin) e ser cobrado por um atendimento. So atribui se ainda nao tinha dono (ex: indicacao
        // por ?ref= de outro licenciado, que tem prioridade sobre o palpite geografico).
        $currentLead = Lead::find((int) $_SESSION['checkout_lead_id']);
        if ($seller && empty($currentLead['assigned_to_user_id'])) {
            Lead::assignTo((int) $_SESSION['checkout_lead_id'], (int) $seller['id']);
        }

        // O orcamento por placa ja e um orcamento de verdade, mesmo que o cliente nunca chame o
        // vendedor no WhatsApp -- precisa aparecer em /painel/orcamentos (Kanban/lista/CRM), nao so
        // como um Lead solto. Cria Cliente (sem email/CPF, que essa etapa publica nao coleta) +
        // Orcamento vinculado ao Lead (via lead_id), pra tela/CRM poderem mostrar cidade/veiculo
        // completos a partir do Lead sem duplicar esses dados na tabela de orcamentos.
        $quoteId = null;
        if ($product) {
            $clientId = Client::create([
                'name' => $name,
                'whatsapp' => $_SESSION['checkout_whatsapp'] ?? '',
                'city' => $_SESSION['checkout_city'] ?? '',
                'email' => '',
                'document' => '',
                'person_type' => 'fisica',
                'seller_id' => $seller['id'] ?? null,
            ]);

            $quoteId = Quote::create([
                'client_id' => $clientId,
                'lead_id' => (int) $_SESSION['checkout_lead_id'],
                'seller_id' => $seller['id'] ?? null,
                'status' => 'aberto',
                'quote_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+7 days')),
                'notes' => 'Gerado automaticamente pelo orçamento por placa no site (autoatendimento).',
            ], [
                ['product_id' => $product['id'], 'quantity' => 1, 'unit_price' => (float) $product['price_cash']],
            ]);
        }

        $_SESSION['orcamento_result'] = [
            'quote_id' => $quoteId,
            'name' => $name,
            'plate' => $plate,
            'year' => $year,
            'brand' => $brand,
            'model' => $model,
            'power' => $power,
            'ecu_status' => $ecuStatus,
            'reprogrammed_power' => $reprogrammedPower,
            'has_arla' => $hasArla,
            'product_name' => $product['name'] ?? null,
            'product_price' => $product['price_cash'] ?? null,
            'product_is_exact_match' => $product && stripos($product['name'], $brand) !== false,
            'seller_name' => $seller['name'] ?? null,
            'seller_whatsapp' => $seller['whatsapp'] ?? null,
        ];

        Router::redirect('/comprar/orcamento');
    }

    public function showOrcamento(): void
    {
        if (empty($_SESSION['orcamento_result'])) {
            Router::redirect('/comprar');
        }

        View::render('site/orcamento', [
            'result' => $_SESSION['orcamento_result'],
        ], 'site');
    }

    public function startCheckout(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/comprar?erro=csrf');
        }

        $name = trim($_POST['name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $city = trim($_POST['city'] ?? '');

        if ($name === '' || $whatsapp === '') {
            Router::redirect('/comprar?erro=1');
        }

        $ref = $this->trackReferral();

        $leadId = Lead::create([
            'name' => mb_substr($name, 0, 120),
            'whatsapp' => mb_substr($whatsapp, 0, 30),
            'city' => mb_substr($city, 0, 120),
            'truck_brand' => null,
            'message' => null,
            'source' => 'checkout',
        ]);

        if ($ref) {
            Lead::assignTo($leadId, $ref);
        }

        $_SESSION['checkout_lead_id'] = $leadId;
        $_SESSION['checkout_name'] = $name;
        $_SESSION['checkout_whatsapp'] = $whatsapp;
        $_SESSION['checkout_city'] = $city;

        Router::redirect('/comprar');
    }

    public function checkout(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/comprar?erro=csrf');
        }

        $name = trim($_POST['name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $document = trim($_POST['document'] ?? '');
        $productId = (int) ($_POST['product_id'] ?? 0);
        $billingType = in_array($_POST['billing_type'] ?? '', ['PIX', 'BOLETO', 'CREDIT_CARD'], true)
            ? $_POST['billing_type']
            : 'PIX';
        $installments = $billingType === 'CREDIT_CARD' ? max(1, min(12, (int) ($_POST['installments'] ?? 1))) : 1;

        $product = Product::find($productId);

        if ($name === '' || $whatsapp === '' || $document === '' || !$product) {
            Router::redirect('/comprar?erro=1');
        }

        $ref = $this->trackReferral();

        $documentDigits = preg_replace('/\D/', '', $document);

        $clientId = Client::create([
            'name' => $name,
            'whatsapp' => $whatsapp,
            'city' => $city,
            'email' => $email,
            'document' => $document,
            'person_type' => strlen($documentDigits) > 11 ? 'juridica' : 'fisica',
            'seller_id' => $ref,
        ]);

        $basePrice = (float) $product['price_cash'];

        $orderId = Order::create([
            'client_id' => $clientId,
            'seller_id' => $ref,
            'order_date' => date('Y-m-d'),
            'notes' => 'Pedido via checkout público' . ($ref ? " (indicação #{$ref})" : ''),
        ], [
            ['product_id' => $productId, 'quantity' => 1, 'unit_price' => $basePrice],
        ]);

        $chargeAmount = $billingType === 'CREDIT_CARD'
            ? CardPricing::chargeAmount($basePrice, $installments)
            : $basePrice;

        try {
            $this->generatePublicCharge($orderId, $clientId, $chargeAmount, $billingType, $installments, $product['name']);
        } catch (\Throwable $e) {
            Router::redirect('/comprar?erro_cobranca=' . urlencode($e->getMessage()));
        }
    }

    /** Gera a cobrança e redireciona direto pro invoiceUrl hospedado da Asaas — nenhum dado de
     *  cartão passa pelo nosso servidor em nenhum momento. */
    private function generatePublicCharge(int $orderId, int $clientId, float $amount, string $billingType, int $installments, string $productName): void
    {
        $client = Client::find($clientId);
        if (!$client) {
            throw new \RuntimeException('Cliente não encontrado.');
        }

        $asaas = new AsaasClient();
        $customerId = $asaas->createOrFindCustomer($client);

        $dueDate = date('Y-m-d', strtotime('+3 days'));
        $charge = $asaas->createCharge([
            'customer' => $customerId,
            'billing_type' => $billingType,
            'value' => $amount,
            'installment_count' => $installments > 1 ? $installments : null,
            'due_date' => $dueDate,
            'description' => "Pedido #{$orderId} — {$productName} — Ecodiffusore Brasil",
            'external_reference' => 'order:' . $orderId,
        ]);

        Payment::create([
            'payable_type' => 'order',
            'payable_id' => $orderId,
            'asaas_customer_id' => $customerId,
            'asaas_charge_id' => $charge['id'],
            'method' => $billingType,
            'amount' => $amount,
            'checkout_url' => $charge['invoiceUrl'] ?? null,
            'pix_payload' => null,
            'due_date' => $dueDate,
        ]);

        unset($_SESSION['checkout_lead_id'], $_SESSION['checkout_name'], $_SESSION['checkout_whatsapp'], $_SESSION['checkout_city']);

        if (!empty($charge['invoiceUrl'])) {
            Router::redirect($charge['invoiceUrl']);
        }

        Router::redirect('/comprar?erro_cobranca=' . urlencode('Não foi possível gerar o link de pagamento.'));
    }

    /**
     * Le/grava o cookie de indicacao por Licenciado (?ref=<id>). Nunca confia no valor sem validar
     * contra User::find() -- o cookie pode ser forjado por qualquer visitante.
     */
    private function trackReferral(): ?int
    {
        $candidate = $_GET['ref'] ?? $_COOKIE[self::REF_COOKIE] ?? null;
        if (!$candidate || !ctype_digit((string) $candidate)) {
            return null;
        }

        $user = User::find((int) $candidate);
        if (!$user || $user['role_slug'] !== 'licenciado' || $user['status'] !== 'active') {
            return null;
        }

        if (isset($_GET['ref'])) {
            setcookie(self::REF_COOKIE, (string) $user['id'], time() + 30 * 86400, '/');
        }

        return (int) $user['id'];
    }
}
