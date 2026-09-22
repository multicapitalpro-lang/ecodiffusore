<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\CompanySettings;
use App\Models\LicenciadoEmailRequest;

/**
 * E-mail profissional no dominio (Fase 84) -- ex: comercial.cascavel@ecodiffusorebrasil.com.br.
 * So' Licenciado com assinatura ativa pede (ver App\Core\SubscriptionGate); provisionamento real
 * da caixa continua manual do admin (metodos adminIndex/approve/reject abaixo), que cria a caixa
 * de verdade no hPanel da Hostinger e so' entao marca o pedido como ativo por aqui.
 */
class LicenciadoEmailController
{
    public function index(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        $settings = CompanySettings::current();
        $quota = (int) ($settings['custom_email_quota'] ?? 0);
        $used = LicenciadoEmailRequest::countTowardQuota();

        View::render('painel/emails/index', [
            'user' => $user,
            'hasAccess' => SubscriptionGate::hasAccess($user),
            'requests' => LicenciadoEmailRequest::forUser((int) $user['id']),
            'quota' => $quota,
            'used' => $used,
            'available' => max(0, $quota - $used),
            'domain' => LicenciadoEmailRequest::DOMAIN,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['licenciado']);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/emails-profissionais?erro=1');
        }
        SubscriptionGate::requireAccess($user, 'email_profissional');

        $localPart = strtolower(trim($_POST['local_part'] ?? ''));
        $settings = CompanySettings::current();
        $quota = (int) ($settings['custom_email_quota'] ?? 0);

        $errors = [];
        if ($localPart === '' || !preg_match('/^[a-z0-9._-]{2,60}$/', $localPart)) {
            $errors['local_part'] = 'Use só letras minúsculas, números, ponto, hífen ou underline (ex: comercial.cascavel).';
        } elseif (LicenciadoEmailRequest::addressTaken($localPart . '@' . LicenciadoEmailRequest::DOMAIN)) {
            $errors['local_part'] = 'Esse endereço já está em uso ou tem um pedido em andamento.';
        } elseif (LicenciadoEmailRequest::countTowardQuota() >= $quota) {
            $errors['local_part'] = 'Não há vagas de e-mail profissional disponíveis no momento — fale com o Admin.';
        }

        if ($errors) {
            View::render('painel/emails/index', [
                'user' => $user,
                'hasAccess' => true,
                'requests' => LicenciadoEmailRequest::forUser((int) $user['id']),
                'quota' => $quota,
                'used' => LicenciadoEmailRequest::countTowardQuota(),
                'available' => max(0, $quota - LicenciadoEmailRequest::countTowardQuota()),
                'domain' => LicenciadoEmailRequest::DOMAIN,
                'errors' => $errors,
            ]);
            return;
        }

        $id = LicenciadoEmailRequest::create((int) $user['id'], $localPart);
        Notifier::emailProfissionalSolicitado(LicenciadoEmailRequest::find($id));

        Router::redirect('/painel/emails-profissionais?sucesso=1');
    }

    /** Fila de pedidos pro admin -- ele cria a caixa de verdade no hPanel FORA do sistema, e so'
     *  depois marca aqui como ativo (approve), colando a senha/instrucoes em admin_note pra o
     *  Licenciado ver. */
    public function adminIndex(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/emails/admin', [
            'user' => Auth::user(),
            'requests' => LicenciadoEmailRequest::all(),
        ]);
    }

    public function approve(string $id): void
    {
        Auth::requireRole(['admin']);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/emails-profissionais/admin?erro=1');
        }

        $note = trim($_POST['admin_note'] ?? '');
        LicenciadoEmailRequest::approve((int) $id, (int) $user['id'], $note);

        Notifier::emailProfissionalDecidido(LicenciadoEmailRequest::find((int) $id), 'Ativado', $note);
        Router::redirect('/painel/emails-profissionais/admin?sucesso=1');
    }

    public function reject(string $id): void
    {
        Auth::requireRole(['admin']);
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/emails-profissionais/admin?erro=1');
        }

        $note = trim($_POST['admin_note'] ?? '');
        $request = LicenciadoEmailRequest::find((int) $id);
        LicenciadoEmailRequest::reject((int) $id, (int) $user['id'], $note);

        Notifier::emailProfissionalDecidido($request, 'Recusado', $note);
        Router::redirect('/painel/emails-profissionais/admin?sucesso=1');
    }
}
