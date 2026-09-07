<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
use App\Models\Client;

/** "Meus Dados" -- cliente completa/edita os proprios dados de contato/endereco (o auto-cadastro
 *  em AuthController::register() so coleta nome/e-mail/whatsapp). */
class ClientProfileController
{
    public function edit(): void
    {
        Auth::requireRole(['cliente']);
        $client = Client::findByUserId((int) Auth::user()['id']);
        if (!$client) {
            Router::redirect('/painel');
        }

        View::render('painel/client_portal/profile', ['user' => Auth::user(), 'client' => $client, 'errors' => []]);
    }

    public function update(): void
    {
        Auth::requireRole(['cliente']);
        $user = Auth::user();
        $client = Client::findByUserId((int) $user['id']);
        if (!$client) {
            Router::redirect('/painel');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/meus-dados?erro=1');
        }

        $document = trim($_POST['document'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');

        $errors = [];
        $duplicate = Client::findDuplicate($document ?: null, $whatsapp ?: null, (int) $client['id']);
        if ($duplicate) {
            $field = $document && preg_replace('/\D/', '', $document) === preg_replace('/\D/', '', (string) $duplicate['document']) ? 'document' : 'whatsapp';
            $errors[$field] = $field === 'document' ? 'Este CPF/CNPJ já está cadastrado em outra conta.' : 'Este WhatsApp já está cadastrado em outra conta.';
        }

        if ($errors) {
            View::render('painel/client_portal/profile', [
                'user' => $user,
                'client' => array_merge($client, $_POST),
                'errors' => $errors,
            ]);
            return;
        }

        Client::updateOwnProfile((int) $client['id'], $_POST);
        Router::redirect('/painel/meus-dados?sucesso=1');
    }
}
