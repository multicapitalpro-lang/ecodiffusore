<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;

/** Fase 76: CRM/Leads pro app -- mesmo escopo por hierarquia de
 *  App\Controllers\LeadController::scopedLeads(), devolvendo JSON. Sem as automacoes de tela
 *  (expireStaleAssignments/flagStalePaymentPending) aqui -- essas continuam rodando no lazy-check
 *  do painel web; nao ha necessidade de duplicar no app tambem. */
class LeadController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        $role = $user['role_slug'];

        if (!in_array($role, Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Leads.', 403);
        }

        $leads = $this->scopedLeads($user);
        $stages = array_column(LeadStage::all(), 'name', 'slug');

        $leads = array_map(function ($l) use ($stages) {
            return [
                'id' => (int) $l['id'],
                'name' => $l['name'],
                'whatsapp' => $l['whatsapp'],
                'city' => $l['city'],
                'status' => $l['status'],
                'status_label' => $stages[$l['status']] ?? $l['status'],
                'assigned_to_user_id' => $l['assigned_to_user_id'] !== null ? (int) $l['assigned_to_user_id'] : null,
                'source' => $l['source'],
                'created_at' => $l['created_at'],
            ];
        }, $leads);

        ApiResponse::json(['leads' => $leads]);
    }

    private function scopedLeads(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            return Lead::all();
        }
        if ($role === Roles::SELLER) {
            return Lead::forScope(User::downlineIds((int) $user['id']), false);
        }
        if ($role === 'supervisor') {
            return Lead::forScope(User::supervisedIds((int) $user['id']), false);
        }
        if ($role === 'gerente') {
            return Lead::forScope(User::nationalIds((int) $user['id']), false);
        }
        return Lead::forScope(User::downlineIds((int) $user['id']), true);
    }
}
