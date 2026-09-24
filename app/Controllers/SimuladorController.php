<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Chart;
use App\Core\Csrf;
use App\Core\EconomyCalculator;
use App\Core\ExchangeRateClient;
use App\Core\Money;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\View;
use App\Models\Product;
use App\Models\User;

/**
 * Simulador de economia como ferramenta ATIVA do Vendedor -- o mesmo motor de calculo que ja
 * roda escondido dentro do fluxo publico de orcamento por placa (App\Core\EconomyCalculator),
 * exposto aqui pra ele preencher na frente do cliente (call, WhatsApp, visita) e gerar um
 * resultado pra mostrar/mandar na hora, em vez de so acontecer automatico no site.
 */
class SimuladorController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $secondaryCurrency = User::secondaryCurrencyForUser((int) $user['id']);

        View::render('painel/simulador/index', [
            'user' => $user,
            'products' => Product::all(true),
            'result' => null,
            'values' => [],
            'errors' => [],
            'secondaryCurrency' => $secondaryCurrency,
            'rate' => $secondaryCurrency ? ExchangeRateClient::brlToPyg() : 0.0,
        ]);
    }

    public function calcular(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $secondaryCurrency = User::secondaryCurrencyForUser((int) $user['id']);
        $rate = $secondaryCurrency ? ExchangeRateClient::brlToPyg() : 0.0;

        [$values, $errors, $productPrice, $productName] = $this->parseAndValidate($_POST, $secondaryCurrency, $rate);

        $result = null;
        $chartSvg = null;
        if (!$errors) {
            $result = EconomyCalculator::estimate(
                (float) $values['km_mensal'],
                (float) $values['km_litro'],
                (float) $values['preco_diesel_brl'],
                $productPrice
            );

            // Comparativo visual das 3 faixas -- so numero em tabela e' menos convincente na hora
            // de mostrar pro cliente do que uma barra que ele bate o olho e ja entende a diferenca.
            // Fase 111: passa um formatador proprio quando ha' moeda secundaria, senao o grafico
            // (Chart::bar() usa R$ por padrao) ficaria com "R$" mesmo com Guarani selecionado.
            $chartCurrency = $values['currency'];
            $chartValueFormatter = $chartCurrency !== 'BRL'
                ? fn (float $v) => Money::compact($v, $chartCurrency, $rate)
                : null;
            $chartSvg = Chart::bar([
                ['label' => '5% — Mínimo garantido', 'value' => $result['tiers']['min']['monthly']],
                ['label' => '8% — Média real', 'value' => $result['tiers']['avg']['monthly']],
                ['label' => '12% — Potencial máximo', 'value' => $result['tiers']['max']['monthly']],
            ], 10, $chartValueFormatter);
        }

        View::render('painel/simulador/index', [
            'user' => $user,
            'products' => Product::all(true),
            'result' => $result,
            'chartSvg' => $chartSvg,
            'productPrice' => $productPrice,
            'productName' => $productName,
            'values' => $values,
            'errors' => $errors,
            'secondaryCurrency' => $secondaryCurrency,
            'rate' => $rate,
        ]);
    }

    public function downloadPdf(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $secondaryCurrency = User::secondaryCurrencyForUser((int) $user['id']);
        $rate = $secondaryCurrency ? ExchangeRateClient::brlToPyg() : 0.0;

        [$values, $errors, $productPrice, $productName] = $this->parseAndValidate($_POST, $secondaryCurrency, $rate);

        if ($errors) {
            View::render('painel/simulador/index', [
                'user' => $user,
                'products' => Product::all(true),
                'result' => null,
                'values' => $values,
                'errors' => $errors,
                'secondaryCurrency' => $secondaryCurrency,
                'rate' => $rate,
            ]);
            return;
        }

        $result = EconomyCalculator::estimate(
            (float) $values['km_mensal'],
            (float) $values['km_litro'],
            (float) $values['preco_diesel_brl'],
            $productPrice
        );

        ob_start();
        View::render('painel/simulador/pdf', [
            'clientName' => $values['client_name'],
            'productName' => $productName,
            'productPrice' => $productPrice,
            'result' => $result,
            'sellerName' => Auth::user()['name'],
            'currency' => $values['currency'],
            'rate' => $rate,
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'simulacao-economia-ecodiffusore.pdf', 'portrait');
    }

    /** Fase 111: $secondaryCurrency/$rate so' vem preenchido pro Licenciado (e rede) com
     *  operacao fora do Brasil -- preco_diesel e' digitado NA MOEDA ESCOLHIDA (campo 'currency'
     *  do POST), convertido aqui pra R$ (preco_diesel_brl) antes de qualquer calculo, ja que
     *  EconomyCalculator so' entende R$. Sem isso configurado, currency fica sempre 'BRL' e a
     *  conversao e' um no-op (Money::toBrl() devolve o mesmo valor).
     *  @return array{0: array, 1: array, 2: float, 3: ?string} */
    private function parseAndValidate(array $input, ?string $secondaryCurrency = null, float $rate = 0.0): array
    {
        if (!Csrf::verify($input['csrf_token'] ?? null)) {
            return [[], ['geral' => 'Sessão expirada, recarregue a página.'], 0.0, null];
        }

        $currency = ($secondaryCurrency && ($input['currency'] ?? 'BRL') === $secondaryCurrency) ? $secondaryCurrency : 'BRL';
        $precoDieselInput = self::parseBrNumber($input['preco_diesel'] ?? '');

        $values = [
            'client_name' => trim($input['client_name'] ?? ''),
            'client_whatsapp' => trim($input['client_whatsapp'] ?? ''),
            'product_id' => trim($input['product_id'] ?? ''),
            'manual_price' => trim($input['manual_price'] ?? ''),
            'km_mensal' => self::parseBrNumber($input['km_mensal'] ?? ''),
            'km_litro' => self::parseBrNumber($input['km_litro'] ?? ''),
            'currency' => $currency,
            'preco_diesel' => $precoDieselInput,
            'preco_diesel_brl' => Money::toBrl($precoDieselInput, $currency, $rate),
        ];

        $errors = [];
        if ($values['km_mensal'] <= 0) {
            $errors['km_mensal'] = 'Informe a quilometragem mensal.';
        }
        if ($values['km_litro'] <= 0) {
            $errors['km_litro'] = 'Informe o consumo (km/litro).';
        }
        if ($values['preco_diesel'] <= 0) {
            $errors['preco_diesel'] = 'Informe o preço do diesel.';
        }

        $productPrice = 0.0;
        $productName = null;
        if ($values['product_id'] !== '') {
            $product = Product::find((int) $values['product_id']);
            if ($product) {
                $productPrice = (float) $product['price_cash'];
                $productName = $product['name'];
            }
        } elseif ($values['manual_price'] !== '') {
            $productPrice = self::parseBrNumber($values['manual_price']);
        }

        if ($productPrice <= 0) {
            $errors['product_id'] = 'Selecione um produto ou informe um valor manual.';
        }

        return [$values, $errors, $productPrice, $productName];
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
