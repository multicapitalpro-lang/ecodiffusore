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

        // Gate central do treinamento obrigatorio (Fase 42) -- DESATIVADO TEMPORARIAMENTE (Fase
        // 131), pedido explicito do usuario: ainda nao existem videos de treinamento gravados,
        // entao o gate so estava travando o primeiro acesso do Vendedor sem ter nada real pra
        // mostrar. Recolocar assim que os videos existirem -- so descomentar o bloco abaixo (o
        // resto do fluxo continua intacto: SellerTrainingController, tela /painel/treinamento e
        // o link no menu continuam funcionando normalmente, so pararam de ser OBRIGATORIOS).
        $user = self::user();
        // if ($user['role_slug'] === Roles::SELLER && empty($user['training_completed_at'])) {
        //     Router::redirect('/painel/treinamento');
        // }

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

        // Gate central de dados bancarios/Pix (Fase 116) -- Gestor/Licenciado/Vendedor/Gerente/
        // Supervisor recebem comissao e o sistema nunca coletou dados de pagamento de ninguem.
        // Retroativo de proposito: bank_data_completed_at fica NULL tanto pra cadastro novo
        // quanto pra conta que ja existia antes desta fase, entao todo mundo cai aqui no proximo
        // acesso ate preencher. Mesmo padrao dos gates acima: a tela (/painel/dados-bancarios)
        // usa so' requireLogin(), nunca requireRole(), fica sempre acessivel mesmo com isso aqui.
        if (in_array($user['role_slug'], Roles::PAYOUT_ROLES, true) && empty($user['bank_data_completed_at'])) {
            Router::redirect('/painel/dados-bancarios');
        }
    }
}
