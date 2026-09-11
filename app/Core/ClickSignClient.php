<?php

namespace App\Core;

/**
 * Cliente da Envelope API 3.0 do ClickSign (JSON:API), pro fluxo de assinatura + KYC do contrato
 * de Licenciado. Sem SDK oficial mantido pra PHP -- chama a API REST direto via cURL, mesmo
 * padrao do App\Core\AsaasClient.
 *
 * O contrato do Licenciado usa um Modelo cadastrado direto no ClickSign (Automação > Modelos,
 * "Contrato Assinatura Diferencial") em vez de gerar o .docx no servidor -- createDocumentFromTemplate()
 * manda so os dados (App\Core\ContractTemplateFiller::buildTemplateData()), o ClickSign faz a
 * substituicao. uploadDocument() (subir um .docx ja pronto em base64) continua disponivel caso
 * algum outro documento precise ir sem passar por um Modelo.
 *
 * Payloads de "requirements" confirmados contra chamadas reais em 2026-09-02 (depois que a conta
 * resolveu a pendencia do "e-mail do usuario da API"): "action" so aceita agree/provide_evidence/
 * rubricate (nao existe "sign"). O requirement de assinatura em si e' action=agree + role=sign
 * (sem "auth"); os requirements de KYC sao action=provide_evidence + auth=selfie/official_document.
 *
 * NOTA ainda em aberto: o campo exato que carrega a signing_url na resposta do envelope ativado
 * nao foi confirmado (a API tambem tem um endpoint `/signers/{id}` que pode ser onde esse link
 * aparece) -- revisar assim que possivel testar uma ativacao completa sem esbarrar no rate limit
 * (429) da API.
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

    /**
     * Cria o documento a partir de um Modelo ja cadastrado no ClickSign (Automacao > Modelos),
     * em vez de subir um .docx pronto. $templateData e um mapa "Nome do Campo" => valor, usando
     * exatamente os nomes dos campos detectados no modelo (confirmado via
     * GET /templates/{key}/template_fields -- sao os mesmos textos dentro de {{...}} no docx).
     */
    public function createDocumentFromTemplate(string $envelopeId, string $templateKey, string $filename, array $templateData): array
    {
        $result = $this->request('POST', "/api/v3/envelopes/{$envelopeId}/documents", [
            'data' => [
                'type' => 'documents',
                'attributes' => [
                    'filename' => $filename,
                    'template' => [
                        'key' => $templateKey,
                        'data' => $templateData,
                    ],
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao criar documento por modelo no ClickSign: ' . json_encode($result));
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
        // Confirmado direto contra a API real (2026-09-02): "action" precisa ser um de
        // agree/provide_evidence/rubricate -- "sign" nao existe. O requirement de assinatura em si
        // e' action=agree com role=sign (nao usa "auth").
        return $this->createRequirement($envelopeId, $documentId, $signerId, ['action' => 'agree', 'role' => 'sign']);
    }

    /** KYC "automatico": selfie + foto do documento oficial, anexados como requirements do mesmo documento.
     *  NAO USADO HOJE -- confirmado contra a API real em 2026-09-05 que a conta devolve 403
     *  "A conta nao possui acesso a essa funcionalidade" pra selfie/official_document (e tambem
     *  pra sms/whatsapp). Fica no codigo pronto pra reativar se a conta contratar esse recurso. */
    public function addKycRequirements(string $envelopeId, string $documentId, string $signerId): array
    {
        return [
            $this->createRequirement($envelopeId, $documentId, $signerId, ['action' => 'provide_evidence', 'auth' => 'selfie']),
            $this->createRequirement($envelopeId, $documentId, $signerId, ['action' => 'provide_evidence', 'auth' => 'official_document']),
        ];
    }

    /**
     * Requisito de autenticacao por e-mail -- confirmado contra a API real (2026-09-05) que a
     * ativacao do envelope EXIGE pelo menos um requirement de autenticacao no signatario alem do
     * "agree" de assinatura (sem nenhum, a ativacao falha com 422 "ha signatario(s) sem os
     * requisitos necessarios"). email e' a unica opcao confirmada como gratuita nessa conta --
     * sms/whatsapp/selfie/official_document deram 403, e icp_brasil nao pode ser combinado com
     * outro auth no mesmo signatario.
     */
    public function addEmailAuthRequirement(string $envelopeId, string $documentId, string $signerId): array
    {
        return $this->createRequirement($envelopeId, $documentId, $signerId, ['action' => 'provide_evidence', 'auth' => 'email']);
    }

    /**
     * Requirement de autenticacao auth=auto_signature (action=provide_evidence, igual ao padrao de
     * addEmailAuthRequirement -- NAO combina com role=sign, que e' exclusivo do requirement de
     * assinatura em si) -- pro signatario que ja assinou o Termo de Assinatura Automatica
     * (createAutoSignatureTerm()), fazendo a API aplicar a assinatura dele sozinha, sem precisar
     * visitar link nem autenticar por e-mail. Usar JUNTO com addSignRequirement() (o requirement de
     * assinatura continua sendo action=agree+role=sign, sem auth) -- este aqui SUBSTITUI
     * addEmailAuthRequirement() no mesmo signatario, nao o addSignRequirement().
     */
    public function addAutoSignatureRequirement(string $envelopeId, string $documentId, string $signerId): array
    {
        return $this->createRequirement($envelopeId, $documentId, $signerId, ['action' => 'provide_evidence', 'auth' => 'auto_signature']);
    }

    private function createRequirement(string $envelopeId, string $documentId, string $signerId, array $attributes): array
    {
        $result = $this->request('POST', "/api/v3/envelopes/{$envelopeId}/requirements", [
            'data' => [
                'type' => 'requirements',
                'attributes' => $attributes,
                'relationships' => [
                    'document' => ['data' => ['type' => 'documents', 'id' => $documentId]],
                    'signer' => ['data' => ['type' => 'signers', 'id' => $signerId]],
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao criar requirement (' . json_encode($attributes) . ') no ClickSign: ' . json_encode($result));
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

    /**
     * Cria (e dispara pro signatario revisar/confirmar) o Termo de Assinatura Automatica --
     * pre-requisito de uma unica vez pra um signatario RECORRENTE do nosso lado (ex: o
     * representante legal da Diferencial) poder ter a assinatura aplicada automaticamente em
     * todo envelope futuro, sem interacao manual. Endpoint fora do namespace /envelopes (nao e'
     * por documento/envelope, e' por par signatario+operador). So' precisa ser chamado 1x por
     * signatario -- chamar de novo com os mesmos dados devolve erro (codigo 100, "ja existe um
     * termo... pra este signatario e operador"). $signer precisa de name/email/documentation
     * (CPF)/birthday (AAAA-MM-DD).
     */
    public function createAutoSignatureTerm(array $signer, string $apiEmail, string $adminEmail): array
    {
        $result = $this->request('POST', '/api/v3/auto_signature/terms', [
            'data' => [
                'type' => 'auto_signature_terms',
                'attributes' => [
                    'signer' => [
                        'name' => $signer['name'],
                        'email' => $signer['email'],
                        'documentation' => $signer['documentation'],
                        'birthday' => $signer['birthday'],
                    ],
                    'api_email' => $apiEmail,
                    'admin_email' => $adminEmail,
                ],
            ],
        ]);

        if (empty($result['data']['id'])) {
            throw new \RuntimeException('Falha ao criar termo de assinatura automática no ClickSign: ' . json_encode($result));
        }

        return $result['data'];
    }

    /**
     * Confirmado contra a API real (2026-09-05): nao existe um endpoint "/download" separado
     * (da 404) -- a URL assinada (S3, expira em ~5min) vem dentro do proprio recurso do
     * documento, em data.links.files.signed (so aparece depois que o envelope fecha).
     */
    public function downloadSignedDocument(string $envelopeId, string $documentId): string
    {
        $result = $this->request('GET', "/api/v3/envelopes/{$envelopeId}/documents/{$documentId}");
        $url = $result['data']['links']['files']['signed'] ?? null;

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
