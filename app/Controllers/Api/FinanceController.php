<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Core\SubscriptionGate;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;

/** Fase 76k: Caixas/Bancos e Contas a Pagar/Receber pro app -- mesmo escopo (MANAGEMENT) de
 *  App\Controllers\FinanceController. So leitura + confirmar pagamento por enquanto -- lancar
 *  conta nova (categoria/recorrencia/anexo) fica no painel web, formulario grande demais pra
 *  fazer sentido no celular por ora. */
class FinanceController
{
    public function accounts(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Financeiro.', 403);
        }

        $accounts = FinancialAccount::all();
        foreach ($accounts as &$account) {
            $account['balance'] = FinancialAccount::currentBalance((int) $account['id']);
        }
        unset($account);

        ApiResponse::json(['accounts' => array_map(fn ($a) => [
            'id' => (int) $a['id'],
            'name' => $a['name'],
            'balance' => (float) $a['balance'],
        ], $accounts)]);
    }

    public function payable(): void
    {
        $this->ledger('saida');
    }

    public function receivable(): void
    {
        $this->ledger('entrada');
    }

    private function ledger(string $type): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra ver Financeiro.', 403);
        }

        $filters = array_merge(['type' => $type, 'exclude_transfers' => true], $this->scopeFilters($user));
        $transactions = FinancialTransaction::all($filters);
        $today = date('Y-m-d');

        $summary = ['open_count' => 0, 'open_total' => 0.0, 'paid_total' => 0.0, 'overdue_count' => 0, 'overdue_total' => 0.0];
        $items = [];
        foreach ($transactions as $t) {
            $total = FinancialTransaction::totalValue($t);
            $overdue = $t['status'] === 'pendente' && $t['due_date'] < $today;

            if ($t['status'] === 'pendente') {
                $summary['open_count']++;
                $summary['open_total'] += $total;
                if ($overdue) {
                    $summary['overdue_count']++;
                    $summary['overdue_total'] += $total;
                }
            } else {
                $summary['paid_total'] += $total;
            }

            $items[] = [
                'id' => (int) $t['id'],
                'description' => $t['description'],
                'client_name' => $t['client_name'] ?? null,
                'category_name' => $t['category_name'] ?? null,
                'account_name' => $t['account_name'],
                'amount' => $total,
                'status' => $t['status'],
                'due_date' => $t['due_date'],
                'overdue' => $overdue,
            ];
        }

        ApiResponse::json(['transactions' => $items, 'summary' => $summary]);
    }

    public function markPaid(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra confirmar pagamento.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }

        $id = (int) $id;
        $transaction = FinancialTransaction::find($id);
        if (!$transaction) {
            ApiResponse::error('Lancamento nao encontrado.', 404);
        }

        FinancialTransaction::markPaid($id, date('Y-m-d'));

        ApiResponse::json(['ok' => true]);
    }

    private function scopeFilters(array $user): array
    {
        if (in_array($user['role_slug'], [Roles::REGIONAL_OWNER, 'gestor'], true)) {
            return ['seller_ids' => User::downlineIds((int) $user['id'])];
        }
        return [];
    }
}
