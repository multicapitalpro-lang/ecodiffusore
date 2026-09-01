<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Models\AuditLog;
use App\Models\Approval;

class ApprovalController
{
    public function decide(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);
        $id = (int) $id;

        $approval = Approval::find($id);
        if (!$approval) {
            Router::redirect('/painel');
        }

        $returnTo = $approval['approvable_type'] === 'order'
            ? "/painel/pedidos/{$approval['approvable_id']}"
            : "/painel/orcamentos/{$approval['approvable_id']}";

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect($returnTo);
        }

        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision, ['aprovado', 'recusado'], true)) {
            Router::redirect($returnTo);
        }

        $user = Auth::user();
        Approval::decide($id, $decision, (int) $user['id']);

        AuditLog::record(
            (int) $user['id'],
            "aprovacao_desconto_{$decision}",
            $approval['approvable_type'],
            (int) $approval['approvable_id'],
            ['status' => 'pendente', 'desconto_pct' => $approval['requested_discount_pct']],
            ['status' => $decision]
        );

        Router::redirect($returnTo . '?sucesso=1');
    }
}
