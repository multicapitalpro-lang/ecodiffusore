<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Commission;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\User;

/** Fase 76d: Comissoes pro app -- mesmo escopo (commissionFilters/commissionManageScope) de
 *  App\Controllers\FinanceController, devolvendo JSON. */
class CommissionController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Comissoes.', 403);
        }

        $filters = array_merge($this->commissionFilters($user), [
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
        ]);

        $commissions = Commission::all($filters);
        $summary = ['total' => 0.0, 'pago' => 0.0, 'pendente' => 0.0, 'count' => count($commissions)];
        $manageScope = $this->commissionManageScope($user);

        $items = array_map(function ($c) use (&$summary, $manageScope) {
            $summary['total'] += (float) $c['amount'];
            $summary[$c['status']] += (float) $c['amount'];

            return [
                'id' => (int) $c['id'],
                'order_id' => (int) $c['order_id'],
                'beneficiary_name' => $c['beneficiary_name'],
                'seller_name' => $c['seller_name'],
                'client_name' => $c['client_name'],
                'role_slug' => $c['role_slug'],
                'percentage' => (float) $c['percentage'],
                'amount' => (float) $c['amount'],
                'status' => $c['status'],
                'order_date' => $c['order_date'],
                'can_manage' => in_array((int) $c['beneficiary_id'], $manageScope, true),
            ];
        }, $commissions);

        ApiResponse::json(['commissions' => $items, 'summary' => $summary]);
    }

    public function markPaid(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT), true)) {
            ApiResponse::error('Sem permissao pra confirmar pagamento.', 403);
        }

        $id = (int) $id;
        $commission = Commission::find($id);
        $manageScope = $this->commissionManageScope($user);
        if (!$commission || !in_array((int) $commission['beneficiary_id'], $manageScope, true)) {
            ApiResponse::error('Sem permissao pra confirmar esta comissao.', 403);
        }

        if ($commission['status'] === 'pendente') {
            $transactionId = null;
            $accountId = FinancialAccount::defaultAccountId();
            if ($accountId) {
                $transactionId = FinancialTransaction::create([
                    'account_id' => $accountId,
                    'order_id' => $commission['order_id'],
                    'category_id' => FinancialCategory::commissionCategoryId(),
                    'type' => 'saida',
                    'description' => 'Comissão · Pedido #' . $commission['order_id'] . ' · ' . $commission['beneficiary_name'],
                    'amount' => (float) $commission['amount'],
                    'due_date' => date('Y-m-d'),
                    'paid_date' => date('Y-m-d'),
                    'status' => 'pago',
                ]);
            }
            Commission::markPaid($id, $transactionId);
        }

        ApiResponse::json(['ok' => true]);
    }

    private function commissionFilters(array $user): array
    {
        if ($user['role_slug'] === Roles::SELLER) {
            return ['beneficiary_id' => $user['id']];
        }
        if (in_array($user['role_slug'], [Roles::REGIONAL_OWNER, 'gestor'], true)) {
            return ['beneficiary_ids' => User::downlineIds((int) $user['id'])];
        }
        if ($user['role_slug'] === 'gerente') {
            $ids = array_merge([(int) $user['id']], $this->licenciadoIdsWithin(User::nationalIds((int) $user['id'])));
            return ['beneficiary_ids' => array_values(array_unique($ids))];
        }
        if ($user['role_slug'] === 'supervisor') {
            $ids = array_merge([(int) $user['id']], $this->licenciadoIdsWithin(User::supervisedIds((int) $user['id'])));
            return ['beneficiary_ids' => array_values(array_unique($ids))];
        }

        return [];
    }

    private function commissionManageScope(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            return array_map(fn ($u) => (int) $u['id'], User::all());
        }
        if ($role === 'gerente') {
            return $this->licenciadoIdsWithin(User::nationalIds((int) $user['id']));
        }
        if ($role === 'supervisor') {
            return $this->licenciadoIdsWithin(User::supervisedIds((int) $user['id']));
        }
        if (in_array($role, [Roles::REGIONAL_OWNER, 'gestor'], true)) {
            return array_values(array_diff(User::downlineIds((int) $user['id']), [(int) $user['id']]));
        }

        return [];
    }

    private function licenciadoIdsWithin(array $ids): array
    {
        return array_values(array_filter($ids, fn ($id) => (User::find($id)['role_slug'] ?? null) === Roles::REGIONAL_OWNER));
    }
}
