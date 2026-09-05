<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\PaymentSettings;

/** Configuracao das taxas de cartao/antecipacao repassadas ao cliente (Fase 26) -- admin-only. */
class PaymentSettingsController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/settings/payment', [
            'user' => Auth::user(),
            'settings' => PaymentSettings::current(),
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        Auth::requireRole(['admin']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/configuracoes/pagamento?erro=1');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            View::render('painel/settings/payment', [
                'user' => Auth::user(),
                'settings' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        PaymentSettings::update($_POST);

        Router::redirect('/painel/configuracoes/pagamento?sucesso=1');
    }

    private function validate(array $input): array
    {
        $errors = [];

        foreach (['card_fee_avista_pct', 'card_fee_parcelado_pct', 'antecipacao_avista_mensal_pct', 'antecipacao_parcelado_mensal_pct'] as $field) {
            if (!is_numeric($input[$field] ?? null) || (float) $input[$field] < 0 || (float) $input[$field] > 100) {
                $errors[$field] = 'Informe uma % entre 0 e 100.';
            }
        }
        foreach (['card_fixed_fee', 'pix_fee', 'boleto_fee'] as $field) {
            if (!is_numeric($input[$field] ?? null) || (float) $input[$field] < 0) {
                $errors[$field] = 'Informe um valor válido.';
            }
        }
        if (!is_numeric($input['max_installments'] ?? null) || (int) $input['max_installments'] < 1 || (int) $input['max_installments'] > 21) {
            $errors['max_installments'] = 'Informe um número de parcelas entre 1 e 21.';
        }

        return $errors;
    }
}
