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
        // Fase 57: quem decide agora depende do papel de quem pediu (Approval::canDecide()) --
        // o gate aqui so garante que e' alguem da equipe interna, a autorizacao fina roda abaixo.
        Auth::requireRole(Roles::STAFF);
        $id = (int) $id;

        $approval = Approval::find($id);
        if (!$approval) {
            Router::redirect('/painel');
        }

        $user = Auth::user();
        if (!Approval::canDecide($approval, $user)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
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

        Approval::decide($id, $decision, (int) $user['id']);

        AuditLog::record(
            (int) $user['id'],
            "aprovacao_desconto_{$decision}",
            $approval['approvable_type'],
            (int) $approval['approvable_id'],
            ['status' => 'pendente', 'preco_solicitado' => $approval['requested_price'] ?? $approval['requested_discount_pct']],
            ['status' => $decision]
        );

        Router::redirect($returnTo . '?sucesso=1');
    }
}
