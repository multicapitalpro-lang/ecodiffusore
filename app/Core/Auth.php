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

        // Gate central do contrato do Vendedor (Fase 105) -- depois do treinamento, o Vendedor
        // precisa baixar o contrato, assinar via gov.br e enviar de volta pro Licenciado aprovar.
        // Mesmo padrao dos gates acima: a tela em si (/painel/contrato-vendedor) usa so'
        // requireLogin(), fica sempre acessivel mesmo com esse redirect aqui.
        if ($user['role_slug'] === Roles::SELLER && in_array($user['vendedor_contract_status'], ['pendente_envio', 'aguardando_aprovacao', 'reprovado'], true)) {
            Router::redirect('/painel/contrato-vendedor');
        }

        // Gate central do onboarding obrigatorio do Licenciado (Fase 44) -- ate aqui o gate
        // (Fase 18) so era checado em AuthController::login() e DashboardController::index(),
        // ou seja, um Licenciado preso em aguardando_perfil/aguardando_assinatura/
        // aguardando_aprovacao/assinatura_recusada/kyc_recusado conseguia navegar direto pra
        // qualquer URL de funcao (Pedidos, Leads etc.) sem nunca assinar o contrato via
        // ClickSign. Mesma logica de LicenciadoOnboardingController::showProfileForm()/
        // showWaitingPage() (que usam so' requireLogin(), nunca requireRole() -- por isso ficam
        // sempre acessiveis mesmo com esse gate aqui).
        if ($user['role_slug'] === Roles::REGIONAL_OWNER && $user['licenciado_onboarding_status'] === 'aguardando_perfil') {
            Router::redirect('/painel/licenciados/completar-perfil');
        }
        if ($user['role_slug'] === Roles::REGIONAL_OWNER && in_array($user['licenciado_onboarding_status'], ['aguardando_assinatura', 'aguardando_aprovacao', 'assinatura_recusada', 'kyc_recusado'], true)) {
            Router::redirect('/painel/licenciados/aguardando-assinatura');
        }
    }
}
