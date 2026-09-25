<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ExchangeRateClient;
use App\Core\LeaseCalculator;
use App\Core\Money;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\View;
use App\Models\User;

/**
 * Calculadora oficial de locação (Fase 122) -- réplica de uma calculadora externa que o usuário
 * pediu pra virar ferramenta oficial do sistema, com seletor de moeda (mesmo padrão já usado no
 * Simulador de Economia/Proposta Fácil: só aparece pra quem tem secondary_currency configurada).
 */
class LeaseCalculatorController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $secondaryCurrency = User::secondaryCurrencyForUser((int) $user['id']);

        View::render('painel/calculadora_locacao/index', [
            'user' => $user,
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

        [$values, $errors] = $this->parseAndValidate($_POST, $secondaryCurrency, $rate);

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

        View::render('painel/calculadora_locacao/index', [
            'user' => $user,
            'result' => $result,
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

        [$values, $errors] = $this->parseAndValidate($_POST, $secondaryCurrency, $rate);

        if ($errors) {
            View::render('painel/calculadora_locacao/index', [
                'user' => $user,
                'result' => null,
                'values' => $values,
                'errors' => $errors,
                'secondaryCurrency' => $secondaryCurrency,
                'rate' => $rate,
            ]);
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
            'sellerName' => $user['name'],
            'currency' => $values['currency'],
            'rate' => $rate,
        ], null);
        $html = ob_get_clean();

        Pdf::download($html, 'calculadora-locacao-ecodiffusore.pdf', 'portrait');
    }

    /** @return array{0: array, 1: array} */
    private function parseAndValidate(array $input, ?string $secondaryCurrency = null, float $rate = 0.0): array
    {
        if (!Csrf::verify($input['csrf_token'] ?? null)) {
            return [[], ['geral' => 'Sessão expirada, recarregue a página.']];
        }

        $currency = ($secondaryCurrency && ($input['currency'] ?? 'BRL') === $secondaryCurrency) ? $secondaryCurrency : 'BRL';
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
        // Fase 128: adesao/mensalidade viraram opcionais -- pedido explicito do usuario, nem toda
        // negociacao e' locacao (as vezes e' so' a economia de diesel que importa), e travar o
        // calculo por causa de um campo comercial que nao se aplica nao fazia sentido.

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
