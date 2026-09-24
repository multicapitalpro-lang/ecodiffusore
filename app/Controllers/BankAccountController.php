<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\User;

/** Dados bancarios/Pix obrigatorios no primeiro acesso (Fase 116) -- Gestor/Licenciado/Vendedor/
 *  Gerente/Supervisor recebem comissao e o sistema nunca coletou dado de pagamento de ninguem.
 *  O bloqueio de verdade fica em Auth::requireRole() (central, cobre qualquer rota de funcao);
 *  esta tela em si so' usa Auth::requireLogin(), pra ficar sempre acessivel enquanto o usuario
 *  esta preso no gate -- e continua acessivel depois, pra edicao (nao e' so' tela de bloqueio). */
class BankAccountController
{
    public function show(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!in_array($user['role_slug'], Roles::PAYOUT_ROLES, true)) {
            Router::redirect('/painel');
        }

        View::render('painel/bank_account/show', ['user' => $user, 'errors' => []], null);
    }

    public function update(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!in_array($user['role_slug'], Roles::PAYOUT_ROLES, true)) {
            Router::redirect('/painel');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/dados-bancarios?erro=1');
        }

        $errors = $this->validate($_POST);

        if ($errors) {
            View::render('painel/bank_account/show', [
                'user' => array_merge($user, $_POST),
                'errors' => $errors,
            ], null);
            return;
        }

        User::updateBankData((int) $user['id'], $_POST);

        Router::redirect('/painel?dados_bancarios=1');
    }

    private function validate(array $data): array
    {
        $errors = [];

        foreach (['bank_code', 'bank_name', 'bank_agency', 'bank_account', 'bank_account_digit', 'pix_key'] as $field) {
            if (trim($data[$field] ?? '') === '') {
                $errors[$field] = 'Campo obrigatório.';
            }
        }

        if (!in_array($data['bank_account_type'] ?? '', ['corrente', 'poupanca'], true)) {
            $errors['bank_account_type'] = 'Selecione o tipo de conta.';
        }

        $document = preg_replace('/\D/', '', (string) ($data['payment_document'] ?? ''));
        if (!in_array(strlen($document), [11, 14], true)) {
            $errors['payment_document'] = 'CPF ou CNPJ inválido.';
        }

        return $errors;
    }
}
