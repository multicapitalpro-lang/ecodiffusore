<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Core\SubscriptionGate;
use App\Models\PricingTier;
use App\Models\UserCommissionTier;

/** Fase 103: Simulador de Comissao pro app -- mesma logica exata de
 *  App\Controllers\CommissionSimulatorController (nao duplica o pool completo do Licenciado, so'
 *  uma aproximacao do bruto da faixa, igual a tela web). Atras do mesmo paywall
 *  ('simulador_comissao'). */
class CommissionSimulatorController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a essa função.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }

        $unitPrice = isset($_GET['unit_price']) ? (float) str_replace(',', '.', (string) $_GET['unit_price']) : 0.0;
        $quantity = max(1, (int) ($_GET['quantity'] ?? 1));

        $tiers = PricingTier::visible();
        $userTierValues = in_array($user['role_slug'], ['vendedor', 'supervisor'], true)
            ? UserCommissionTier::forUser((int) $user['id'])
            : [];

        $rows = array_map(function ($tier) use ($user, $userTierValues) {
            $pct = null;
            $note = '';

            if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
                $pct = (float) $tier['licenciado_commission_pct'];
                $note = 'bruto do pool (antes de repassar pro seu Gestor/Vendedor)';
            } elseif (in_array($user['role_slug'], ['vendedor', 'supervisor'], true) && !empty($userTierValues[$tier['id']])) {
                $value = $userTierValues[$tier['id']];
                $note = ($user['commission_type'] ?? 'percentual') === 'fixo' ? "R$ {$value} fixo por unidade" : "{$value}% do total";
            } else {
                $pct = (float) ($user['commission_pct'] ?? 0);
                $note = 'sua % padrão configurada';
            }

            return [
                'tier_id' => (int) $tier['id'],
                'min_price' => (float) $tier['min_price'],
                'max_price' => $tier['max_price'] !== null ? (float) $tier['max_price'] : null,
                'pct' => $pct,
                'value' => $userTierValues[$tier['id']] ?? null,
                'note' => $note,
            ];
        }, $tiers);

        $simulation = null;
        if ($unitPrice > 0) {
            $tier = PricingTier::forPrice($unitPrice);
            $total = $unitPrice * $quantity;
            $amount = 0.0;

            if ($tier) {
                if ($user['role_slug'] === Roles::REGIONAL_OWNER) {
                    $amount = round($total * (float) $tier['licenciado_commission_pct'] / 100, 2);
                } elseif (in_array($user['role_slug'], ['vendedor', 'supervisor'], true) && !empty($userTierValues[$tier['id']])) {
                    $value = $userTierValues[$tier['id']];
                    $amount = ($user['commission_type'] ?? 'percentual') === 'fixo'
                        ? round($value * $quantity, 2)
                        : round($total * $value / 100, 2);
                } else {
                    $amount = round($total * (float) ($user['commission_pct'] ?? 0) / 100, 2);
                }
            }

            $simulation = [
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total' => $total,
                'tier_found' => $tier !== null,
                'amount' => $amount,
            ];
        }

        ApiResponse::json(['rows' => $rows, 'simulation' => $simulation]);
    }
}
