<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Approval;
use App\Models\Lead;
use App\Models\LeadRoutingSettings;
use App\Models\MachineQuoteRequest;
use App\Models\User;
use App\Models\WarrantyRequest;

/** Fase 134: contadores pros badges das abas do app (Leads/Aprovacoes/Mais) -- pedido explicito
 *  do usuario, mesmo espirito dos badges que ja existem no menu do painel web (ver
 *  app/Views/layouts/painel.php). Espelha o MESMO escopo por papel de cada tela de origem, pra
 *  o numero bater exatamente com o que a pessoa veria abrindo a tela. Endpoint leve, sem
 *  paginacao/filtro -- so os totais, chamado no load do app e ao focar cada aba. */
class BadgeController
{
    public function summary(): void
    {
        $user = ApiAuth::requireUser();
        $role = $user['role_slug'];

        if (!in_array($role, Roles::STAFF, true)) {
            ApiResponse::json(['leads' => 0, 'aprovacoes' => 0, 'mais' => 0]);
        }

        $leadsNovos = match (true) {
            $role === 'admin' => Lead::countNew(null, true),
            $role === Roles::SELLER => Lead::countNew(User::downlineIds((int) $user['id']), false),
            $role === 'supervisor' => Lead::countNew(User::supervisedIds((int) $user['id']), false),
            $role === 'gerente' => Lead::countNew(User::nationalIds((int) $user['id']), false),
            default => Lead::countNew(User::downlineIds((int) $user['id']), LeadRoutingSettings::canSeeUnassigned($user)),
        };

        $aprovacoes = 0;
        if (in_array($role, ['gestor', 'licenciado', 'gerente', 'supervisor', 'admin'], true)) {
            $aprovacoes += Approval::countPendingForUser($user);
        }
        if (in_array($role, ['vendedor', 'gestor', 'licenciado'], true)) {
            $aprovacoes += Approval::countMyPendingRequests((int) $user['id']);
        }

        $mais = 0;
        $mqScope = match (true) {
            $role === 'admin' => null,
            $role === Roles::SELLER => [(int) $user['id']],
            $role === 'supervisor' => User::supervisedIds((int) $user['id']),
            $role === 'gerente' => User::nationalIds((int) $user['id']),
            default => User::downlineIds((int) $user['id']),
        };
        $mais += MachineQuoteRequest::countPending($mqScope);

        if (in_array($role, Roles::SUPERVISOR_ASSIGNMENT, true)) {
            $mais += WarrantyRequest::countPending($role === 'admin' ? null : User::nationalIds((int) $user['id']));
        }

        ApiResponse::json([
            'leads' => $leadsNovos,
            'aprovacoes' => $aprovacoes,
            'mais' => $mais,
        ]);
    }
}
