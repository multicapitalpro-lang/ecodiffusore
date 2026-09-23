<?php

namespace App\Core;

use App\Models\Referral;
use App\Models\TeamFeedEntry;
use App\Models\User;

/** Fase 97: ponto central que formata e posta cada evento no Mural de Conquistas -- um metodo por
 *  tipo de evento, chamado no exato momento em que cada um se confirma em outras partes do
 *  codigo (venda verificada, meta batida, certificacao ganha, indicacao ativada). Audiencia
 *  sempre a rede inteira do Licenciado (User::downlineIds), nunca so' quem gerou o evento. */
class TeamFeed
{
    public static function orderVerified(array $order): void
    {
        if (empty($order['seller_id'])) {
            return;
        }
        $licenciado = User::licenciadoFor((int) $order['seller_id']);
        if (!$licenciado) {
            return;
        }
        $seller = User::find((int) $order['seller_id']);
        $sellerName = $seller['name'] ?? 'Alguém';
        $valor = 'R$ ' . number_format((float) $order['total_value'], 2, ',', '.');
        TeamFeedEntry::create((int) $licenciado['id'], (int) $order['seller_id'], 'venda', "🎉 {$sellerName} fechou uma venda de {$valor}!");
    }

    /** @param array $goal precisa de seller_id/seller_name/name (id opcional, nao usado aqui). */
    public static function goalReached(array $goal): void
    {
        if (empty($goal['seller_id'])) {
            return;
        }
        $licenciado = User::licenciadoFor((int) $goal['seller_id']);
        if (!$licenciado) {
            return;
        }
        $sellerName = $goal['seller_name'] ?? 'Alguém';
        TeamFeedEntry::create((int) $licenciado['id'], (int) $goal['seller_id'], 'meta', "🏆 {$sellerName} bateu a meta \"{$goal['name']}\"!");
    }

    /** @param array $user precisa de id/name (o proprio vendedor recem-certificado). */
    public static function certificationEarned(array $user): void
    {
        $licenciado = User::licenciadoFor((int) $user['id']);
        if (!$licenciado) {
            return;
        }
        TeamFeedEntry::create((int) $licenciado['id'], (int) $user['id'], 'certificacao', "✅ {$user['name']} virou Vendedor Certificado!");
    }

    /** @param array $referral precisa de referrer_id/name/target_role. */
    public static function referralActivated(array $referral): void
    {
        $licenciado = User::licenciadoFor((int) $referral['referrer_id']);
        if (!$licenciado) {
            return;
        }
        $referrer = User::find((int) $referral['referrer_id']);
        $roleLabel = Referral::TARGET_ROLE_LABELS[$referral['target_role']] ?? $referral['target_role'];
        $referrerName = $referrer['name'] ?? 'Alguém';
        TeamFeedEntry::create((int) $licenciado['id'], (int) $referral['referrer_id'], 'indicacao', "🎁 A indicação de {$referrerName} virou {$roleLabel} ativo(a)!");
    }
}
