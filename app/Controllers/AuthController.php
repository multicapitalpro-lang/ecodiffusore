<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Core\Router;
use App\Core\View;
use App\Models\PasswordReset;
use App\Models\Role;
use App\Models\User;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Router::redirect('/painel');
        }

        View::render('auth/login', [], null);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/login?erro=1');
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '' || !Auth::attempt($email, $password)) {
            Router::redirect('/painel/login?erro=1');
        }

        $user = Auth::user();
        if (!empty($user['must_change_password'])) {
            Router::redirect('/painel/trocar-senha');
        }
        if ($user['role_slug'] === 'cliente' && !$user['email_verified_at']) {
            Router::redirect('/painel/verificar-email');
        }
        if ($user['role_slug'] === 'licenciado' && $user['licenciado_onboarding_status'] === 'aguardando_perfil') {
            Router::redirect('/painel/licenciados/completar-perfil');
        }
        if ($user['role_slug'] === 'licenciado' && in_array($user['licenciado_onboarding_status'], ['aguardando_assinatura', 'aguardando_aprovacao', 'assinatura_recusada', 'kyc_recusado'], true)) {
            Router::redirect('/painel/licenciados/aguardando-assinatura');
        }
        if ($user['role_slug'] === 'vendedor' && empty($user['training_completed_at'])) {
            Router::redirect('/painel/treinamento');
        }

        Router::redirect('/painel');
    }

    public function logout(): void
    {
        Auth::logout();
        Router::redirect('/painel/login');
    }

    public function showChangePassword(): void
    {
        Auth::requireLogin();
        View::render('auth/change_password', [], null);
    }

    public function changePassword(): void
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/trocar-senha?erro=1');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8 || $password !== $confirm) {
            Router::redirect('/painel/trocar-senha?erro=2');
        }

        $user = Auth::user();
        User::changePassword((int) $user['id'], $password);

        Router::redirect('/painel');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            Router::redirect('/painel');
        }

        View::render('auth/register', ['errors' => [], 'old' => []], null);
    }

    public function register(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/cadastro?erro=1');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Informe o seu nome.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        } elseif (User::emailExists($email)) {
            $errors['email'] = 'Já existe uma conta com este e-mail.';
        }
        if ($whatsapp === '') {
            $errors['whatsapp'] = 'Informe o seu WhatsApp.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'A senha precisa ter pelo menos 8 caracteres.';
        } elseif ($password !== $confirm) {
            $errors['password'] = 'As senhas não conferem.';
        }

        if ($errors) {
            View::render('auth/register', ['errors' => $errors, 'old' => $_POST], null);
            return;
        }

        $roleId = Role::idBySlug('cliente');

        $userId = User::create([
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'password' => $password,
            'status' => 'active',
            'must_change_password' => false,
            'email_verified' => false,
        ]);

        \App\Models\Client::create([
            'user_id' => $userId,
            'name' => $name,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'status' => 'ativo',
        ]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        $this->sendVerificationCode($userId, $email, $name);

        Router::redirect('/painel/verificar-email');
    }

    public function showVerifyEmail(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['email_verified_at']) {
            Router::redirect('/painel');
        }

        View::render('auth/verify_email', ['erro' => $_GET['erro'] ?? null, 'enviado' => isset($_GET['enviado'])], null);
    }

    public function verifyEmail(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/verificar-email?erro=1');
        }

        $code = trim($_POST['code'] ?? '');

        if ($code === '' || !User::verifyEmailCode((int) $user['id'], $code)) {
            Router::redirect('/painel/verificar-email?erro=2');
        }

        Router::redirect('/painel?verificado=1');
    }

    public function resendVerificationCode(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || $user['email_verified_at']) {
            Router::redirect('/painel/verificar-email');
        }

        $this->sendVerificationCode((int) $user['id'], $user['email'], $user['name']);

        Router::redirect('/painel/verificar-email?enviado=1');
    }

    private function sendVerificationCode(int $userId, string $email, string $name): void
    {
        $code = (string) random_int(100000, 999999);
        User::setVerificationCode($userId, $code);

        Mailer::send(
            $email,
            'Confirme seu cadastro - Ecodiffusore Brasil',
            '<p>Olá, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!</p>'
            . '<p>Seu cadastro no painel Ecodiffusore Brasil foi criado. Use o código abaixo para confirmar seu e-mail:</p>'
            . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . $code . '</p>'
            . '<p>Esse código é válido por 30 minutos.</p>'
        );
    }

    public function showForgotPassword(): void
    {
        View::render('auth/forgot_password', ['step' => 'request'], null);
    }

    public function sendResetCode(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/esqueci-senha?erro=1');
        }

        $email = trim($_POST['email'] ?? '');
        $user = $email !== '' ? User::findByEmail($email) : null;

        if ($user && $user['role_slug'] === 'cliente' && $user['status'] === 'active') {
            $code = (string) random_int(100000, 999999);
            PasswordReset::createCode((int) $user['id'], $code);

            Mailer::send(
                $user['email'],
                'Seu código de recuperação de senha',
                '<p>Olá, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!</p>'
                . '<p>Seu código para redefinir a senha no painel Ecodiffusore Brasil é:</p>'
                . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;">' . $code . '</p>'
                . '<p>Esse código é válido por 15 minutos. Se você não pediu essa redefinição, ignore este e-mail.</p>'
            );
        }

        // Mensagem sempre genérica, exista ou não o e-mail / seja qual for o papel (evita enumeração de contas).
        View::render('auth/forgot_password', [
            'step' => 'sent',
            'email' => $email,
        ], null);
    }

    public function showResetForm(): void
    {
        $email = $_GET['email'] ?? '';
        View::render('auth/reset_password', ['email' => $email, 'errors' => []], null);
    }

    public function resetPassword(): void
    {
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/esqueci-senha?erro=1');
        }

        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];

        if (strlen($password) < 8 || $password !== $confirm) {
            $errors['password'] = 'As senhas não conferem ou têm menos de 8 caracteres.';
        }

        if ($errors) {
            View::render('auth/reset_password', ['email' => $email, 'errors' => $errors], null);
            return;
        }

        $user = User::findByEmail($email);
        $validCode = $user && $user['role_slug'] === 'cliente' && $code !== ''
            && PasswordReset::verifyCode((int) $user['id'], $code);

        if (!$validCode) {
            View::render('auth/reset_password', [
                'email' => $email,
                'errors' => ['code' => 'Código inválido ou expirado.'],
            ], null);
            return;
        }

        User::changePassword((int) $user['id'], $password);

        Router::redirect('/painel/login?recuperada=1');
    }
}
