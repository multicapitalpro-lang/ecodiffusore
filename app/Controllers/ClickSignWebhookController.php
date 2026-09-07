<?php

namespace App\Controllers;

use App\Core\ClickSignClient;
use App\Core\Config;
use App\Core\Notifier;
use App\Models\LicenciadoEnvelope;
use App\Models\User;

class ClickSignWebhookController
{
    private const REFUSAL_EVENTS = ['refusal'];
    private const KYC_REFUSAL_EVENTS = [
        'liveness_refused', 'facematch_refused', 'biometric_refused',
        'identity_biometrics_refused', 'documentscopy_refused', 'ocr_refused',
    ];
    private const CLOSED_EVENTS = ['auto_close', 'document_closed'];

    public function clicksign(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $secret = Config::get('clicksign', [])['webhook_secret'] ?? '';
        $header = $_SERVER['HTTP_CONTENT_HMAC'] ?? '';
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        if ($secret === '' || !hash_equals($expected, $header)) {
            http_response_code(403);
            exit;
        }

        $body = json_decode($rawBody, true) ?? [];
        $event = $body['event']['name'] ?? $body['event'] ?? '';
        $envelopeId = $body['data']['envelope']['id'] ?? $body['envelope']['id'] ?? null;

        if (!$envelopeId) {
            http_response_code(200);
            exit;
        }

        $envelope = LicenciadoEnvelope::findByClickSignEnvelopeId($envelopeId);
        if (!$envelope) {
            http_response_code(200);
            exit;
        }

        if (in_array($event, self::CLOSED_EVENTS, true)) {
            LicenciadoEnvelope::updateStatus((int) $envelope['id'], 'closed');

            try {
                $content = (new ClickSignClient())->downloadSignedDocument($envelopeId, $envelope['clicksign_document_id']);
                $storedName = bin2hex(random_bytes(16)) . '.pdf';
                $dir = BASE_PATH . '/storage/uploads/licenciados';
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                file_put_contents($dir . '/' . $storedName, $content);
                LicenciadoEnvelope::attachSignedDocument((int) $envelope['id'], $storedName);
            } catch (\Throwable $e) {
                // Nao bloqueia a aprovacao por falha no download do PDF -- pode ser baixado depois.
            }

            // Nao aprova sozinho -- fica esperando Admin/Gerente revisar os dados + documentos na
            // tela de aprovacao de cadastros antes de liberar o painel completo.
            User::setOnboardingStatus((int) $envelope['user_id'], 'aguardando_aprovacao');

            $licenciado = User::find((int) $envelope['user_id']);
            if ($licenciado) {
                Notifier::licenciadoPendenteAprovacao($licenciado);
            }
        } elseif (in_array($event, self::REFUSAL_EVENTS, true)) {
            LicenciadoEnvelope::updateStatus((int) $envelope['id'], 'refused');
            User::setOnboardingStatus((int) $envelope['user_id'], 'assinatura_recusada');
        } elseif (in_array($event, self::KYC_REFUSAL_EVENTS, true)) {
            LicenciadoEnvelope::updateStatus((int) $envelope['id'], 'kyc_refused');
            User::setOnboardingStatus((int) $envelope['user_id'], 'kyc_recusado');
        }

        http_response_code(200);
    }
}
