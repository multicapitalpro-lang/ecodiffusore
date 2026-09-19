<?php

namespace App\Core;

class AsaasClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $config = Config::get('asaas', []);
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.asaas.com/v3', '/');
        $this->apiKey = $config['api_key'] ?? '';
    }

    public function createOrFindCustomer(array $client): string
    {
        $document = preg_replace('/\D/', '', (string) ($client['document'] ?? ''));
        if ($document === '') {
            throw new \RuntimeException('Cliente sem CPF/CNPJ cadastrado — necessário para gerar cobrança.');
        }

        $existing = $this->request('GET', '/customers', ['cpfCnpj' => $document]);
        if (!empty($existing['data'][0]['id'])) {
            return $existing['data'][0]['id'];
        }

        $created = $this->request('POST', '/customers', [
            'name' => $client['name'],
            'cpfCnpj' => $document,
            'email' => $client['email'] ?: null,
            'mobilePhone' => preg_replace('/\D/', '', (string) ($client['whatsapp'] ?? '')) ?: null,
            'externalReference' => (string) $client['id'],
        ]);

        if (empty($created['id'])) {
            throw new \RuntimeException('Falha ao criar cliente no Asaas: ' . json_encode($created));
        }

        return $created['id'];
    }

    public function createCharge(array $data): array
    {
        $payload = [
            'customer' => $data['customer'],
            'billingType' => $data['billing_type'],
            'value' => $data['value'],
            'dueDate' => $data['due_date'],
            'description' => $data['description'] ?? null,
            'externalReference' => $data['external_reference'] ?? null,
        ];

        // Cartao parcelado: manda o total (Asaas divide em N parcelas iguais) em vez de 'value'.
        // Nao usar o endpoint dedicado de parcelamento (/v3/installments) -- esse exige os dados
        // crus do cartao no corpo da requisicao, o que colocaria a aplicacao no escopo PCI-DSS.
        // Esse endpoint (/payments) devolve invoiceUrl hospedado pela propria Asaas, onde o cliente
        // digita o cartao -- nenhum dado de cartao passa pelo nosso servidor.
        if (!empty($data['installment_count']) && (int) $data['installment_count'] > 1) {
            $payload['installmentCount'] = (int) $data['installment_count'];
            $payload['totalValue'] = $data['value'];
            unset($payload['value']);
        }

        $result = $this->request('POST', '/payments', $payload);

        if (empty($result['id'])) {
            throw new \RuntimeException('Falha ao criar cobrança no Asaas: ' . json_encode($result));
        }

        return $result;
    }

    public function getPixQrCode(string $chargeId): array
    {
        return $this->request('GET', "/payments/{$chargeId}/pixQrCode");
    }

    /** Linha digitavel + codigo de barras do boleto -- junto com bankSlipUrl (ja vem na propria
     *  resposta de createCharge()), deixa mostrar o boleto inteiro direto na nossa pagina, sem
     *  precisar mandar o comprador pra pagina hospedada da Asaas (mesmo espirito do Pix). */
    public function getBoletoIdentificationField(string $chargeId): array
    {
        return $this->request('GET', "/payments/{$chargeId}/identificationField");
    }

    public function find(string $chargeId): array
    {
        return $this->request('GET', "/payments/{$chargeId}");
    }

    public function cancel(string $chargeId): array
    {
        return $this->request('DELETE', "/payments/{$chargeId}");
    }

    /** Lista antecipacoes (paginado, 100 por pagina -- limite maximo da Asaas). */
    public function listAnticipations(int $offset = 0, int $limit = 100): array
    {
        return $this->request('GET', '/anticipations', ['offset' => $offset, 'limit' => $limit]);
    }

    /** Busca servicos municipais cadastrados na Asaas pra essa conta (usado pra montar o seletor
     *  de servico municipal na tela de Config. de NF-e, em vez do admin ter que digitar o codigo
     *  cego). Read-only. */
    public function searchFiscalServices(string $query): array
    {
        return $this->request('GET', '/fiscalInfo/services', ['description' => $query, 'limit' => 20]);
    }

    /** Cria uma nota fiscal de servico (NF-e) vinculada a uma cobranca ja paga. So deve ser
     *  chamado quando NfeSettings::current()['enabled'] estiver ligado -- emitir nota fiscal e uma
     *  acao com efeito fiscal real, nao reversivel de forma simples (cancelamento depende de prazo
     *  e aprovacao municipal). Ver App\Models\NfeSettings. */
    public function createInvoice(array $data): array
    {
        $payload = [
            'payment' => $data['payment_id'],
            'serviceDescription' => $data['service_description'],
            'observations' => $data['observations'] ?? null,
            'value' => $data['value'],
            'effectiveDate' => $data['effective_date'],
            'municipalServiceId' => $data['municipal_service_id'] ?? null,
            'municipalServiceCode' => $data['municipal_service_code'] ?? null,
            'municipalServiceName' => $data['municipal_service_name'],
            'taxes' => [
                'retainIss' => $data['taxes']['retain_iss'] ?? false,
                'iss' => $data['taxes']['iss'] ?? 0,
                'cofins' => $data['taxes']['cofins'] ?? 0,
                'csll' => $data['taxes']['csll'] ?? 0,
                'inss' => $data['taxes']['inss'] ?? 0,
                'ir' => $data['taxes']['ir'] ?? 0,
                'pis' => $data['taxes']['pis'] ?? 0,
            ],
        ];

        $result = $this->request('POST', '/invoices', $payload);

        if (empty($result['id'])) {
            throw new \RuntimeException('Falha ao criar nota fiscal no Asaas: ' . json_encode($result));
        }

        return $result;
    }

    /** Consulta o status atual de uma nota fiscal ja criada -- emissao municipal e' assincrona
     *  (aprovacao da prefeitura), entao o pdfUrl normalmente so fica disponivel depois da criacao.
     *  Usado pelo lazy-check (App\Core\NfeStatusChecker) pra atualizar o pedido quando ficar pronta. */
    public function getInvoice(string $invoiceId): array
    {
        return $this->request('GET', "/invoices/{$invoiceId}");
    }

    public function registerWebhook(string $url, string $authToken): array
    {
        return $this->request('POST', '/webhooks', [
            'name' => 'Painel Ecodiffusore',
            'url' => $url,
            'email' => 'contato@ecodiffusorebrasil.com.br',
            'enabled' => true,
            'interrupted' => false,
            'apiVersion' => 3,
            'authToken' => $authToken,
            'sendType' => 'SEQUENTIALLY',
            'events' => ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'],
        ]);
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if ($method === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
            $params = [];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'access_token: ' . $this->apiKey,
                'Content-Type: application/json',
                'User-Agent: EcodiffusoreBrasil-Painel',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        if (in_array($method, ['POST', 'PUT'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_filter($params, fn ($v) => $v !== null)));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Erro de conexão com o Asaas: ' . $error);
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
