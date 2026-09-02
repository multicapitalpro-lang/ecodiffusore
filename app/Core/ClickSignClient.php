<?php

namespace App\Core;

/**
 * Cliente da Envelope API 3.0 do ClickSign (JSON:API), pro fluxo de assinatura + KYC do contrato
 * de Licenciado. Sem SDK oficial mantido pra PHP -- chama a API REST direto via cURL, mesmo
 * padrao do App\Core\AsaasClient.
 *
 * NOTA: os payloads exatos de "requirements" (acao de assinar vs. verificacao de identidade) e o
 * campo que carrega a signing_url na resposta do envelope foram montados a partir da documentacao
 * publica do ClickSign, mas nao puderam ser confirmados contra uma chamada real ainda (a conta
 * usada pro token precisa configurar o "e-mail do usuario da API" nas configuracoes do ClickSign
 * antes de qualquer chamada funcionar -- ver erro 403 "Verificacao de usuario"). Revisar contra
 * uma resposta real assim que isso for resolvido.
 */
class ClickSignClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $config = Config::get('clicksign', []);
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://app.clicksign.com', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function createEnvelope(string $name): array
    {
        $result = $this->request('POST', '/api/v3/envelopes', [
            'data' => [
                'type' => 'envelopes',
                'attributes' => [
                    'name' => $name,
                    'locale' => 'pt-BR',
                    'auto_close' => true,
                    'remind_interval' => 3,
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao criar envelope no ClickSign: ' . json_encode($result));
        }

        return $result['data'];
    }

    public function uploadDocument(string $envelopeId, string $filename, string $base64Content): array
    {
        $result = $this->request('POST', "/api/v3/envelopes/{$envelopeId}/documents", [
            'data' => [
                'type' => 'documents',
                'attributes' => [
                    'filename' => $filename,
                    'content_base64' => 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,' . $base64Content,
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao subir documento no ClickSign: ' . json_encode($result));
        }

        return $result['data'];
    }

    public function addSigner(string $envelopeId, array $signer): array
    {
        $result = $this->request('POST', "/api/v3/envelopes/{$envelopeId}/signers", [
            'data' => [
                'type' => 'signers',
                'attributes' => [
                    'name' => $signer['name'],
                    'email' => $signer['email'],
                    'documentation' => $signer['documentation'],
                    'phone_number' => $signer['phone_number'] ?? null,
                    'has_documentation' => true,
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao adicionar signatário no ClickSign: ' . json_encode($result));
        }

        return $result['data'];
    }

    public function addSignRequirement(string $envelopeId, string $documentId, string $signerId): array
    {
        return $this->createRequirement($envelopeId, $documentId, $signerId, 'sign', 'email');
    }

    /** KYC "automatico": selfie + foto do documento oficial, anexados como requirements do mesmo documento. */
    public function addKycRequirements(string $envelopeId, string $documentId, string $signerId): array
    {
        return [
            $this->createRequirement($envelopeId, $documentId, $signerId, 'provide_evidence', 'selfie'),
            $this->createRequirement($envelopeId, $documentId, $signerId, 'provide_evidence', 'official_document'),
        ];
    }

    private function createRequirement(string $envelopeId, string $documentId, string $signerId, string $action, string $auth): array
    {
        $result = $this->request('POST', "/api/v3/envelopes/{$envelopeId}/requirements", [
            'data' => [
                'type' => 'requirements',
                'attributes' => [
                    'action' => $action,
                    'auth' => $auth,
                ],
                'relationships' => [
                    'document' => ['data' => ['type' => 'documents', 'id' => $documentId]],
                    'signer' => ['data' => ['type' => 'signers', 'id' => $signerId]],
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException("Falha ao criar requirement ({$action}/{$auth}) no ClickSign: " . json_encode($result));
        }

        return $result['data'];
    }

    public function activateEnvelope(string $envelopeId): array
    {
        $result = $this->request('PATCH', "/api/v3/envelopes/{$envelopeId}", [
            'data' => [
                'id' => $envelopeId,
                'type' => 'envelopes',
                'attributes' => ['status' => 'running'],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao ativar envelope no ClickSign: ' . json_encode($result));
        }

        return $result['data'];
    }

    public function getEnvelope(string $envelopeId): array
    {
        return $this->request('GET', "/api/v3/envelopes/{$envelopeId}");
    }

    public function downloadSignedDocument(string $envelopeId, string $documentId): string
    {
        $result = $this->request('GET', "/api/v3/envelopes/{$envelopeId}/documents/{$documentId}/download");
        $url = $result['data']['attributes']['download_url'] ?? null;

        if (!$url) {
            throw new \RuntimeException('ClickSign não retornou a URL de download do documento assinado: ' . json_encode($result));
        }

        $content = file_get_contents($url);
        if ($content === false) {
            throw new \RuntimeException('Falha ao baixar o documento assinado do ClickSign.');
        }

        return $content;
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/vnd.api+json',
                'Accept: application/vnd.api+json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $params) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Erro de conexão com o ClickSign: ' . $error);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
