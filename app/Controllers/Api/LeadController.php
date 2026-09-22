<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Notifier;
use App\Core\Roles;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadRoutingSettings;
use App\Models\LeadStage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;

/** Fase 89: CRM/Leads pro app com paridade de conteudo do painel web
 *  (App\Controllers\LeadController) -- card rico (veiculo/origem/badges de atencao/observacoes)
 *  + detalhe + mudar status/atribuir/anotar, em vez de so abrir o WhatsApp direto como era antes.
 *  Sem upload de anexo na observacao aqui (multipart do app fica pra depois se pedirem -- o
 *  mesmo corte ja feito pras fotos de MachineQuoteController). */
class LeadController
{
    private const EXPIRATION_WARNING_DAYS = 5;
    private const EARLY_WARNING_DAYS = 15;

    public function index(): void
    {
        $user = ApiAuth::requireUser();
        $role = $user['role_slug'];

        if (!in_array($role, Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Leads.', 403);
        }

        $leads = $this->scopedLeads($user);
        $enriched = $this->enrichLeads($leads, $user);

        $isViewOnly = in_array($role, array_merge([Roles::SELLER], Roles::NATIONAL_SUPPORT), true);

        ApiResponse::json([
            'leads' => array_map(fn ($l) => $this->publicLead($l), $enriched),
            'can_assign' => !$isViewOnly,
            'can_add_notes' => !in_array($role, Roles::NATIONAL_SUPPORT, true),
        ]);
    }

    public function show(string $id): void
    {
        $user = ApiAuth::requireUser();
        $id = (int) $id;

        if (!in_array($id, array_column($this->scopedLeads($user), 'id'), true)) {
            ApiResponse::error('Lead nao encontrado.', 404);
        }

        $lead = Lead::find($id);
        if (!$lead) {
            ApiResponse::error('Lead nao encontrado.', 404);
        }

        $enriched = $this->enrichLeads([$lead], $user);
        $role = $user['role_slug'];
        $isViewOnly = in_array($role, array_merge([Roles::SELLER], Roles::NATIONAL_SUPPORT), true);

        ApiResponse::json([
            'lead' => $this->publicLead($enriched[0]),
            'notes' => array_map(fn ($n) => [
                'id' => (int) $n['id'],
                'note' => $n['note'],
                'user_name' => $n['user_name'] ?? 'Sistema',
                'created_at' => $n['created_at'],
                'follow_up_date' => $n['follow_up_date'],
                'follow_up_done' => !empty($n['follow_up_done']),
                'has_attachment' => !empty($n['attachment_path']),
            ], LeadNote::forLead($id)),
            'can_assign' => !$isViewOnly,
            'can_add_notes' => !in_array($role, Roles::NATIONAL_SUPPORT, true),
        ]);
    }

    /** Vendedores da propria equipe + colunas do kanban -- pro app montar os seletores de
     *  atribuir/mudar status sem precisar de outra chamada por tela. */
    public function options(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Leads.', 403);
        }

        ApiResponse::json([
            'sellers' => array_map(fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name']], $this->sellerOptions($user)),
            'stages' => array_map(fn ($s) => ['slug' => $s['slug'], 'name' => $s['name']], LeadStage::all()),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            ApiResponse::error('Gerente e Supervisor tem acesso de visualizacao.', 403);
        }

        $id = (int) $id;
        if (!in_array($id, array_column($this->scopedLeads($user), 'id'), true)) {
            ApiResponse::error('Lead nao encontrado.', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $status = $body['status'] ?? '';
        if (!in_array($status, LeadStage::allSlugs(), true)) {
            ApiResponse::error('Status invalido.', 422);
        }

        Lead::updateStatus($id, $status);
        ApiResponse::json(['ok' => true]);
    }

    public function assign(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::MANAGEMENT, true)) {
            ApiResponse::error('Sem permissao pra atribuir leads.', 403);
        }

        $id = (int) $id;
        if (!in_array($id, array_column($this->scopedLeads($user), 'id'), true)) {
            ApiResponse::error('Lead nao encontrado.', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $sellerId = !empty($body['seller_id']) ? (int) $body['seller_id'] : null;
        if ($sellerId !== null && $user['role_slug'] !== 'admin'
            && !in_array($sellerId, User::downlineIds((int) $user['id']), true)) {
            ApiResponse::error('Vendedor fora da sua equipe.', 422);
        }

        Lead::assignTo($id, $sellerId);
        if ($sellerId !== null) {
            $lead = Lead::find($id);
            if ($lead) {
                Notifier::leadRoteado($lead, $sellerId);
            }
        }

        ApiResponse::json(['ok' => true]);
    }

    public function storeNote(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (in_array($user['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            ApiResponse::error('Gerente e Supervisor tem acesso de visualizacao.', 403);
        }

        $id = (int) $id;
        if (!in_array($id, array_column($this->scopedLeads($user), 'id'), true)) {
            ApiResponse::error('Lead nao encontrado.', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $note = trim($body['note'] ?? '');
        if ($note === '') {
            ApiResponse::error('Escreva a observacao.', 422);
        }

        $followUpDate = trim($body['follow_up_date'] ?? '');
        if ($followUpDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followUpDate)) {
            $followUpDate = '';
        }

        LeadNote::create($id, (int) $user['id'], $note, $followUpDate !== '' ? $followUpDate : null, null);

        ApiResponse::json([
            'notes' => array_map(fn ($n) => [
                'id' => (int) $n['id'],
                'note' => $n['note'],
                'user_name' => $n['user_name'] ?? 'Sistema',
                'created_at' => $n['created_at'],
                'follow_up_date' => $n['follow_up_date'],
                'follow_up_done' => !empty($n['follow_up_done']),
                'has_attachment' => !empty($n['attachment_path']),
            ], LeadNote::forLead($id)),
        ], 201);
    }

    /** Mesmo enriquecimento de App\Controllers\LeadController::index() (badges de atencao, dados
     *  do veiculo, pedido do checkout, observacoes) -- reaproveitado por index() e show(). */
    private function enrichLeads(array $leads, array $user): array
    {
        $showLicenciadoBadge = in_array($user['role_slug'], ['supervisor', 'gerente'], true);
        $terminalStages = ['convertido', 'descartado'];
        $centralLicenciadoId = LeadRoutingSettings::centralLicenciadoId();
        $today = date('Y-m-d');
        $leadIds = array_column($leads, 'id');
        $pendingFollowUps = LeadNote::pendingFollowUps($leadIds);
        $notesByLead = LeadNote::forLeads($leadIds);
        $expiringQuotes = Quote::expiringSoonForLeads($leadIds);
        $now = time();

        foreach ($leads as &$l) {
            if ($showLicenciadoBadge) {
                $l['licenciado_name'] = User::licenciadoNameFor((int) ($l['assigned_to_user_id'] ?? 0));
            }

            $order = null;
            if (in_array($l['status'], ['checkout_acessado', 'termos_aceitos', 'pagamento_gerado', 'pagamento_pendente', 'convertido'], true)) {
                $order = Order::forLead((int) $l['id']);
            }
            if ($order) {
                $l['order_id'] = (int) $order['id'];
                $l['order_status'] = $order['status'];
                $l['order_document'] = $order['client_document'];
                $payments = Payment::forPayable('order', (int) $order['id']);
                $l['order_installments'] = $payments[0]['installments'] ?? null;
                $l['order_paid'] = ($payments[0]['status'] ?? null) === 'pago';
            }

            $l['days_until_expiration'] = $l['expires_at']
                ? (int) ceil((strtotime($l['expires_at']) - $now) / 86400)
                : null;
            $followUpDate = $pendingFollowUps[(int) $l['id']] ?? null;
            $l['follow_up_due'] = $followUpDate !== null && $followUpDate <= $today ? $followUpDate : null;
            $l['notes'] = $notesByLead[(int) $l['id']] ?? [];
            $l['quote_expiring'] = $expiringQuotes[(int) $l['id']] ?? null;

            $neverContactedStale = empty($l['notes']) && !empty($l['created_at'])
                && (time() - strtotime($l['created_at'])) >= 3 * 86400;
            $l['is_urgent'] = !in_array($l['status'], $terminalStages, true)
                && (!empty($l['follow_up_due']) || !empty($l['quote_expiring']) || $neverContactedStale);

            $isTerminal = in_array($l['status'], $terminalStages, true);
            $l['is_unassigned'] = !$isTerminal && empty($l['assigned_to_user_id']);
            $l['is_unattended'] = !$isTerminal && !empty($l['assigned_to_user_id']) && empty($l['notes']);
            $l['is_central'] = $centralLicenciadoId !== null && (int) ($l['assigned_to_user_id'] ?? 0) === $centralLicenciadoId;
        }
        unset($l);

        return $leads;
    }

    private function publicLead(array $l): array
    {
        $stages = array_column(LeadStage::all(), 'name', 'slug');

        $vehicleLabels = [
            'vehicle_plate' => 'Placa', 'vehicle_year' => 'Ano', 'vehicle_brand' => 'Marca',
            'vehicle_model' => 'Modelo', 'vehicle_power' => 'Potência', 'vehicle_ecu_status' => 'Situação da ECU',
            'vehicle_reprogrammed_power' => 'Potência reprogramada', 'vehicle_has_arla' => 'Usa ARLA',
            'vehicle_has_telemetry' => 'Telemetria',
        ];
        $vehicle = [];
        foreach ($vehicleLabels as $field => $label) {
            if (!empty($l[$field])) {
                $value = $l[$field];
                if ($field === 'vehicle_ecu_status') {
                    $value = $value === 'original' ? 'Original' : 'Reprogramado';
                } elseif (in_array($field, ['vehicle_has_arla', 'vehicle_has_telemetry'], true)) {
                    $value = $value === 'sim' ? 'Sim' : 'Não';
                }
                $vehicle[$label] = $value;
            }
        }

        return [
            'id' => (int) $l['id'],
            'name' => $l['name'],
            'whatsapp' => $l['whatsapp'],
            'city' => $l['city'],
            'status' => $l['status'],
            'status_label' => $stages[$l['status']] ?? $l['status'],
            'assigned_to_user_id' => $l['assigned_to_user_id'] !== null ? (int) $l['assigned_to_user_id'] : null,
            'assigned_name' => $l['assigned_name'] ?? null,
            'licenciado_name' => $l['licenciado_name'] ?? null,
            'source' => $l['source'],
            'truck_brand' => $l['truck_brand'],
            'message' => $l['message'],
            'created_at' => $l['created_at'],
            'vehicle' => $vehicle,
            'notes_count' => count($l['notes'] ?? []),
            'is_unassigned' => !empty($l['is_unassigned']),
            'is_unattended' => !empty($l['is_unattended']),
            'is_central' => !empty($l['is_central']),
            'is_urgent' => !empty($l['is_urgent']),
            'days_until_expiration' => $l['days_until_expiration'],
            'expiration_warning_days' => self::EXPIRATION_WARNING_DAYS,
            'early_warning_days' => self::EARLY_WARNING_DAYS,
            'follow_up_due' => $l['follow_up_due'] ?? null,
            'quote_expiring' => $l['quote_expiring'] ?? null,
            'order_id' => $l['order_id'] ?? null,
            'order_status' => $l['order_status'] ?? null,
            'order_document' => $l['order_document'] ?? null,
            'order_installments' => $l['order_installments'] ?? null,
            'order_paid' => !empty($l['order_paid']),
        ];
    }

    private function sellerOptions(array $user): array
    {
        $sellers = User::allByRole('vendedor');
        if ($user['role_slug'] === 'admin') {
            return $sellers;
        }

        $downline = User::downlineIds((int) $user['id']);
        return array_values(array_filter($sellers, fn ($s) => in_array((int) $s['id'], $downline, true)));
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
        return Lead::forScope(User::downlineIds((int) $user['id']), LeadRoutingSettings::canSeeUnassigned($user));
    }
}
