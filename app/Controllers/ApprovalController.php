<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Approval;

class ApprovalController
{
    /** Fase 57b: painel central de liberacoes -- antes so' dava pra ver a pendencia entrando no
     *  Pedido/Orcamento especifico (banner). Lista so' as pendencias que ESSE usuario pode
     *  decidir (Approval::canDecide() por linha -- volume baixo, filtro em PHP e' suficiente,
     *  evita duplicar a regra de autorizacao numa query SQL separada). */
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $pending = array_values(array_filter(
            Approval::allPending(),
            fn ($a) => Approval::canDecide($a, $user)
        ));

        View::render('painel/approvals/index', [
            'user' => $user,
            'pending' => $pending,
            'mine' => Approval::forOwnRequests((int) $user['id']),
            'decided' => Approval::forDecisionsBy((int) $user['id']),
        ]);
    }

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

        $updated = Approval::decide($id, $decision, (int) $user['id']);

        AuditLog::record(
            (int) $user['id'],
            "aprovacao_desconto_{$decision}",
            $approval['approvable_type'],
            (int) $approval['approvable_id'],
            ['status' => Approval::statusLabel($approval)],
            ['status' => Approval::statusLabel($updated ?: $approval)]
        );

        Router::redirect($returnTo . '?sucesso=1');
    }
}
