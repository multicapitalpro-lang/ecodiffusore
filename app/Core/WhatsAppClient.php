<?php

namespace App\Core;

class WhatsAppClient
{
    private string $baseUrl;
    private string $phoneNumberId;
    private string $accessToken;

    public function __construct()
    {
        $config = Config::get('whatsapp', []);
        $apiVersion = $config['api_version'] ?? 'v25.0';
        $this->baseUrl = "https://graph.facebook.com/{$apiVersion}";
        $this->phoneNumberId = $config['phone_number_id'] ?? '';
        $this->accessToken = $config['access_token'] ?? '';
    }

    /** Mensagem via modelo aprovado -- unico jeito de a empresa iniciar uma conversa (fora da
     *  janela de 24h de atendimento). $components segue o formato de "components" da Cloud API,
     *  ex: [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'valor']]]]. */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'pt_BR', array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => array_filter([
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components ?: null,
            ], fn ($v) => $v !== null),
        ];

        $result = $this->request('POST', "/{$this->phoneNumberId}/messages", $payload);

        if (empty($result['messages'][0]['id'])) {
            throw new \RuntimeException('Falha ao enviar mensagem de WhatsApp: ' . json_encode($result));
        }

        return $result;
    }

    /** Texto livre -- so entrega se o destinatario tiver mandado mensagem pra esse numero nas
     *  ultimas 24h (janela de atendimento). Nao serve pra notificacao iniciada pela empresa. */
    public function sendTextMessage(string $to, string $text): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        $result = $this->request('POST', "/{$this->phoneNumberId}/messages", $payload);

        if (empty($result['messages'][0]['id'])) {
            throw new \RuntimeException('Falha ao enviar mensagem de WhatsApp: ' . json_encode($result));
        }

        return $result;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }
        return $digits;
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        if (in_array($method, ['POST', 'PUT'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Erro de conexão com a Meta (WhatsApp): ' . $error);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
