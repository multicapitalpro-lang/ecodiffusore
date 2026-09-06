<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\EconomyCalculator;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\View;
use App\Models\Product;

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

        View::render('painel/simulador/index', [
            'user' => Auth::user(),
            'products' => Product::all(true),
            'result' => null,
            'values' => [],
            'errors' => [],
        ]);
    }

    public function calcular(): void
    {
        Auth::requireRole(Roles::STAFF);

        [$values, $errors, $productPrice, $productName] = $this->parseAndValidate($_POST);

        $result = null;
        if (!$errors) {
            $result = EconomyCalculator::estimate(
                (float) $values['km_mensal'],
                (float) $values['km_litro'],
                (float) $values['preco_diesel'],
                $productPrice
            );
        }

        View::render('painel/simulador/index', [
            'user' => Auth::user(),
            'products' => Product::all(true),
            'result' => $result,
            'productPrice' => $productPrice,
            'productName' => $productName,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    public function downloadPdf(): void
    {
        Auth::requireRole(Roles::STAFF);

        [$values, $errors, $productPrice, $productName] = $this->parseAndValidate($_POST);

        if ($errors) {
            View::render('painel/simulador/index', [
                'user' => Auth::user(),
                'products' => Product::all(true),
                'result' => null,
                'values' => $values,
                'errors' => $errors,
            ]);
            return;
        }

        $result = EconomyCalculator::estimate(
            (float) $values['km_mensal'],
            (float) $values['km_litro'],
            (float) $values['preco_diesel'],
            $productPrice
        );

        ob_start();
        View::render('painel/simulador/pdf', [
            'clientName' => $values['client_name'],
            'productName' => $productName,
            'productPrice' => $productPrice,
            'result' => $result,
            'sellerName' => Auth::user()['name'],
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'simulacao-economia-ecodiffusore.pdf', 'portrait');
    }

    /** @return array{0: array, 1: array, 2: float, 3: ?string} */
    private function parseAndValidate(array $input): array
    {
        if (!Csrf::verify($input['csrf_token'] ?? null)) {
            return [[], ['geral' => 'Sessão expirada, recarregue a página.'], 0.0, null];
        }

        $values = [
            'client_name' => trim($input['client_name'] ?? ''),
            'client_whatsapp' => trim($input['client_whatsapp'] ?? ''),
            'product_id' => trim($input['product_id'] ?? ''),
            'manual_price' => trim($input['manual_price'] ?? ''),
            'km_mensal' => self::parseBrNumber($input['km_mensal'] ?? ''),
            'km_litro' => self::parseBrNumber($input['km_litro'] ?? ''),
            'preco_diesel' => self::parseBrNumber($input['preco_diesel'] ?? ''),
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
