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
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();

        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }
}
