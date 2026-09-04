<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\LicenciadoEnvelope;
use App\Models\User;

class LicenciadoApprovalController
{
    public function index(): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();

        $pending = User::pendingApproval($user);
        $pending = array_map(function ($licenciado) {
            $licenciado['envelope'] = LicenciadoEnvelope::findLatestByUser((int) $licenciado['id']);
            return $licenciado;
        }, $pending);

        View::render('painel/licenciados/aprovacoes', [
            'user' => $user,
            'pending' => $pending,
        ]);
    }

    /** Perfil completo (dados do formulario + documentos) de um Licenciado, pra consulta a
     * qualquer momento -- nao so durante a janela de aprovacao pendente. Mesmo escopo de
     * visibilidade que a lista /painel/licenciados ja usa hoje (gerente ve todo mundo). */
    public function show(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $target = User::find((int) $id);

        if (!$target || $target['role_slug'] !== 'licenciado') {
            Router::redirect('/painel/licenciados');
        }

        View::render('painel/licenciados/perfil', [
            'licenciado' => $target,
            'envelope' => LicenciadoEnvelope::findLatestByUser((int) $target['id']),
        ]);
    }

    public function approve(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=1');
        }

        $this->authorizeTarget($user, $id);

        User::setOnboardingStatus($id, 'ativo');
        AuditLog::record((int) $user['id'], 'licenciado_cadastro_aprovado', 'user', $id, [], []);

        Router::redirect('/painel/licenciados/aprovacoes?sucesso=1');
    }

    public function reject(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=1');
        }

        $this->authorizeTarget($user, $id);

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            Router::redirect('/painel/licenciados/aprovacoes?erro=2');
        }

        User::rejectOnboarding($id, $reason);
        AuditLog::record((int) $user['id'], 'licenciado_cadastro_reprovado', 'user', $id, [], ['motivo' => $reason]);

        Router::redirect('/painel/licenciados/aprovacoes?sucesso=1');
    }

    /** So deixa aprovar/reprovar quem esta de fato na fila de pendentes de quem esta logado. */
    private function authorizeTarget(array $user, int $id): void
    {
        $target = User::find($id);
        $allowedIds = array_map(fn ($u) => (int) $u['id'], User::pendingApproval($user));

        if (!$target || $target['role_slug'] !== 'licenciado' || !in_array($id, $allowedIds, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }
    }
}
