<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\View;
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
}
