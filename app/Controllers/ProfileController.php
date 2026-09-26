<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Router;
use App\Core\View;
use App\Models\User;

/** Fase 136: pagina "Meu Perfil" -- trocar senha (ja existia como /painel/trocar-senha, so' pro
 *  fluxo obrigatorio de primeiro acesso/senha temporaria, sem link nenhum no menu pra revisitar
 *  depois) + preferencias de notificacao push (novo). Pedido explicito do usuario: "hoje nao tem
 *  nenhuma tela assim". */
class ProfileController
{
    public function show(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        View::render('painel/profile/show', [
            'user' => $user,
            'events' => Notifier::EVENTS,
            'disabled' => array_keys(array_filter(User::notificationPrefs((int) $user['id']), fn ($v) => $v === false)),
            'sucesso' => $_GET['sucesso'] ?? null,
            'erro' => $_GET['erro'] ?? null,
        ]);
    }

    public function updatePassword(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/perfil?erro=1');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            Router::redirect('/painel/perfil?erro=senha_atual');
        }
        if (strlen($password) < 8 || $password !== $confirm) {
            Router::redirect('/painel/perfil?erro=senha_nova');
        }

        User::changePassword((int) $user['id'], $password);

        Router::redirect('/painel/perfil?sucesso=senha');
    }

    public function updateNotifications(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/perfil?erro=1');
        }

        $enabled = array_keys($_POST['events'] ?? []);
        $disabled = array_diff(array_keys(Notifier::EVENTS), $enabled);
        User::updateNotificationPrefs((int) $user['id'], $disabled);

        Router::redirect('/painel/perfil?sucesso=notificacoes');
    }
}
