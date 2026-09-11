<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);

        if (!$user || $user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        User::touchLastLogin((int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        static $cached = null;

        if (!self::check()) {
            return null;
        }

        if ($cached === null) {
            $cached = User::find((int) $_SESSION['user_id']);
        }

        return $cached;
    }

    public static function role(): ?string
    {
        return self::user()['role_slug'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Router::redirect('/painel/login');
        }

        $user = self::user();
        if (!$user || $user['status'] !== 'active') {
            self::logout();
            Router::redirect('/painel/login');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        // Gate central do treinamento obrigatorio (Fase 42) -- toda acao de verdade do painel
        // passa por requireRole(), entao colocar a trava aqui (em vez de so' no login/Dashboard,
        // como os outros gates de onboarding deste projeto) bloqueia de verdade mesmo se o
        // Vendedor tentar navegar direto pra uma URL de funcao. A tela de treinamento em si usa
        // so' requireLogin(), nunca requireRole(), entao fica sempre acessivel.
        $user = self::user();
        if ($user['role_slug'] === Roles::SELLER && empty($user['training_completed_at'])) {
            Router::redirect('/painel/treinamento');
        }
    }
}
