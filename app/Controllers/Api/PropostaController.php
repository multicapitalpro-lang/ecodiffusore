<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\CardPricing;
use App\Core\EconomyCalculator;
use App\Core\Roles;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;

/**
 * "Proposta Fácil" pro app (Fase 85) -- mesma logica de App\Controllers\PropostaController::
 * store()/conclude(), sem a etapa de sessao (aqui e' tudo stateless: store() ja devolve o
 * resultado completo pronto pra tela, sem precisar de uma segunda chamada "show"). Sem PDF aqui
 * -- o vendedor compartilha o link publico do pedido depois de concluir, gerado igual ao painel.
 */
class PropostaController
{
    /** Dados fixos que o formulario precisa: piso por faixa, se e' vendedor (piso proprio
     *  diferente) e se mostra % de comissao do Licenciado. */
    public function options(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Proposta Facil.', 403);
        }

        ApiResponse::json([
            'is_view_only' => in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true),
            'is_vendedor' => $user['role_slug'] === Roles::SELLER,
            'vendor_floor' => PricingTier::VENDOR_STANDARD_PRICE,
            'show_commission' => $user['role_slug'] !== Roles::SELLER,
            'pricing_tiers' => array_map(fn ($t) => [
                'min_price' => (float) $t['min_price'],
                'max_price' => $t['max_price'] !== null ? (float) $t['max_price'] : null,
                'licenciado_commission_pct' => (float) $t['licenciado_commission_pct'],
            ], PricingTier::all()),
        ]);
    }

    public function store(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Proposta Facil.', 403);
        }
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            ApiResponse::error('Gerente e Supervisor tem acesso de visualizacao.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $errors = $this->validate($body, $user['role_slug']);
        if ($errors) {
            ApiResponse::json(['errors' => $errors], 422);
        }

        $name = trim($body['name']);
        $whatsapp = trim($body['whatsapp']);

        $duplicateClient = Client::findDuplicate(null, $whatsapp);
        if ($duplicateClient) {
            $licenciadoName = User::licenciadoNameFor($duplicateClient['seller_id'] ? (int) $duplicateClient['seller_id'] : null);
            $message = 'Esse numero de WhatsApp ja esta cadastrado como cliente'
                . ($duplicateClient['seller_name'] ? ' do vendedor ' . $duplicateClient['seller_name'] : '')
                . ($licenciadoName ? ' (rede do Licenciado ' . $licenciadoName . ')' : '')
                . '. Nao e possivel cadastrar o mesmo contato em outra rede.';
            ApiResponse::json(['errors' => ['_geral' => $message]], 422);
        }

        $plate = strtoupper(trim($body['plate'] ?? ''));
        $brand = trim($body['brand']);
        $model = trim($body['model'] ?? '');
        $year = trim($body['year']);
        $power = trim($body['power'] ?? '');
        $ecuStatus = $body['ecu_status'];
        $reprogrammedPower = trim($body['reprogrammed_power'] ?? '');
        $hasArla = $body['has_arla'];
        $hasTelemetry = $body['has_telemetry'];
        $qty = max(1, (int) $body['quantidade']);

        $kmMensal = self::toFloat($body['km_mensal'] ?? 0);
        $kmLitro = self::toFloat($body['km_litro'] ?? 0);
        $precoDiesel = self::toFloat($body['preco_diesel'] ?? 0);
        $unitPrice = self::toFloat($body['unit_price'] ?? 0);
        $totalPrice = $unitPrice * $qty;

        $product = Product::findByBrandKeyword($brand) ?? Product::cheapest();

        $payback = EconomyCalculator::estimate($kmMensal, $kmLitro, $precoDiesel, $totalPrice);
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
            for ($n = 1; $n <= CardPricing::maxInstallments(); $n++) {
                $installments[] = [
                    'n' => $n,
                    'total' => CardPricing::chargeAmount($totalPrice, $n),
                    'parcela' => CardPricing::installmentValue($totalPrice, $n),
                ];
            }
        }

        $sellerId = (int) $user['id'];
        $quoteId = null;
        $band = PricingTier::forPrice($unitPrice);

        if ($product && $unitPrice > 0) {
            $leadId = Lead::create([
                'name' => $name,
                'whatsapp' => $whatsapp,
                'city' => trim($body['city'] ?? ''),
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
                'has_telemetry' => $hasTelemetry,
                'km_mensal' => $kmMensal,
                'km_litro' => $kmLitro,
                'preco_diesel' => $precoDiesel,
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
                'notes' => 'Gerado pela Proposta Facil no app. Economia media estimada: R$ '
                    . number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') . '/mes.',
            ], [
                ['product_id' => $product['id'], 'quantity' => $qty, 'unit_price' => $unitPrice],
            ]);

            Approval::checkAndRequest('quote', $quoteId, [['product_id' => $product['id'], 'quantity' => $qty, 'unit_price' => $unitPrice]], $sellerId, $sellerId, trim($body['motivo_desconto'] ?? '') ?: null);
        }

        ApiResponse::json([
            'result' => [
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
                'has_telemetry' => $hasTelemetry,
                'km_mensal' => $kmMensal,
                'km_litro' => $kmLitro,
                'preco_diesel' => $precoDiesel,
                'quantidade' => $qty,
                'unit_price' => $unitPrice ?: null,
                'licenciado_commission_pct' => $band['licenciado_commission_pct'] ?? null,
                'product_name' => $product['name'] ?? null,
                'product_price' => $totalPrice ?: null,
                'payback' => $payback,
                'installments' => $installments,
            ],
        ], 201);
    }

    /** "Concluir Pedido" -- mesma logica de App\Controllers\PropostaController::conclude(). */
    public function conclude(string $quoteId): void
    {
        $user = ApiAuth::requireUser();
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            ApiResponse::error('Gerente e Supervisor tem acesso de visualizacao.', 403);
        }

        $quoteId = (int) $quoteId;
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $document = preg_replace('/\D/', '', (string) ($body['document'] ?? ''));
        if ($document !== '' && !in_array(strlen($document), [11, 14], true)) {
            ApiResponse::error('CPF precisa ter 11 digitos ou CNPJ 14 digitos (ou deixe em branco).', 422);
        }

        $quote = Quote::find($quoteId);
        if (!$quote || (int) $quote['seller_id'] !== (int) $user['id'] || $quote['status'] === 'convertido') {
            ApiResponse::error('Orcamento nao encontrado ou ja concluido.', 404);
        }

        $items = QuoteItem::forQuote($quoteId);
        $lowestPrice = $items ? (float) min(array_column($items, 'unit_price')) : 0.0;
        $block = Approval::blocksCompletion('quote', $quoteId, $lowestPrice);
        if ($block) {
            $msg = $block['status'] === 'recusado'
                ? 'A liberacao de preco desse orcamento foi recusada. Ajuste o preco ou peca uma nova liberacao.'
                : 'Esse orcamento esta com o preco aguardando aprovacao.';
            ApiResponse::error($msg, 422);
        }

        if ($document !== '') {
            Client::updateDocument((int) $quote['client_id'], $document);
        }

        $orderId = Quote::convertToOrder($quoteId);
        AuditLog::record((int) $user['id'], 'orcamento_convertido', 'quote', $quoteId, ['status' => $quote['status']], ['order_id' => $orderId]);

        $order = Order::find($orderId);
        ApiResponse::json([
            'order_id' => $orderId,
            'public_link' => !empty($order['public_token']) ? "https://ecodiffusorebrasil.com.br/pedido/{$order['public_token']}" : null,
        ]);
    }

    private function validate(array $body, string $role): array
    {
        $errors = [];

        if (trim($body['name'] ?? '') === '') {
            $errors['name'] = 'Informe o nome.';
        }
        if (trim($body['whatsapp'] ?? '') === '') {
            $errors['whatsapp'] = 'Informe o WhatsApp.';
        }
        if (trim($body['brand'] ?? '') === '') {
            $errors['brand'] = 'Informe a marca.';
        }
        if (trim($body['year'] ?? '') === '') {
            $errors['year'] = 'Informe o ano.';
        }
        if (!in_array($body['ecu_status'] ?? '', ['original', 'reprogramado'], true)) {
            $errors['ecu_status'] = 'Selecione uma opcao.';
        }
        if (!in_array($body['has_arla'] ?? '', ['sim', 'nao'], true)) {
            $errors['has_arla'] = 'Selecione uma opcao.';
        }
        if (!in_array($body['has_telemetry'] ?? '', ['sim', 'nao'], true)) {
            $errors['has_telemetry'] = 'Selecione uma opcao.';
        }
        if (!is_numeric($body['quantidade'] ?? '') || (int) $body['quantidade'] < 1) {
            $errors['quantidade'] = 'Informe pelo menos 1 placa.';
        }

        $unitPrice = self::toFloat($body['unit_price'] ?? 0);
        if ($unitPrice <= 0) {
            $errors['unit_price'] = 'Informe o preco negociado por unidade.';
        } elseif (!PricingTier::forPrice($unitPrice)) {
            $floorTier = PricingTier::all()[0] ?? null;
            $floor = $floorTier ? number_format((float) $floorTier['min_price'], 2, ',', '.') : '0,00';
            $errors['unit_price'] = "Preco abaixo do minimo negociavel (R$ {$floor}).";
        }

        if ($role === Roles::SELLER && $unitPrice > 0 && $unitPrice < PricingTier::VENDOR_STANDARD_PRICE
            && trim($body['motivo_desconto'] ?? '') === '') {
            $errors['motivo_desconto'] = 'Explique o motivo do preco abaixo do padrao.';
        }

        if (self::toFloat($body['km_mensal'] ?? 0) <= 0) {
            $errors['km_mensal'] = 'Informe um valor valido.';
        }
        if (self::toFloat($body['km_litro'] ?? 0) <= 0) {
            $errors['km_litro'] = 'Informe um valor valido.';
        }
        if (self::toFloat($body['preco_diesel'] ?? 0) <= 0) {
            $errors['preco_diesel'] = 'Informe um valor valido.';
        }

        return $errors;
    }

    /** O app manda numero puro (o teclado numerico do celular ja usa ponto decimal), mas aceita
     *  tambem string com virgula caso venha de um input de texto livre. */
    private static function toFloat($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $value = trim((string) $value);
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
