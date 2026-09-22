<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Order;
use App\Models\Payment;
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
        $showLicenciadoBadge = in_array($role, ['supervisor', 'gerente'], true);

        $leads = array_map(function ($l) use ($stages, $showLicenciadoBadge) {
            // Fase 85 (paridade com a Fase 81 do painel web): dados que o COMPRADOR preencheu no
            // checkout -- CPF, parcelas escolhidas e numero do pedido gerado, mesmo criterio de
            // App\Controllers\LeadController::index() (so busca a partir de "checkout acessado").
            $order = null;
            if (in_array($l['status'], ['checkout_acessado', 'termos_aceitos', 'pagamento_gerado', 'pagamento_pendente', 'convertido'], true)) {
                $order = Order::forLead((int) $l['id']);
            }
            $payments = $order ? Payment::forPayable('order', (int) $order['id']) : [];

            return [
                'id' => (int) $l['id'],
                'name' => $l['name'],
                'whatsapp' => $l['whatsapp'],
                'city' => $l['city'],
                'status' => $l['status'],
                'status_label' => $stages[$l['status']] ?? $l['status'],
                'assigned_to_user_id' => $l['assigned_to_user_id'] !== null ? (int) $l['assigned_to_user_id'] : null,
                'licenciado_name' => $showLicenciadoBadge ? User::licenciadoNameFor((int) ($l['assigned_to_user_id'] ?? 0)) : null,
                'source' => $l['source'],
                'created_at' => $l['created_at'],
                'order_id' => $order ? (int) $order['id'] : null,
                'order_status' => $order['status'] ?? null,
                'order_document' => $order['client_document'] ?? null,
                'order_installments' => $payments[0]['installments'] ?? null,
                'order_paid' => ($payments[0]['status'] ?? null) === 'pago',
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
