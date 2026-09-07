<?php

namespace App\Core;

/** Cliente pro Evolution API (self-hosted, protocolo nao-oficial do WhatsApp) -- usado como
 *  fallback enquanto a WhatsApp Cloud API oficial (App\Core\WhatsAppClient) esta em analise pela
 *  Meta. Mesmo padrao do AsaasClient. */
class EvolutionApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $instance;

    public function __construct()
    {
        $config = Config::get('evolution', []);
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->instance = $config['instance'] ?? '';
    }

    /** Estado da conexao: 'open' (conectado), 'connecting' ou 'close' (desconectado). */
    public function connectionState(): array
    {
        return $this->request('GET', "/instance/connectionState/{$this->instance}");
    }

    /** Gera (ou renova) o QR Code pra parear o aparelho -- expira em cerca de 60s. */
    public function qrCode(): array
    {
        return $this->request('GET', "/instance/connect/{$this->instance}");
    }

    public function logout(): array
    {
        return $this->request('DELETE', "/instance/logout/{$this->instance}");
    }

    public function sendText(string $to, string $text): array
    {
        $digits = preg_replace('/\D/', '', $to);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        return $this->request('POST', "/message/sendText/{$this->instance}", [
            'number' => $digits,
            'text' => $text,
            'linkPreview' => false,
        ]);
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $this->apiKey,
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
            throw new \RuntimeException('Erro de conexão com o Evolution API: ' . $error);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
