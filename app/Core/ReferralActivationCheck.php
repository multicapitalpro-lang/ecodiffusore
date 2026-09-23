<?php

namespace App\Core;

use App\Models\Order;
use App\Models\Referral;

/** Fase 95: rotina lazy (sem cron nesse plano Hostinger, mesmo padrao de InactivityAlert/
 *  GoalPaceAlert) -- roda a cada carregamento de /painel/indicacoes, varre indicacoes ja
 *  vinculadas a um cadastro (status 'cadastrado') e confere se esse cadastro ja "pegou de
 *  verdade": pra Licenciado, passou pelo onboarding completo (licenciado_onboarding_status =
 *  'ativo' -- contrato assinado + KYC aprovado); pra Gestor/Vendedor, ja fechou pelo menos 1
 *  pedido (nao basta status active, que ja nasce assim). Quando confirma, avisa o indicador. */
class ReferralActivationCheck
{
    public static function processDue(): void
    {
        foreach (Referral::allPendingActivation() as $referral) {
            self::checkReferral($referral);
        }
    }

    private static function checkReferral(array $referral): void
    {
        if (($referral['converted_user_status'] ?? '') !== 'active') {
            return;
        }

        $isActive = $referral['target_role'] === 'licenciado'
            ? ($referral['converted_onboarding_status'] ?? '') === 'ativo'
            : (Order::metrics('2000-01-01', date('Y-m-d'), (int) $referral['converted_user_id'])['order_count'] ?? 0) > 0;

        if (!$isActive) {
            return;
        }

        Referral::markActive((int) $referral['id']);
        Notifier::indicacaoAtivada($referral);
    }
}
