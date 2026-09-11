<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ClickSignClient;
use App\Core\Csrf;
use App\Core\Notifier;
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

        $licenciado = User::find($id);
        if ($licenciado) {
            Notifier::cadastroAprovado($licenciado);
        }

        Router::redirect('/painel/licenciados/aprovacoes?sucesso=1');
    }

    /** Pra quando o contrato assinado nao aparece (falha ao baixar do ClickSign, envelope
     *  recusado/expirado, ou qualquer motivo que deixe o Admin/Gerente sem provar a assinatura) --
     *  gera um envelope NOVO no ClickSign reaproveitando os dados de perfil ja preenchidos (nao
     *  precisa refazer o formulario) e volta o Licenciado pra 'aguardando_assinatura', que ja cai
     *  automaticamente na tela de assinatura no proximo login dele (gate central em
     *  Auth::requireRole(), Fase 44) -- satisfaz as duas opcoes pedidas ("enviar pra ele assinar
     *  de novo" + "quando ele logar pedir assinatura") com a mesma acao. */
    public function resendSignature(string $id): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=1');
        }

        $target = User::find($id);
        if (!$target || $target['role_slug'] !== 'licenciado') {
            Router::redirect('/painel/licenciados/aprovacoes?erro=1');
        }

        try {
            (new LicenciadoOnboardingController())->createSigningEnvelope($id);
        } catch (\Throwable $e) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=3');
        }

        User::setOnboardingStatus($id, 'aguardando_assinatura');
        AuditLog::record((int) $user['id'], 'licenciado_reenvio_assinatura', 'user', $id, [], []);

        Router::redirect('/painel/licenciados/aprovacoes?sucesso=1');
    }

    /** Pro caso (confirmado acontecer ao vivo, 2026-09-11) do download do PDF assinado falhar de
     *  forma transitoria tanto no webhook quanto no refreshStatus() do proprio Licenciado (API do
     *  ClickSign instavel naquele momento, nao um erro permanente) -- so tenta baixar de novo o
     *  documento do MESMO envelope ja fechado, sem gerar um envelope novo nem pedir assinatura de
     *  novo (diferente de resendSignature()). */
    public function retryDownloadContract(string $envelopeId): void
    {
        Auth::requireRole(Roles::SUPERVISOR_ASSIGNMENT);
        $envelopeId = (int) $envelopeId;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=1');
        }

        $envelope = LicenciadoEnvelope::find($envelopeId);
        if (!$envelope) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=1');
        }

        try {
            $content = (new ClickSignClient())->downloadSignedDocument($envelope['clicksign_envelope_id'], $envelope['clicksign_document_id']);
            $storedName = bin2hex(random_bytes(16)) . '.pdf';
            $dir = BASE_PATH . '/storage/uploads/licenciados';
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            file_put_contents($dir . '/' . $storedName, $content);
            LicenciadoEnvelope::attachSignedDocument($envelopeId, $storedName);
        } catch (\Throwable $e) {
            Router::redirect('/painel/licenciados/aprovacoes?erro=4');
        }

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
