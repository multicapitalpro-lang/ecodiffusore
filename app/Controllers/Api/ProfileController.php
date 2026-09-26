<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Mailer;
use App\Core\Notifier;
use App\Models\PasswordReset;
use App\Models\User;

/** Fase 136: "Meu Perfil" pro app -- troca de senha + preferencias de notificacao, mesmo
 *  principio da versao web (App\Controllers\ProfileController), so' devolvendo/recebendo JSON.
 *  Tambem cobre "esqueci minha senha" (login() ainda nao existia essa opcao no app -- so no
 *  painel web), reaproveitando 100% App\Models\PasswordReset (mesmo codigo de 6 digitos por
 *  e-mail que o painel ja usa). */
class ProfileController
{
    public function events(): void
    {
        ApiAuth::requireUser();
        ApiResponse::json(['events' => Notifier::EVENTS]);
    }

    public function show(): void
    {
        $user = ApiAuth::requireUser();
        $prefs = User::notificationPrefs((int) $user['id']);
        $disabled = array_keys(array_filter($prefs, fn ($v) => $v === false));

        ApiResponse::json([
            'name' => $user['name'],
            'email' => $user['email'],
            'role_name' => $user['role_name'],
            'disabled_events' => $disabled,
            'profile_reviewed' => !empty($user['profile_reviewed_at']),
        ]);
    }

    public function updateNotifications(): void
    {
        $user = ApiAuth::requireUser();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $disabled = is_array($body['disabled_events'] ?? null) ? $body['disabled_events'] : [];

        User::updateNotificationPrefs((int) $user['id'], $disabled);

        ApiResponse::json(['ok' => true]);
    }

    public function markReviewed(): void
    {
        $user = ApiAuth::requireUser();
        User::markProfileReviewed((int) $user['id']);
        ApiResponse::json(['ok' => true]);
    }

    public function changePassword(): void
    {
        $user = ApiAuth::requireUser();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $current = (string) ($body['current_password'] ?? '');
        $password = (string) ($body['password'] ?? '');

        if (!password_verify($current, $user['password_hash'])) {
            ApiResponse::json(['error' => 'Senha atual incorreta.', 'errors' => ['current_password' => 'Senha atual incorreta.']], 422);
        }
        if (strlen($password) < 8) {
            ApiResponse::json(['error' => 'A nova senha precisa ter pelo menos 8 caracteres.', 'errors' => ['password' => 'Mínimo de 8 caracteres.']], 422);
        }

        User::changePassword((int) $user['id'], $password);

        ApiResponse::json(['ok' => true]);
    }

    /** Sem login -- pedido antes de autenticar (tela "Esqueci minha senha"). Resposta sempre
     *  generica (evita enumeracao de conta), igual AuthController::sendResetCode() no painel. */
    public function forgotPassword(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = trim((string) ($body['email'] ?? ''));
        $user = $email !== '' ? User::findByEmail($email) : null;

        if ($user && $user['status'] === 'active') {
            $code = (string) random_int(100000, 999999);
            PasswordReset::createCode((int) $user['id'], $code);

            Mailer::send(
                $user['email'],
                'Seu código de recuperação de senha',
                '<p>Olá, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!</p>'
                . '<p>Seu código para redefinir a senha é:</p>'
                . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . $code . '</p>'
                . '<p>Esse código é válido por 15 minutos. Se você não pediu essa redefinição, ignore este e-mail.</p>'
            );
        }

        ApiResponse::json(['ok' => true]);
    }

    /** Confirma o codigo + define a senha nova -- sem login (o usuario ainda nao tem token). */
    public function resetPassword(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = trim((string) ($body['email'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if (strlen($password) < 8) {
            ApiResponse::json(['error' => 'A senha precisa ter pelo menos 8 caracteres.', 'errors' => ['password' => 'Mínimo de 8 caracteres.']], 422);
        }

        $user = $email !== '' ? User::findByEmail($email) : null;
        if (!$user || $code === '' || !PasswordReset::verifyCode((int) $user['id'], $code)) {
            ApiResponse::json(['error' => 'Código inválido ou expirado.', 'errors' => ['code' => 'Código inválido ou expirado.']], 422);
        }

        User::changePassword((int) $user['id'], $password);

        ApiResponse::json(['ok' => true]);
    }
}
