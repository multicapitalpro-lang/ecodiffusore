<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Roles;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\PricingTier;
use App\Models\UserCommissionTier;

/**
 * Simulador "quanto eu ganho nessa venda" (Fase 93b, terceira das 7 ferramentas premium) --
 * mostra a comissao estimada pra um preco/quantidade hipoteticos, usando a config REAL da
 * pessoa (tabela por faixa se tiver, senao % simples) -- e uma tabela comparando todas as
 * faixas de preco, pra deixar visivel "se eu vender mais caro, ganho quanto a mais". So
 * leitura, nao grava nada -- mesma logica de calculo ja usada em Commission::
 * createCascadeForOrder(), sem duplicar o pool completo do Licenciado (so' uma aproximacao
 * do bruto da faixa, documentada na propria tela).
 */
class CommissionSimulatorController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'simulador_comissao');

        $unitPrice = isset($_GET['unit_price']) ? (float) str_replace(',', '.', $_GET['unit_price']) : 0.0;
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
                'tier' => $tier,
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

            $simulation = ['unit_price' => $unitPrice, 'quantity' => $quantity, 'total' => $total, 'tier' => $tier, 'amount' => $amount];
        }

        View::render('painel/commission_simulator/index', [
            'user' => $user,
            'rows' => $rows,
            'simulation' => $simulation,
            'unitPrice' => $unitPrice,
            'quantity' => $quantity,
        ]);
    }
}
