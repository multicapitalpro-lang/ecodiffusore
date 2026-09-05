<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\EconomyCalculator;
use App\Core\CardPricing;
use App\Core\Pdf;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;
use App\Models\Lead;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Quote;

/**
 * "Proposta Fácil": ferramenta interna pro Vendedor/Licenciado gerar um orçamento completo do
 * interessado em um único formulário, reaproveitando o mesmo motor de calculo (EconomyCalculator/
 * CardPricing) já usado no orçamento por placa público (PublicController::submitOrcamento).
 * Diferença chave: aqui o vendedor logado É o seller_id (nunca GeoMatch -- é o prospect dele, não um
 * palpite geográfico), e o resultado fica disponível também em PDF/WhatsApp pro vendedor compartilhar.
 * Preço (Fase 24): vem da tabela de preco por quantidade (PricingTier::forQuantity), nao mais de
 * 2 opcoes fixas por produto -- o Produto so entra pra identificar nome/id pro item do Orcamento.
 * Acesso: todo STAFF ve o botao no header, mas Gerente/Supervisor sao papel de suporte nacional
 * (Roles::NATIONAL_SUPPORT) e so visualizam -- mesmo tratamento view-only ja usado em
 * OrderController/QuoteController, nunca criam Pedido/Orcamento de verdade.
 */
class PropostaController
{
    /** Parcelas mostradas na tabela de pagamento no cartão. A tabela de juros real pro parcelamento
     *  "próprio" (fora do cartão) ainda não foi passada pelo cliente -- usamos CardPricing (mesma
     *  regra já usada no checkout público) como valor provisório, sinalizado na tela e no PDF. */
    private const INSTALLMENT_OPTIONS = [1, 2, 3, 6, 10, 12];

    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $isFragment = isset($_GET['fragment']);

        View::render('painel/proposta/form', [
            'user' => $user,
            'isModal' => $isFragment,
            'isViewOnly' => in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true),
            'pricingTiers' => PricingTier::all(),
        ], $isFragment ? null : 'painel');
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $this->assertNotViewOnly($user);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['_geral' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/proposta-facil?erro=csrf');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            Router::redirect('/painel/proposta-facil?erro=1');
        }

        $name = trim($_POST['name']);
        $whatsapp = trim($_POST['whatsapp']);
        $plate = strtoupper(trim($_POST['plate'] ?? ''));
        $brand = trim($_POST['brand']);
        $model = trim($_POST['model'] ?? '');
        $year = trim($_POST['year']);
        $power = trim($_POST['power'] ?? '');
        $ecuStatus = $_POST['ecu_status'];
        $reprogrammedPower = trim($_POST['reprogrammed_power'] ?? '');
        $hasArla = $_POST['has_arla'];
        $qty = max(1, (int) $_POST['quantidade']);

        $kmMensal = self::parseBrNumber($_POST['km_mensal']);
        $kmLitro = self::parseBrNumber($_POST['km_litro']);
        $precoDiesel = self::parseBrNumber($_POST['preco_diesel']);

        $tier = PricingTier::forQuantity($qty);
        $unitPrice = $tier ? (float) $tier['unit_price'] : 0.0;
        $totalPrice = $unitPrice * $qty;

        $product = Product::findByBrandKeyword($brand) ?? Product::cheapest();

        $payback = EconomyCalculator::estimate($kmMensal, $kmLitro, $precoDiesel, $totalPrice);
        // EconomyCalculator assume UM veiculo (o km/consumo informado e de 1 caminhao); com mais de
        // 1 placa, a economia total (e por isso o payback) escala pela quantidade -- ajuste feito
        // aqui no controller pra nao mexer no calculo por-veiculo em si (EconomyCalculator).
        if ($qty > 1 && $payback['tiers']['avg']['monthly'] > 0) {
            $avgMonthlyFleet = $payback['tiers']['avg']['monthly'] * $qty;
            $payback['payback_months'] = $totalPrice > 0 ? $totalPrice / $avgMonthlyFleet : null;
            foreach ($payback['yearly_breakdown'] as &$row) {
                $row['cumulative_savings'] = $avgMonthlyFleet * 12 * $row['year'];
                $row['net_gain'] = $row['cumulative_savings'] - $totalPrice;
            }
            unset($row);
        }

        $installments = [];
        if ($totalPrice > 0) {
            foreach (self::INSTALLMENT_OPTIONS as $n) {
                $installments[] = [
                    'n' => $n,
                    'total' => CardPricing::chargeAmount($totalPrice, $n),
                    'parcela' => CardPricing::installmentValue($totalPrice, $n),
                ];
            }
        }

        $sellerId = (int) $user['id'];
        $quoteId = null;

        if ($product && $tier) {
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
                ['product_id' => $product['id'], 'quantity' => $qty, 'unit_price' => $unitPrice],
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
            'quantidade' => $qty,
            'unit_price' => $unitPrice ?: null,
            'product_name' => $product['name'] ?? null,
            'product_price' => $totalPrice ?: null,
            'payback' => $payback,
            'installments' => $installments,
        ];

        $target = '/painel/proposta-facil/resultado';

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => $target]);
        }

        Router::redirect($target);
    }

    public function show(): void
    {
        Auth::requireRole(Roles::STAFF);
        $isFragment = isset($_GET['fragment']);

        if (empty($_SESSION['proposta_result'])) {
            if ($isFragment) {
                echo '<div class="modal-header"><h2>Proposta Fácil</h2>'
                    . '<button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>'
                    . '<div class="modal-body"><p class="form-msg form-msg-erro">Sessão expirada. Feche e tente de novo.</p></div>';
                return;
            }
            Router::redirect('/painel/proposta-facil');
        }

        View::render('painel/proposta/resultado', [
            'user' => Auth::user(),
            'result' => $_SESSION['proposta_result'],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function pdf(): void
    {
        Auth::requireRole(Roles::STAFF);

        if (empty($_SESSION['proposta_result'])) {
            Router::redirect('/painel/proposta-facil');
        }

        $result = $_SESSION['proposta_result'];

        ob_start();
        View::render('painel/proposta/proposta_pdf', ['result' => $result], null);
        $html = ob_get_clean();

        Pdf::download($html, 'proposta-ecodiffusore-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $result['name'])) . '.pdf', 'portrait');
    }

    /** Gerente/Supervisor sao papel de suporte nacional -- so visualizam, nunca geram proposta de
     *  verdade (mesmo tratamento de OrderController::assertNotViewOnly/QuoteController). */
    private function assertNotViewOnly(array $user): void
    {
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['_geral' => 'Gerente e Supervisor têm acesso de visualização.']]);
            }
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }

    private function validate(array $post): array
    {
        $errors = [];

        if (trim($post['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome.';
        }
        if (trim($post['whatsapp'] ?? '') === '') {
            $errors['whatsapp'] = 'Informe o WhatsApp.';
        }
        if (trim($post['brand'] ?? '') === '') {
            $errors['brand'] = 'Informe a marca.';
        }
        if (trim($post['year'] ?? '') === '') {
            $errors['year'] = 'Informe o ano.';
        }

        $ecuStatus = $post['ecu_status'] ?? '';
        if (!in_array($ecuStatus, ['original', 'reprogramado'], true)) {
            $errors['ecu_status'] = 'Selecione uma opção.';
        } elseif ($ecuStatus === 'reprogramado' && trim($post['reprogrammed_power'] ?? '') === '') {
            $errors['reprogrammed_power'] = 'Informe a potência reprogramada.';
        }

        if (!in_array($post['has_arla'] ?? '', ['sim', 'nao'], true)) {
            $errors['has_arla'] = 'Selecione uma opção.';
        }
        if (!is_numeric($post['quantidade'] ?? '') || (int) $post['quantidade'] < 1) {
            $errors['quantidade'] = 'Informe pelo menos 1 placa.';
        }
        if (self::parseBrNumber($post['km_mensal'] ?? '') <= 0) {
            $errors['km_mensal'] = 'Informe um valor válido.';
        }
        if (self::parseBrNumber($post['km_litro'] ?? '') <= 0) {
            $errors['km_litro'] = 'Informe um valor válido.';
        }
        if (self::parseBrNumber($post['preco_diesel'] ?? '') <= 0) {
            $errors['preco_diesel'] = 'Informe um valor válido.';
        }

        return $errors;
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
