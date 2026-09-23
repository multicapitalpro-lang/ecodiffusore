<?php

namespace App\Core;

use App\Models\LicenciadoSubscription;
use App\Models\SubscriptionPaywallHit;
use App\Models\User;

/**
 * Paywall do Licenciado (Fase 32, modelo revisado): Licenciado/Gestor/Vendedor sem assinatura ativa
 * do Licenciado da rede podem VER as telas de Relatorios e Financeiro (Caixas/Contas a Pagar/
 * Contas a Receber) -- os numeros aparecem mascarados (money()/count() abaixo) -- mas nao USAR
 * (salvar, dar baixa, baixar PDF, excluir): esses botoes abrem o modal de assinatura
 * (painel/subscription/_modal.php) em vez da acao de verdade. requireAccess() continua sendo a
 * trava real do lado do servidor em toda acao/mutacao (storeX/updateX/destroyX/markPaid/pdf/
 * downloadAttachment/storeSchedule) -- o popup e' so a experiencia, nao a seguranca.
 * A assinatura e' sempre do LICENCIADO (Gestor/Vendedor herdam o acesso da rede dele, nunca
 * assinam por conta propria). Admin/Gerente/Supervisor nunca sao afetados (nao pertencem a rede de
 * um Licenciado especifico). Financeiro > Comissoes fica de fora do bloqueio de proposito (e' como
 * o Licenciado/Gestor/Vendedor recebem, nao devem ficar sem ver isso).
 */
class SubscriptionGate
{
    public static function hasAccess(array $user): bool
    {
        if (!in_array($user['role_slug'], ['licenciado', 'gestor', 'vendedor'], true)) {
            return true;
        }

        $licenciado = User::licenciadoFor((int) $user['id']);
        if (!$licenciado) {
            return false;
        }

        return LicenciadoSubscription::isActive((int) $licenciado['id']);
    }

    /** Redireciona pra tela de assinatura (com preview do que ganha) em vez de completar a acao --
     *  chamado em toda mutacao (store/update/destroy/markPaid/pdf/downloadAttachment/storeSchedule),
     *  nunca nas telas de so-visualizacao (essas ficam abertas, com numero mascarado). Fase 86:
     *  $feature (chave de SubscriptionPaywallHit::LABELS) grava o "toque no paywall" pra
     *  alimentar o banner de remarketing -- omitido, nao grava nada (alguns call sites nao tem um
     *  rotulo natural, sem problema deixar de fora). */
    public static function requireAccess(array $user, ?string $feature = null): void
    {
        if (!self::hasAccess($user)) {
            if ($feature) {
                $licenciadoId = $user['role_slug'] === 'licenciado' ? (int) $user['id'] : User::licenciadoIdFor((int) $user['id']);
                SubscriptionPaywallHit::record((int) $user['id'], $licenciadoId, $feature);
            }
            Router::redirect('/painel/assinatura?bloqueado=1' . ($feature ? '&feature=' . urlencode($feature) : ''));
        }
    }

    /** Formata um valor monetario, ou mascara se sem acesso -- uso nas telas de Financeiro/
     *  Relatorios/Dashboard que agora ficam visiveis mesmo sem assinatura. */
    public static function money(array $user, float $value): string
    {
        return self::hasAccess($user) ? 'R$ ' . number_format($value, 2, ',', '.') : 'R$ ••••••';
    }

    /** Mesma logica pra contagens simples (ex: "3 vencidas"). */
    public static function count(array $user, int $value): string
    {
        return self::hasAccess($user) ? (string) $value : '••';
    }

    /** Decide se o modal de assinatura (painel/subscription/_modal.php) deve abrir SOZINHO no
     *  carregamento da tela -- so' quando sem acesso, e so' na primeira vez na sessao (senao
     *  atrapalharia navegar entre Caixas/Contas/Relatorios). Consome o flag ja na primeira
     *  chamada (nunca mais reabre sozinho ate' relogar). */
    public static function shouldAutoOpenModal(array $user): bool
    {
        if (self::hasAccess($user) || !empty($_SESSION['subscription_modal_seen'])) {
            return false;
        }
        $_SESSION['subscription_modal_seen'] = true;
        return true;
    }
}
