<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\ExchangeRateClient;
use App\Core\LeaseCalculator;
use App\Core\Money;
use App\Core\Pdf;
use App\Core\View;

/**
 * Versao PUBLICA (sem login) da Calculadora de Locacao (Fase 124) -- pedido explicito do
 * usuario: "quero o link fora do painel pra qualquer vendedor acessar", sem precisar de conta
 * no sistema. Mesmo motor de calculo (App\Core\LeaseCalculator) e mesmo layout visual da versao
 * autenticada (/painel/calculadora-locacao) -- a diferenca e' so' o gate de acesso e o seletor
 * de moeda, que aqui fica SEMPRE disponivel (Real/Guarani) em vez de depender da configuracao de
 * secondary_currency de um usuario logado especifico.
 */
class PublicLeaseCalculatorController
{
    public function index(): void
    {
        View::render('public/calculadora_locacao', [
            'result' => null,
            'values' => [],
            'errors' => [],
            'rate' => ExchangeRateClient::brlToPyg(),
        ], null);
    }

    public function calcular(): void
    {
        $rate = ExchangeRateClient::brlToPyg();
        [$values, $errors] = $this->parseAndValidate($_POST, $rate);

        $result = null;
        if (!$errors) {
            $result = LeaseCalculator::estimate(
                (float) $values['gasto_diesel_brl'],
                (float) $values['km_rodados'],
                (float) $values['media_kml'],
                $values['modo'],
                (float) $values['preco_diesel_brl'],
                (float) $values['pct_economia'],
                (float) $values['valor_adesao_brl'],
                (float) $values['mensalidade_brl'],
                (int) $values['veiculos'],
                (int) $values['parcelas_adesao']
            );
        }

        View::render('public/calculadora_locacao', [
            'result' => $result,
            'values' => $values,
            'errors' => $errors,
            'rate' => $rate,
        ], null);
    }

    public function downloadPdf(): void
    {
        $rate = ExchangeRateClient::brlToPyg();
        [$values, $errors] = $this->parseAndValidate($_POST, $rate);

        if ($errors) {
            View::render('public/calculadora_locacao', [
                'result' => null,
                'values' => $values,
                'errors' => $errors,
                'rate' => $rate,
            ], null);
            return;
        }

        $result = LeaseCalculator::estimate(
            (float) $values['gasto_diesel_brl'],
            (float) $values['km_rodados'],
            (float) $values['media_kml'],
            $values['modo'],
            (float) $values['preco_diesel_brl'],
            (float) $values['pct_economia'],
            (float) $values['valor_adesao_brl'],
            (float) $values['mensalidade_brl'],
            (int) $values['veiculos'],
            (int) $values['parcelas_adesao']
        );

        ob_start();
        View::render('painel/calculadora_locacao/pdf', [
            'clientName' => $values['client_name'],
            'result' => $result,
            'sellerName' => 'Ecodiffusore Brasil',
            'currency' => $values['currency'],
            'rate' => $rate,
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'calculadora-locacao-ecodiffusore.pdf', 'portrait');
    }

    /** @return array{0: array, 1: array} */
    private function parseAndValidate(array $input, float $rate): array
    {
        if (!Csrf::verify($input['csrf_token'] ?? null)) {
            return [[], ['geral' => 'Sessão expirada, recarregue a página.']];
        }

        $currency = ($input['currency'] ?? 'BRL') === 'PYG' ? 'PYG' : 'BRL';
        $modo = ($input['modo'] ?? 'km_rodados') === 'media' ? 'media' : 'km_rodados';

        $gastoDieselInput = self::parseBrNumber($input['gasto_diesel'] ?? '');
        $precoDieselInput = self::parseBrNumber($input['preco_diesel'] ?? '');
        $valorAdesaoInput = self::parseBrNumber($input['valor_adesao'] ?? '');
        $mensalidadeInput = self::parseBrNumber($input['mensalidade'] ?? '');

        $values = [
            'client_name' => trim($input['client_name'] ?? ''),
            'currency' => $currency,
            'modo' => $modo,
            'gasto_diesel' => $gastoDieselInput,
            'gasto_diesel_brl' => Money::toBrl($gastoDieselInput, $currency, $rate),
            'km_rodados' => self::parseBrNumber($input['km_rodados'] ?? ''),
            'media_kml' => self::parseBrNumber($input['media_kml'] ?? ''),
            'preco_diesel' => $precoDieselInput,
            'preco_diesel_brl' => Money::toBrl($precoDieselInput, $currency, $rate),
            'pct_economia' => self::parseBrNumber($input['pct_economia'] ?? '10'),
            'valor_adesao' => $valorAdesaoInput,
            'valor_adesao_brl' => Money::toBrl($valorAdesaoInput, $currency, $rate),
            'mensalidade' => $mensalidadeInput,
            'mensalidade_brl' => Money::toBrl($mensalidadeInput, $currency, $rate),
            'veiculos' => max(1, (int) ($input['veiculos'] ?? 1)),
            'parcelar_adesao' => !empty($input['parcelar_adesao']),
            'parcelas_adesao' => !empty($input['parcelar_adesao']) ? max(1, min(12, (int) ($input['parcelas_adesao'] ?? 1))) : 1,
        ];

        $errors = [];
        if ($values['gasto_diesel'] <= 0) {
            $errors['gasto_diesel'] = 'Informe o gasto com diesel.';
        }
        if ($modo === 'km_rodados' && $values['km_rodados'] <= 0) {
            $errors['km_rodados'] = 'Informe os km rodados por mês.';
        }
        if ($modo === 'media' && $values['media_kml'] <= 0) {
            $errors['media_kml'] = 'Informe a média km/l.';
        }
        if ($values['preco_diesel'] <= 0) {
            $errors['preco_diesel'] = 'Informe o preço do diesel.';
        }
        if ($values['pct_economia'] < 5 || $values['pct_economia'] > 30) {
            $errors['pct_economia'] = 'O percentual de economia contratual fica entre 5% e 30%.';
        }
        if ($values['valor_adesao'] <= 0) {
            $errors['valor_adesao'] = 'Informe o valor de adesão.';
        }
        if ($values['mensalidade'] <= 0) {
            $errors['mensalidade'] = 'Informe a mensalidade da locação.';
        }

        return [$values, $errors];
    }

    /** Aceita tanto "12.000"/"12000" quanto "2,8"/"6,10" (formato BR com vírgula decimal). */
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
