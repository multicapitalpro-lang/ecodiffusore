<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Commission;
use App\Models\User;

/** Fase 76i: Desempenho da equipe pro app -- mesmo escopo por hierarquia de
 *  App\Controllers\PerformanceController::team(), mas devolve LISTA PLANA (nao arvore aninhada)
 *  ordenada por comissao total -- arvore de organograma nao funciona bem em tela de celular,
 *  uma lista ordenada por quem mais vendeu e' mais util no dia a dia mobile. */
class PerformanceController
{
    public function team(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], array_merge(Roles::MANAGEMENT, Roles::NATIONAL_SUPPORT), true)) {
            ApiResponse::error('Sem permissao pra ver Desempenho da equipe.', 403);
        }

        if ($user['role_slug'] === 'admin') {
            $users = User::all();
        } elseif ($user['role_slug'] === 'gerente') {
            $scopeIds = User::nationalIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $scopeIds, true)));
        } elseif ($user['role_slug'] === 'supervisor') {
            $scopeIds = User::supervisedIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $scopeIds, true)));
        } else {
            $downline = User::downlineIds((int) $user['id']);
            $users = array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $downline, true)));
        }

        $beneficiaryIds = array_map(fn ($u) => (int) $u['id'], $users);
        $totals = [];
        foreach (Commission::byBeneficiary(['beneficiary_ids' => $beneficiaryIds]) as $row) {
            $totals[(int) $row['beneficiary_id']] = $row;
        }

        $byId = [];
        foreach ($users as $u) {
            $byId[(int) $u['id']] = $u;
        }

        $items = array_map(function ($u) use ($totals, $byId) {
            $id = (int) $u['id'];
            $t = $totals[$id] ?? null;
            $manager = !empty($u['manager_id']) ? ($byId[(int) $u['manager_id']]['name'] ?? null) : null;

            return [
                'id' => $id,
                'name' => $u['name'],
                'role_slug' => $u['role_slug'],
                'role_name' => $u['role_name'],
                'manager_name' => $manager,
                'city' => $u['city'] ?? null,
                'state' => $u['state'] ?? null,
                'commission_total' => $t ? (float) $t['total'] : 0.0,
                'commission_pago' => $t ? (float) $t['total_pago'] : 0.0,
                'commission_pendente' => $t ? (float) $t['total_pendente'] : 0.0,
            ];
        }, $users);

        usort($items, fn ($a, $b) => $b['commission_total'] <=> $a['commission_total']);

        ApiResponse::json(['team' => $items]);
    }
}
