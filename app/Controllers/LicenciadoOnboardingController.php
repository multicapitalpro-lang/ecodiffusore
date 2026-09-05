<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ClickSignClient;
use App\Core\Config;
use App\Core\ContractTemplateFiller;
use App\Core\Csrf;
use App\Core\FileUpload;
use App\Core\Response;
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
            'old' => $user,
            'rejectionReason' => $user['licenciado_rejection_reason'] ?? null,
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
            'endereco_cep' => preg_replace('/\D/', '', $_POST['endereco_cep'] ?? ''),
            'endereco_logradouro' => trim($_POST['endereco_logradouro'] ?? ''),
            'endereco_numero' => trim($_POST['endereco_numero'] ?? ''),
            'endereco_complemento' => trim($_POST['endereco_complemento'] ?? ''),
            'endereco_bairro' => trim($_POST['endereco_bairro'] ?? ''),
            'endereco_cidade' => trim($_POST['endereco_cidade'] ?? ''),
            'endereco_uf' => strtoupper(trim($_POST['endereco_uf'] ?? '')),
            'cpf_representante' => preg_replace('/\D/', '', $_POST['cpf_representante'] ?? ''),
            'rg_representante' => trim($_POST['rg_representante'] ?? ''),
            'estado_civil' => trim($_POST['estado_civil'] ?? ''),
            'profissao' => trim($_POST['profissao'] ?? ''),
            'celular' => preg_replace('/\D/', '', $_POST['celular'] ?? ''),
            'telefone_fixo' => preg_replace('/\D/', '', $_POST['telefone_fixo'] ?? ''),
        ];

        $errors = [];
        if ($data['razao_social'] === '') {
            $errors['razao_social'] = 'Informe a razão social.';
        }
        if (strlen($data['cnpj']) !== 14) {
            $errors['cnpj'] = 'CNPJ inválido (14 dígitos).';
        }
        if (strlen($data['endereco_cep']) !== 8) {
            $errors['endereco_cep'] = 'CEP inválido (8 dígitos).';
        }
        if ($data['endereco_logradouro'] === '') {
            $errors['endereco_logradouro'] = 'Informe o logradouro.';
        }
        if ($data['endereco_numero'] === '') {
            $errors['endereco_numero'] = 'Informe o número.';
        }
        if ($data['endereco_bairro'] === '') {
            $errors['endereco_bairro'] = 'Informe o bairro.';
        }
        if ($data['endereco_cidade'] === '') {
            $errors['endereco_cidade'] = 'Informe a cidade.';
        }
        if (strlen($data['endereco_uf']) !== 2) {
            $errors['endereco_uf'] = 'Informe a UF (2 letras).';
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
        if (strlen($data['celular']) < 10 || strlen($data['celular']) > 11) {
            $errors['celular'] = 'Celular inválido (com DDD).';
        }
        if ($data['telefone_fixo'] !== '' && (strlen($data['telefone_fixo']) < 10 || strlen($data['telefone_fixo']) > 11)) {
            $errors['telefone_fixo'] = 'Telefone fixo inválido (com DDD).';
        }

        $documentFields = [
            'comprovante_residencia' => 'Envie o comprovante de residência (PDF, JPG, PNG ou WEBP).',
            'documento_identidade' => 'Envie seu documento de identidade — RG e CPF, ou CNH (PDF, JPG, PNG ou WEBP).',
            'contrato_social' => 'Envie o contrato social da empresa (PDF, JPG, PNG ou WEBP).',
            'cartao_cnpj' => 'Envie o cartão CNPJ (PDF, JPG, PNG ou WEBP).',
        ];
        $uploadedFiles = [];
        foreach ($documentFields as $field => $requiredMessage) {
            try {
                $uploadedFiles[$field] = FileUpload::storeLicenciadoDocument($_FILES[$field] ?? []);
            } catch (\RuntimeException $e) {
                $errors[$field] = $e->getMessage();
                continue;
            }
            if (!$uploadedFiles[$field]) {
                $errors[$field] = $requiredMessage;
            }
        }

        if ($errors) {
            View::render('painel/licenciados/onboarding_form', [
                'user' => $user,
                'errors' => $errors,
                'old' => $_POST,
            ], null);
            return;
        }

        foreach ($documentFields as $field => $requiredMessage) {
            $data[$field . '_path'] = $uploadedFiles[$field]['stored_name'];
        }
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

        $templateKey = Config::get('clicksign', [])['template_key'] ?? '';
        if ($templateKey === '') {
            throw new \RuntimeException('Modelo de contrato do ClickSign não configurado (clicksign.template_key).');
        }

        $envelope = $client->createEnvelope('Contrato Licenciado — ' . $user['razao_social']);
        $document = $client->createDocumentFromTemplate(
            $envelope['id'],
            $templateKey,
            'contrato_licenciado.docx',
            ContractTemplateFiller::buildTemplateData($user)
        );
        $signer = $client->addSigner($envelope['id'], [
            'name' => $user['name'],
            'email' => $user['email'],
            'documentation' => ContractTemplateFiller::formatCpf($user['cpf_representante']),
            'phone_number' => ContractTemplateFiller::formatPhoneE164($user['whatsapp']),
        ]);
        $client->addSignRequirement($envelope['id'], $document['id'], $signer['id']);
        // KYC automatico (selfie + foto do documento) desativado: confirmado contra a API real
        // em 2026-09-05 que a conta ClickSign atual devolve 403 "A conta nao possui acesso a essa
        // funcionalidade" nesse requirement -- e' um recurso pago a parte, nao incluso no plano
        // de hoje. App\Core\ClickSignClient::addKycRequirements() continua disponivel: se a conta
        // contratar esse recurso no ClickSign, e' so trocar a linha abaixo por ela.
        // A ativacao do envelope EXIGE pelo menos um requirement de autenticacao alem do "agree"
        // de assinatura -- sem nenhum, falha com 422. email e' a unica opcao confirmada como
        // gratuita nessa conta (sms/whatsapp tambem deram 403).
        $client->addEmailAuthRequirement($envelope['id'], $document['id'], $signer['id']);
        $client->activateEnvelope($envelope['id']);

        // A API do ClickSign nao expoe uma "signing_url" pronta (confirmado contra a API real e a
        // documentacao oficial) -- a assinatura acontece via Widget Embedded, que precisa so do
        // clicksign_signer_id pra montar o iframe direto na nossa pagina (ver aguardando_assinatura.php).
        LicenciadoEnvelope::create([
            'user_id' => $userId,
            'clicksign_envelope_id' => $envelope['id'],
            'clicksign_document_id' => $document['id'],
            'clicksign_signer_id' => $signer['id'],
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
            'clicksignBaseUrl' => Config::get('clicksign', [])['base_url'] ?? 'https://app.clicksign.com',
        ], null);
    }

    public function refreshStatus(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!Csrf::verify($_POST['csrf_token'] ?? null) || $user['role_slug'] !== 'licenciado') {
            if (Response::isAjax()) {
                Response::json(['status' => $user['licenciado_onboarding_status'] ?? null]);
            }
            Router::redirect('/painel/licenciados/aguardando-assinatura');
        }

        $envelope = LicenciadoEnvelope::findLatestByUser((int) $user['id']);
        if ($envelope) {
            try {
                $remote = (new ClickSignClient())->getEnvelope($envelope['clicksign_envelope_id']);
                $remoteStatus = $remote['data']['attributes']['status'] ?? null;

                // O widget embutido reporta "assinado" no navegador as vezes antes do envelope
                // fechar de fato do lado do ClickSign (pequeno atraso de propagacao) -- por isso
                // o front tenta de novo algumas vezes em vez de checar uma unica vez.
                if (in_array($remoteStatus, ['closed', 'auto_closed'], true) && $envelope['status'] !== 'closed') {
                    LicenciadoEnvelope::updateStatus((int) $envelope['id'], 'closed');
                    User::setOnboardingStatus((int) $user['id'], 'aguardando_aprovacao');

                    try {
                        $content = (new ClickSignClient())->downloadSignedDocument($envelope['clicksign_envelope_id'], $envelope['clicksign_document_id']);
                        $storedName = bin2hex(random_bytes(16)) . '.pdf';
                        $dir = BASE_PATH . '/storage/uploads/licenciados';
                        if (!is_dir($dir)) {
                            mkdir($dir, 0750, true);
                        }
                        file_put_contents($dir . '/' . $storedName, $content);
                        LicenciadoEnvelope::attachSignedDocument((int) $envelope['id'], $storedName);
                    } catch (\Throwable $e) {
                        // Nao bloqueia a aprovacao por falha no download do PDF -- pode ser baixado depois
                        // (o webhook do ClickSign tambem tenta isso de forma independente).
                    }
                }
            } catch (\Throwable $e) {
                // Sem sorte agora -- o usuario ve o mesmo status de antes e pode tentar de novo.
            }
        }

        $fresh = User::find((int) $user['id']);

        if (Response::isAjax()) {
            Response::json(['status' => $fresh['licenciado_onboarding_status']]);
        }

        if ($fresh['licenciado_onboarding_status'] === 'ativo') {
            Router::redirect('/painel');
        }

        Router::redirect('/painel/licenciados/aguardando-assinatura');
    }

    private const DOCUMENT_TYPES = ['comprovante_residencia', 'documento_identidade', 'contrato_social', 'cartao_cnpj'];

    public function downloadDocument(string $id, string $tipo): void
    {
        Auth::requireLogin();
        $viewer = Auth::user();

        if (!in_array($tipo, self::DOCUMENT_TYPES, true)) {
            http_response_code(404);
            exit('Documento não encontrado.');
        }

        $target = User::find((int) $id);
        $field = $tipo . '_path';

        if (!$target || !$target[$field]) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }
        if ((int) $viewer['id'] !== (int) $target['id'] && !in_array($viewer['role_slug'], Roles::USER_MANAGEMENT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $path = FileUpload::path('licenciados', $target[$field]);
        if (!file_exists($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        // O nome salvo (stored_name) ja tem a extensao real do arquivo enviado -- sem repassar
        // ela pro download, o navegador salvava sem extensao e com Content-Type generico
        // (application/octet-stream), entao o SO nao sabia com o que abrir (nem em outro app,
        // pois o conteudo em si nunca teve o tipo declarado corretamente).
        $extension = pathinfo($target[$field], PATHINFO_EXTENSION);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $tipo . '-' . (int) $id . ($extension ? '.' . $extension : '') . '"');
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
