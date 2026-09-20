<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\BrazilStates;
use App\Core\DateRange;
use App\Core\Roles;
use App\Models\Commission;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Goal;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PricingTier;
use App\Models\Quote;
use App\Models\SellerActivity;
use App\Models\User;

/** Fase 76/78: resumo do Dashboard pro app -- mesmo escopo por hierarquia de
 *  App\Controllers\DashboardController::index(), agora bem mais completo (pedido do usuario:
 *  "admin pagina inicial tem poucos cards"). So que devolvendo JSON em vez de renderizar view;
 *  fica de fora so o que so faz sentido como grafico/mapa (regionCharts, mapa por estado). */
class DashboardController
{
    public function summary(): void
    {
        $user = ApiAuth::requireUser();
        $role = $user['role_slug'];

        if (!in_array($role, Roles::STAFF, true)) {
            ApiResponse::error('Papel sem resumo de vendas.', 403);
        }

        [$from, $to] = DateRange::fromRequest();

        $sellerId = null;
        $sellerIds = null;
        if ($role === Roles::SELLER) {
            $sellerId = (int) $user['id'];
        } elseif ($role === 'supervisor') {
            $sellerIds = User::supervisedIds((int) $user['id']);
        } elseif ($role === 'gerente') {
            $sellerIds = User::nationalIds((int) $user['id']);
        } elseif ($role !== 'admin') {
            $sellerIds = User::downlineIds((int) $user['id']);
        }

        $metrics = Order::metrics($from, $to, $sellerId, $sellerIds);
        $crmScope = $sellerId !== null ? ['seller_id' => $sellerId] : ($sellerIds !== null ? ['seller_ids' => $sellerIds] : []);
        $situation = Order::paymentSituationSummary($crmScope);

        $data = [
            'from' => $from,
            'to' => $to,
            'metrics' => $metrics,
            'pedidos_pendentes' => $situation['pending']['count'],
            'pedidos_pendentes_valor' => $situation['pending']['total_value'],
            'pedidos_pagos' => $situation['paid']['count'],
            'pedidos_pagos_valor' => $situation['paid']['total_value'],
            'orcamentos_pendentes' => Quote::countPendingPayment($crmScope),
        ];

        $data['top_products'] = array_map(fn ($p) => [
            'name' => $p['name'],
            'quantity' => (int) $p['total_qty'],
            'value' => (float) $p['total_value'],
        ], array_slice(OrderItem::topProducts($from, $to, $sellerId, $sellerIds), 0, 5));

        $data['goals'] = array_map(fn ($g) => [
            'id' => (int) $g['id'],
            'label' => $g['seller_name'] ?? 'Equipe',
            'start_date' => $g['start_date'],
            'end_date' => $g['end_date'],
            'progress' => Goal::progress($g),
        ], Goal::activeFor((int) $user['id']));

        if ($role === Roles::REGIONAL_OWNER) {
            $data['vendor_ranking'] = array_map(fn ($row) => [
                'name' => $row['name'],
                'value' => (float) $row['total_value'],
            ], array_slice(Order::sellerRanking($from, $to, $sellerIds), 0, 10));
        }

        if (in_array($role, ['gestor', Roles::REGIONAL_OWNER], true)) {
            $ownVendedores = array_values(array_filter(
                User::allByRole('vendedor'),
                fn ($v) => in_array((int) $v['id'], $sellerIds ?? [], true)
            ));
            $data['vendedores_inativos'] = SellerActivity::inactiveAmong($ownVendedores);
        }

        if ($role === 'admin') {
            $leads = Lead::all();
        } else {
            $leadIds = $sellerId !== null ? [$sellerId] : ($sellerIds ?? []);
            $includeUnassigned = !in_array($role, [Roles::SELLER, 'supervisor', 'gerente'], true);
            $leads = Lead::forScope($leadIds, $includeUnassigned);
        }
        $data['leads_novos'] = count(array_filter($leads, fn ($l) => $l['status'] === 'novo'));

        $todayStr = date('Y-m-d');
        $pendingFollowUps = LeadNote::pendingFollowUps(array_column($leads, 'id'));
        $data['follow_ups_pendentes'] = count(array_filter($pendingFollowUps, fn ($d) => $d <= $todayStr));

        $myCommission = Commission::byBeneficiary(['beneficiary_id' => (int) $user['id']]);
        $data['minha_comissao_pendente'] = (float) ($myCommission[0]['total_pendente'] ?? 0);

        if (in_array($role, ['licenciado', 'gestor'], true)) {
            $data['pricing_tiers_ref'] = array_map(fn ($t) => [
                'min_price' => (float) $t['min_price'],
                'max_price' => $t['max_price'] !== null ? (float) $t['max_price'] : null,
                'pct' => (float) $t['licenciado_commission_pct'],
            ], PricingTier::visible());
        }

        if (in_array($role, Roles::MANAGEMENT, true)) {
            $financeScope = in_array($role, [Roles::REGIONAL_OWNER, 'gestor'], true)
                ? ['seller_ids' => User::downlineIds((int) $user['id'])]
                : [];

            $accounts = FinancialAccount::all();
            $data['saldo_caixa'] = array_sum(array_map(
                fn ($a) => FinancialAccount::currentBalance((int) $a['id']),
                $accounts
            ));

            $today = date('Y-m-d');
            $payables = FinancialTransaction::all(array_merge(['type' => 'saida', 'status' => 'pendente', 'exclude_transfers' => true], $financeScope));
            $receivables = FinancialTransaction::all(array_merge(['type' => 'entrada', 'status' => 'pendente', 'exclude_transfers' => true], $financeScope));

            $data['contas_pagar_aberto'] = array_sum(array_map(fn ($t) => FinancialTransaction::totalValue($t), $payables));
            $data['contas_pagar_vencidas'] = count(array_filter($payables, fn ($t) => $t['due_date'] < $today));
            $data['contas_receber_aberto'] = array_sum(array_map(fn ($t) => FinancialTransaction::totalValue($t), $receivables));
        }

        if (in_array($role, Roles::SUPERVISOR_ASSIGNMENT, true)) {
            $data['aprovacoes_pendentes'] = User::pendingApprovalCount($user);
        }

        if (in_array($role, ['admin', 'gerente', 'supervisor'], true)) {
            $licenciadosAtivos = User::allByRole('licenciado');
            $data['licenciados_ativos'] = count($licenciadosAtivos);
            $data['estados_cobertos'] = count(BrazilStates::groupByState($licenciadosAtivos));
        }

        ApiResponse::json($data);
    }
}
