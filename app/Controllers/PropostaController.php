<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\EconomyCalculator;
use App\Core\CardPricing;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quote;

/**
 * "Proposta Fácil": ferramenta interna pro Vendedor/Licenciado gerar um orçamento completo do
 * interessado em um único formulário, reaproveitando o mesmo motor de calculo (EconomyCalculator/
 * CardPricing/Product) já usado no orçamento por placa público (PublicController::submitOrcamento).
 * Diferença chave: aqui o vendedor logado É o seller_id (nunca GeoMatch -- é o prospect dele, não um
 * palpite geográfico), e o resultado fica disponível também em PDF/WhatsApp pro vendedor compartilhar.
 */
class PropostaController
{
    private const ALLOWED_ROLES = [Roles::SELLER, Roles::REGIONAL_OWNER];

    /** Parcelas mostradas na tabela de pagamento no cartão. A tabela de juros real pro parcelamento
     *  "próprio" (fora do cartão) ainda não foi passada pelo cliente -- usamos CardPricing (mesma
     *  regra já usada no checkout público) como valor provisório, sinalizado na tela e no PDF. */
    private const INSTALLMENT_OPTIONS = [1, 2, 3, 6, 10, 12];

    public function create(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        View::render('painel/proposta/form', [
            'user' => Auth::user(),
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/proposta-facil?erro=csrf');
        }

        $name = trim($_POST['name'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year = trim($_POST['year'] ?? '');
        $power = trim($_POST['power'] ?? '');
        $ecuStatus = $_POST['ecu_status'] ?? '';
        $reprogrammedPower = trim($_POST['reprogrammed_power'] ?? '');
        $hasArla = $_POST['has_arla'] ?? '';

        $kmMensal = self::parseBrNumber($_POST['km_mensal'] ?? '');
        $kmLitro = self::parseBrNumber($_POST['km_litro'] ?? '');
        $precoDiesel = self::parseBrNumber($_POST['preco_diesel'] ?? '');

        $ecuValid = $ecuStatus === 'original' || ($ecuStatus === 'reprogramado' && $reprogrammedPower !== '');

        if ($name === '' || $whatsapp === '' || $brand === '' || $year === '' || !$ecuValid
            || !in_array($hasArla, ['sim', 'nao'], true) || $kmMensal <= 0 || $kmLitro <= 0 || $precoDiesel <= 0) {
            Router::redirect('/painel/proposta-facil?erro=1');
        }

        $product = Product::findByBrandKeyword($brand) ?? Product::cheapest();
        $productPrice = (float) ($product['price_cash'] ?? 0);
        $payback = EconomyCalculator::estimate($kmMensal, $kmLitro, $precoDiesel, $productPrice);

        $installments = [];
        if ($productPrice > 0) {
            foreach (self::INSTALLMENT_OPTIONS as $n) {
                $installments[] = [
                    'n' => $n,
                    'total' => CardPricing::chargeAmount($productPrice, $n),
                    'parcela' => CardPricing::installmentValue($productPrice, $n),
                ];
            }
        }

        $sellerId = (int) $user['id'];
        $quoteId = null;

        if ($product) {
            $leadId = Lead::create([
                'name' => $name,
                'whatsapp' => $whatsapp,
                'city' => '',
                'truck_brand' => $brand,
                'message' => null,
                'source' => 'proposta_facil',
            ]);
            Lead::assignTo($leadId, $sellerId);
            Lead::updateVehicleInfo($leadId, [
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

            $clientId = Client::create([
                'name' => $name,
                'whatsapp' => $whatsapp,
                'email' => '',
                'document' => '',
                'person_type' => 'fisica',
                'seller_id' => $sellerId,
            ]);

            $quoteId = Quote::create([
                'client_id' => $clientId,
                'lead_id' => $leadId,
                'seller_id' => $sellerId,
                'status' => 'aberto',
                'quote_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+7 days')),
                'notes' => 'Gerado pela Proposta Fácil no painel. Economia média estimada: R$ '
                    . number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') . '/mês.',
            ], [
                ['product_id' => $product['id'], 'quantity' => 1, 'unit_price' => $productPrice],
            ]);
        }

        $_SESSION['proposta_result'] = [
            'quote_id' => $quoteId,
            'seller_name' => $user['name'],
            'name' => $name,
            'whatsapp' => $whatsapp,
            'plate' => $plate,
            'year' => $year,
            'brand' => $brand,
            'model' => $model,
            'power' => $power,
            'ecu_status' => $ecuStatus,
            'reprogrammed_power' => $reprogrammedPower,
            'has_arla' => $hasArla,
            'km_mensal' => $kmMensal,
            'km_litro' => $kmLitro,
            'preco_diesel' => $precoDiesel,
            'product_name' => $product['name'] ?? null,
            'product_price' => $productPrice ?: null,
            'product_is_exact_match' => $product && stripos($product['name'], $brand) !== false,
            'payback' => $payback,
            'installments' => $installments,
        ];

        Router::redirect('/painel/proposta-facil/resultado');
    }

    public function show(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (empty($_SESSION['proposta_result'])) {
            Router::redirect('/painel/proposta-facil');
        }

        View::render('painel/proposta/resultado', [
            'user' => Auth::user(),
            'result' => $_SESSION['proposta_result'],
        ]);
    }

    public function pdf(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (empty($_SESSION['proposta_result'])) {
            Router::redirect('/painel/proposta-facil');
        }

        $result = $_SESSION['proposta_result'];

        ob_start();
        View::render('painel/proposta/proposta_pdf', ['result' => $result], null);
        $html = ob_get_clean();

        Pdf::download($html, 'proposta-ecodiffusore-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $result['name'])) . '.pdf', 'portrait');
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
}
