<?php

namespace App\Core;

/**
 * Checkout Pro do Mercado Pago (Fase 32) -- pagamento avulso por periodo da assinatura do
 * Licenciado (mensal/semestral/anual), nunca recorrencia automatica de verdade: o cliente paga no
 * site do Mercado Pago (nenhum dado de cartao passa pelo nosso servidor, mesmo espirito do
 * AsaasClient::createCharge ja usado aqui), o webhook confirma e App\Models\LicenciadoSubscription
 * estende expires_at. SEM credencial configurada em 'mercadopago' no config.php, createCheckout()
 * sempre devolve null -- mesmo espirito de stub do App\Core\CorreiosClient/DataflowClient: estrutura
 * pronta, sem chamada de verdade enquanto nao houver conta Mercado Pago.
 */
class MercadoPagoClient
{
    private string $accessToken;
    private string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $config = Config::get('mercadopago', []);
        $this->accessToken = $config['access_token'] ?? '';
    }

    /** @return string|null init_point (URL de checkout) pronto pra redirecionar o Licenciado. */
    public function createCheckout(string $externalReference, string $title, float $amount, string $notificationUrl, string $backUrl): ?string
    {
        if ($this->accessToken === '') {
            return null;
        }

        try {
            $result = $this->request('POST', '/checkout/preferences', [
                'items' => [[
                    'title' => $title,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'currency_id' => 'BRL',
                ]],
                'external_reference' => $externalReference,
                'notification_url' => $notificationUrl,
                'back_urls' => ['success' => $backUrl, 'pending' => $backUrl, 'failure' => $backUrl],
                'auto_return' => 'approved',
            ]);

            return $result['init_point'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{status:string,external_reference:string}|null */
    public function getPayment(string $paymentId): ?array
    {
        if ($this->accessToken === '') {
            return null;
        }

        try {
            $result = $this->request('GET', "/v1/payments/{$paymentId}", []);
            if (empty($result['id'])) {
                return null;
            }

            return [
                'status' => $result['status'] ?? '',
                'external_reference' => $result['external_reference'] ?? '',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function request(string $method, string $path, array $params): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Erro de conexão com o Mercado Pago: ' . $error);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
