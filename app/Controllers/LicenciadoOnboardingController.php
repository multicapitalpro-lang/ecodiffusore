<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ClickSignClient;
use App\Core\ContractTemplateFiller;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\LicenciadoEnvelope;
use App\Models\User;

class LicenciadoOnboardingController
{
    public function showProfileForm(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== 'licenciado' || $user['licenciado_onboarding_status'] !== 'aguardando_perfil') {
            Router::redirect('/painel');
        }

        View::render('painel/licenciados/onboarding_form', [
            'user' => $user,
            'errors' => [],
            'old' => [],
        ], null);
    }

    public function submitProfileForm(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== 'licenciado' || $user['licenciado_onboarding_status'] !== 'aguardando_perfil') {
            Router::redirect('/painel');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/licenciados/completar-perfil?erro=1');
        }

        $data = [
            'razao_social' => trim($_POST['razao_social'] ?? ''),
            'cnpj' => preg_replace('/\D/', '', $_POST['cnpj'] ?? ''),
            'endereco_empresa' => trim($_POST['endereco_empresa'] ?? ''),
            'cpf_representante' => preg_replace('/\D/', '', $_POST['cpf_representante'] ?? ''),
            'rg_representante' => trim($_POST['rg_representante'] ?? ''),
            'estado_civil' => trim($_POST['estado_civil'] ?? ''),
            'profissao' => trim($_POST['profissao'] ?? ''),
        ];

        $errors = [];
        if ($data['razao_social'] === '') {
            $errors['razao_social'] = 'Informe a razão social.';
        }
        if (strlen($data['cnpj']) !== 14) {
            $errors['cnpj'] = 'CNPJ inválido (14 dígitos).';
        }
        if ($data['endereco_empresa'] === '') {
            $errors['endereco_empresa'] = 'Informe o endereço completo com CEP.';
        }
        if (strlen($data['cpf_representante']) !== 11) {
            $errors['cpf_representante'] = 'CPF inválido (11 dígitos).';
        }
        if ($data['rg_representante'] === '') {
            $errors['rg_representante'] = 'Informe o RG.';
        }
        if ($data['estado_civil'] === '') {
            $errors['estado_civil'] = 'Informe o estado civil.';
        }
        if ($data['profissao'] === '') {
            $errors['profissao'] = 'Informe a profissão.';
        }

        $uploadedFile = null;
        try {
            $uploadedFile = FileUpload::storeLicenciadoDocument($_FILES['comprovante_residencia'] ?? []);
        } catch (\RuntimeException $e) {
            $errors['comprovante_residencia'] = $e->getMessage();
        }
        if (!$uploadedFile && !isset($errors['comprovante_residencia'])) {
            $errors['comprovante_residencia'] = 'Envie o comprovante de residência (PDF, JPG, PNG ou WEBP).';
        }

        if ($errors) {
            View::render('painel/licenciados/onboarding_form', [
                'user' => $user,
                'errors' => $errors,
                'old' => $_POST,
            ], null);
            return;
        }

        $data['comprovante_residencia_path'] = $uploadedFile['stored_name'];
        User::completeOnboardingProfile((int) $user['id'], $data);

        try {
            $this->createSigningEnvelope((int) $user['id']);
        } catch (\Throwable $e) {
            // Mantem em aguardando_perfil pra poder tentar de novo -- o perfil ja foi salvo, so a
            // parte do ClickSign falhou (ex: conta ainda sem "e-mail do usuario da API" configurado).
            User::setOnboardingStatus((int) $user['id'], 'aguardando_perfil');
            View::render('painel/licenciados/onboarding_form', [
                'user' => User::find((int) $user['id']),
                'errors' => ['geral' => 'Não foi possível gerar o contrato agora. Tente novamente em alguns minutos ou fale com o suporte.'],
                'old' => $_POST,
            ], null);
            return;
        }

        Router::redirect('/painel/licenciados/aguardando-assinatura');
    }

    private function createSigningEnvelope(int $userId): void
    {
        $user = User::find($userId);
        $client = new ClickSignClient();

        $contractBase64 = ContractTemplateFiller::fillLicenciadoContract($user);

        $envelope = $client->createEnvelope('Contrato Licenciado — ' . $user['razao_social']);
        $document = $client->uploadDocument($envelope['id'], 'contrato_licenciado.docx', $contractBase64);
        $signer = $client->addSigner($envelope['id'], [
            'name' => $user['name'],
            'email' => $user['email'],
            'documentation' => ContractTemplateFiller::formatCpf($user['cpf_representante']),
            'phone_number' => $user['whatsapp'],
        ]);
        $client->addSignRequirement($envelope['id'], $document['id'], $signer['id']);
        $client->addKycRequirements($envelope['id'], $document['id'], $signer['id']);
        $client->activateEnvelope($envelope['id']);

        $activated = $client->getEnvelope($envelope['id']);
        $signingUrl = $activated['data']['attributes']['widget_signature_url']
            ?? $activated['data']['attributes']['signing_url']
            ?? null;

        LicenciadoEnvelope::create([
            'user_id' => $userId,
            'clicksign_envelope_id' => $envelope['id'],
            'clicksign_document_id' => $document['id'],
            'clicksign_signer_id' => $signer['id'],
            'signing_url' => $signingUrl,
            'status' => 'running',
        ]);
    }

    public function showWaitingPage(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if ($user['role_slug'] !== 'licenciado') {
            Router::redirect('/painel');
        }
        if ($user['licenciado_onboarding_status'] === 'ativo') {
            Router::redirect('/painel');
        }
        if ($user['licenciado_onboarding_status'] === 'aguardando_perfil') {
            Router::redirect('/painel/licenciados/completar-perfil');
        }

        $envelope = LicenciadoEnvelope::findLatestByUser((int) $user['id']);

        View::render('painel/licenciados/aguardando_assinatura', [
            'user' => $user,
            'envelope' => $envelope,
        ], null);
    }

    public function refreshStatus(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || $user['role_slug'] !== 'licenciado') {
            Router::redirect('/painel/licenciados/aguardando-assinatura');
        }

        $envelope = LicenciadoEnvelope::findLatestByUser((int) $user['id']);
        if ($envelope) {
            try {
                $remote = (new ClickSignClient())->getEnvelope($envelope['clicksign_envelope_id']);
                $remoteStatus = $remote['data']['attributes']['status'] ?? null;

                if (in_array($remoteStatus, ['closed', 'auto_closed'], true) && $envelope['status'] !== 'closed') {
                    LicenciadoEnvelope::updateStatus((int) $envelope['id'], 'closed');
                    User::setOnboardingStatus((int) $user['id'], 'ativo');
                }
            } catch (\Throwable $e) {
                // Sem sorte agora -- o usuario ve o mesmo status de antes e pode tentar de novo.
            }
        }

        Router::redirect('/painel/licenciados/aguardando-assinatura');
    }

    public function downloadComprovante(string $id): void
    {
        Auth::requireLogin();
        $viewer = Auth::user();
        $target = User::find((int) $id);

        if (!$target || !$target['comprovante_residencia_path']) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }
        if ((int) $viewer['id'] !== (int) $target['id'] && !in_array($viewer['role_slug'], Roles::USER_MANAGEMENT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $path = FileUpload::path('licenciados', $target['comprovante_residencia_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: inline; filename="comprovante-residencia-' . (int) $id . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function downloadContract(string $envelopeId): void
    {
        Auth::requireLogin();
        $viewer = Auth::user();
        $envelope = LicenciadoEnvelope::find((int) $envelopeId);

        if (!$envelope || !$envelope['signed_document_path']) {
            http_response_code(404);
            exit('Contrato assinado ainda não disponível.');
        }
        if ((int) $viewer['id'] !== (int) $envelope['user_id'] && !in_array($viewer['role_slug'], Roles::USER_MANAGEMENT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $path = FileUpload::path('licenciados', $envelope['signed_document_path']);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="contrato-licenciado-' . (int) $envelopeId . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
