<?php

namespace App\Core;

use App\Models\LicenciadoSubscription;
use App\Models\User;

/**
 * Paywall do Licenciado (Fase 32): Relatorios + Financeiro (Caixas/Contas a Pagar/Contas a Receber)
 * + Controle Fiscal/Antecipacoes ficam bloqueados pra Licenciado, Gestor e Vendedor sem assinatura
 * ativa -- a assinatura e' sempre do LICENCIADO (Gestor/Vendedor herdam o acesso da rede dele,
 * nunca assinam por conta propria). Admin/Gerente/Supervisor nunca sao afetados (nao pertencem a
 * rede de um Licenciado especifico). Financeiro > Comissoes fica de fora do bloqueio de proposito
 * (e' como o Licenciado/Gestor/Vendedor recebem, nao devem ficar sem ver isso).
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

    /** Redireciona pra tela de assinatura (com preview do que ganha) em vez de mostrar o
     *  conteudo bloqueado -- chamado logo depois do Auth::requireRole() de cada tela paga. */
    public static function requireAccess(array $user): void
    {
        if (!self::hasAccess($user)) {
            Router::redirect('/painel/assinatura?bloqueado=1');
        }
    }
}
