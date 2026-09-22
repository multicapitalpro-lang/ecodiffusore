<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Roles;
use App\Models\Approval;
use App\Models\AuditLog;

/** Fase 76: Liberacoes/Aprovacoes pro app -- espelha App\Controllers\ApprovalController, devolvendo
 *  JSON. Esse e' o modulo mais provavel de virar notificacao push (Fase B) -- decisao de desconto
 *  costuma ser urgente, exatamente o tipo de aviso hoje perdido em meio ao volume do WhatsApp. */
class ApprovalController
{
    public function index(): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Aprovacoes.', 403);
        }

        $pending = array_values(array_filter(
            Approval::allPending(),
            fn ($a) => Approval::canDecide($a, $user)
        ));

        $pending = array_map(fn ($a) => $this->publicApproval($a), $pending);

        ApiResponse::json([
            'pending' => $pending,
            'mine' => array_map(fn ($a) => $this->publicApproval($a), Approval::forOwnRequests((int) $user['id'])),
        ]);
    }

    public function decide(string $id): void
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a Aprovacoes.', 403);
        }

        $id = (int) $id;
        $approval = Approval::find($id);
        if (!$approval) {
            ApiResponse::error('Aprovacao nao encontrada.', 404);
        }

        if (!Approval::canDecide($approval, $user)) {
            ApiResponse::error('Sem permissao pra decidir esta aprovacao.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $decision = $body['decision'] ?? '';
        if (!in_array($decision, ['aprovado', 'recusado'], true)) {
            ApiResponse::error('decision precisa ser "aprovado" ou "recusado".', 422);
        }

        $updated = Approval::decide($id, $decision, (int) $user['id']);

        AuditLog::record(
            (int) $user['id'],
            "aprovacao_desconto_{$decision}",
            $approval['approvable_type'],
            (int) $approval['approvable_id'],
            ['status' => Approval::statusLabel($approval)],
            ['status' => Approval::statusLabel($updated ?: $approval)]
        );

        ApiResponse::json(['approval' => $this->publicApproval($updated ?: $approval)]);
    }

    private function publicApproval(array $a): array
    {
        return [
            'id' => (int) $a['id'],
            'approvable_type' => $a['approvable_type'],
            'approvable_id' => (int) $a['approvable_id'],
            'requester_role' => $a['requester_role'] ?? null,
            'requested_by_name' => $a['requested_by_name'] ?? null,
            'requested_price' => isset($a['requested_price']) ? (float) $a['requested_price'] : null,
            'justification' => $a['justification'] ?? null,
            'status' => $a['status'],
            'status_label' => Approval::statusLabel($a),
            'created_at' => $a['created_at'] ?? null,
        ];
    }
}
