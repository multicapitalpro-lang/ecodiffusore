<?php

namespace App\Controllers;

use App\Core\AsaasClient;
use App\Core\CardPricing;
use App\Core\Csrf;
use App\Core\DataflowClient;
use App\Core\EconomyCalculator;
use App\Core\GeoMatch;
use App\Core\Notifier;
use App\Core\Pdf;
use App\Core\Router;
use App\Core\VehicleCatalog;
use App\Core\View;
use App\Models\BrCity;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadRoutingSettings;
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

        // Cidade precisa ser um municipio brasileiro real (selecionado do autocomplete) -- texto
        // livre (bairro, cidade de outro pais, erro de digitacao) quebra o roteamento por
        // proximidade que depende dela mais adiante no funil (GeoMatch).
        if ($city !== '' && !BrCity::exists($city)) {
            Router::redirect('/?erro=cidade#contato');
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

        $kmMensal = self::parseBrNumber($_POST['km_mensal'] ?? '');
        $kmLitro = self::parseBrNumber($_POST['km_litro'] ?? '');
        $precoDiesel = self::parseBrNumber($_POST['preco_diesel'] ?? '');

        $ecuValid = $ecuStatus === 'original' || ($ecuStatus === 'reprogramado' && $reprogrammedPower !== '');

        if ($name === '' || $plate === '' || $year === '' || $brand === '' || !$ecuValid || !in_array($hasArla, ['sim', 'nao'], true)
            || $kmMensal <= 0 || $kmLitro <= 0 || $precoDiesel <= 0) {
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
        $productPrice = (float) ($product['price_cash'] ?? 0);
        $payback = EconomyCalculator::estimate($kmMensal, $kmLitro, $precoDiesel, $productPrice);

        // Sem Vendedor no raio de 100km, o dono do Lead/Cliente/Orcamento cai pro Licenciado
        // Central (configuravel em /painel/configuracoes/roteamento) -- antes ficava sem
        // responsavel e aparecia pra QUALQUER Licenciado do pais poder pegar (vazamento entre
        // redes). O cliente continua vendo o WhatsApp central na tela ($seller fica null pra
        // isso -- ver orcamento_result abaixo), essa fila e' so pro dono interno no CRM.
        $ownerId = $seller['id'] ?? LeadRoutingSettings::centralLicenciadoId();

        // Mesmo WhatsApp que ja e' cliente de alguem tem prioridade sobre o palpite geografico de
        // agora -- "nao podemos ter o mesmo lead em dois CRMs diferentes" (pedido do usuario):
        // quem capturou primeiro mantem, o resto (Lead/Orcamento novo) segue o MESMO dono.
        $existingClient = Client::findDuplicate(null, $_SESSION['checkout_whatsapp'] ?? '');
        if ($existingClient && $existingClient['seller_id']) {
            $ownerId = (int) $existingClient['seller_id'];
        }

        // Mesmo que o cliente nao chame o vendedor pelo WhatsApp, o orcamento ja foi gerado --
        // atribui o Lead ao Vendedor/Licenciado Central pra ele aparecer no CRM/hierarquia
        // certa (ate o admin). So atribui se ainda nao tinha dono (ex: indicacao por ?ref= de
        // outro licenciado, que tem prioridade sobre o palpite geografico).
        $currentLead = Lead::find((int) $_SESSION['checkout_lead_id']);
        if ($ownerId && empty($currentLead['assigned_to_user_id'])) {
            Lead::assignTo((int) $_SESSION['checkout_lead_id'], $ownerId);
            Notifier::leadRoteado($currentLead, $ownerId);
        }

        // O orcamento por placa ja e um orcamento de verdade, mesmo que o cliente nunca chame o
        // vendedor no WhatsApp -- precisa aparecer em /painel/orcamentos (Kanban/lista/CRM), nao so
        // como um Lead solto. Reaproveita o Cliente existente (mesmo WhatsApp) se houver, em vez
        // de duplicar, + cria Orcamento vinculado ao Lead (via lead_id), pra tela/CRM poderem
        // mostrar cidade/veiculo completos a partir do Lead sem duplicar esses dados na tabela.
        $quoteId = null;
        if ($product) {
            $clientId = $existingClient ? (int) $existingClient['id'] : Client::create([
                'name' => $name,
                'whatsapp' => $_SESSION['checkout_whatsapp'] ?? '',
                'city' => $_SESSION['checkout_city'] ?? '',
                'email' => '',
                'document' => '',
                'person_type' => 'fisica',
                'seller_id' => $ownerId,
            ]);

            $quoteId = Quote::create([
                'client_id' => $clientId,
                'lead_id' => (int) $_SESSION['checkout_lead_id'],
                'seller_id' => $ownerId,
                'status' => 'aberto',
                'quote_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+7 days')),
                'notes' => 'Gerado automaticamente pelo orçamento por placa no site (autoatendimento). '
                    . 'Economia média estimada: R$ ' . number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') . '/mês.',
            ], [
                ['product_id' => $product['id'], 'quantity' => 1, 'unit_price' => (float) $product['price_cash']],
            ]);

            $quote = Quote::find($quoteId);
            if ($quote) {
                Notifier::orcamentoRealizado($quote);
            }
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
            'payback' => $payback,
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

    public function downloadOrcamentoPdf(): void
    {
        if (empty($_SESSION['orcamento_result'])) {
            Router::redirect('/comprar');
        }

        $result = $_SESSION['orcamento_result'];

        ob_start();
        View::render('site/orcamento_pdf', ['result' => $result], null);
        $html = ob_get_clean();

        Pdf::download($html, 'orcamento-ecodiffusore-' . strtolower($result['plate']) . '.pdf', 'portrait');
    }

    /** Aceita tanto "12.000"/"12000" quanto "2,8"/"6,10" (formato BR com virgula decimal). */
    private static function parseBrNumber(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }
        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        return (float) $value;
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

        // Cidade precisa ser um municipio brasileiro real (selecionado do autocomplete) -- e' o
        // dado que GeoMatch usa pra achar o Vendedor mais proximo; texto livre (bairro, cidade de
        // outro pais, erro de digitacao) faz o roteamento nunca encontrar ninguem no raio.
        if ($city !== '' && !BrCity::exists($city)) {
            Router::redirect('/comprar?erro=cidade');
        }

        $ref = $this->trackReferral();

        // Mesmo WhatsApp que ja preencheu o popup antes reaproveita o Lead existente (com o dono
        // que ja tinha, se tiver) em vez de criar outro -- "nao podemos ter o mesmo lead em dois
        // CRMs diferentes" (pedido do usuario). Nome/cidade sao atualizados pro que a pessoa
        // informou agora, caso tenha mudado.
        $existingLead = Lead::findByWhatsapp($whatsapp);
        if ($existingLead) {
            $leadId = (int) $existingLead['id'];
        } else {
            $leadId = Lead::create([
                'name' => mb_substr($name, 0, 120),
                'whatsapp' => mb_substr($whatsapp, 0, 30),
                'city' => mb_substr($city, 0, 120),
                'truck_brand' => null,
                'message' => null,
                'source' => 'checkout',
            ]);
        }

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
        $installments = $billingType === 'CREDIT_CARD' ? max(1, min(CardPricing::maxInstallments(), (int) ($_POST['installments'] ?? 1))) : 1;

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
     * Le/grava o cookie de indicacao por Licenciado OU Vendedor (?ref=<id>) -- link pessoal que
     * qualquer um dos dois pode mandar pro interessado preencher, ficando salvo no CRM daquele
     * vendedor especifico mesmo que o cliente seja de outro estado/fora do raio do GeoMatch
     * (pedido explicito do usuario: "todo vendedor precisa de um link ref especifico pra controle
     * interno do CRM dele"). Nunca confia no valor sem validar contra User::find() -- o cookie
     * pode ser forjado por qualquer visitante.
     */
    private function trackReferral(): ?int
    {
        $candidate = $_GET['ref'] ?? $_COOKIE[self::REF_COOKIE] ?? null;
        if (!$candidate || !ctype_digit((string) $candidate)) {
            return null;
        }

        $user = User::find((int) $candidate);
        if (!$user || !in_array($user['role_slug'], ['licenciado', 'vendedor'], true) || $user['status'] !== 'active') {
            return null;
        }

        if (isset($_GET['ref'])) {
            setcookie(self::REF_COOKIE, (string) $user['id'], time() + 30 * 86400, '/');
        }

        return (int) $user['id'];
    }
}
