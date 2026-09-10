<?php

namespace App\Core;

/**
 * Cliente pro Evolution API (self-hosted, protocolo nao-oficial do WhatsApp) -- usado como
 * fallback enquanto a WhatsApp Cloud API oficial (App\Core\WhatsAppClient) esta em analise pela
 * Meta. Mesmo padrao do AsaasClient.
 *
 * Fase 33: passa a aceitar um $instanceName no construtor -- antes so operava na instancia unica
 * do admin (config('evolution.instance')). O mesmo apikey GLOBAL (fixo no config.php, gerado no
 * .env do servidor Evolution) gerencia QUALQUER instancia por nome na URL -- confirmado testando
 * ao vivo contra o servidor (v2.3.7): POST /instance/create cria, GET /instance/connectionState/
 * {instance} e POST /chat/findChats|findMessages/{instance} leem, sem precisar de token por
 * instancia. Isso viabiliza o Painel de WhatsApp do Licenciado/Gestor/Vendedor (cada um com a
 * propria instancia) sem reestruturar a autenticacao.
 */
class EvolutionApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $instance;

    public function __construct(?string $instanceName = null)
    {
        $config = Config::get('evolution', []);
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->instance = $instanceName ?? ($config['instance'] ?? '');
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

    /** Cria a instancia no servidor Evolution -- resposta ja vem com o QR Code (qrcode.base64)
     *  pronto, sem precisar de uma segunda chamada a qrCode(). Confirmado ao vivo (v2.3.7). */
    public function createInstance(): array
    {
        return $this->request('POST', '/instance/create', [
            'instanceName' => $this->instance,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ]);
    }

    /** Remove a instancia de vez (cadastro de usuario excluido, ou reconexao do zero). Precisa de
     *  logout antes se ainda estiver conectada -- ver disconnectAndDelete(). */
    public function deleteInstance(): array
    {
        return $this->request('DELETE', "/instance/delete/{$this->instance}");
    }

    public function disconnectAndDelete(): void
    {
        try {
            $this->logout();
        } catch (\Throwable $e) {
            // Instancia pode ja estar desconectada -- delete() abaixo e' o que importa de verdade.
        }
        try {
            $this->deleteInstance();
        } catch (\Throwable $e) {
            // Best-effort.
        }
    }

    /** Configura o webhook da instancia -- eventos de mensagem/conexao passam a chegar em
     *  App\Controllers\WhatsAppWebhookController::receive() pra essa instancia especifica
     *  (identificada pelo campo "instance" que a Evolution manda em todo payload). */
    public function setWebhook(string $url): array
    {
        return $this->request('POST', "/webhook/set/{$this->instance}", [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'webhookByEvents' => false,
                'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'],
            ],
        ]);
    }

    /** @return array Lista de chats (grupos e individuais) com lastMessage/unreadCount --
     *  historico ja mantido pela propria Evolution desde que a instancia foi conectada. */
    public function fetchChats(): array
    {
        $result = $this->request('POST', "/chat/findChats/{$this->instance}", (object) []);
        return is_array($result) ? $result : [];
    }

    /** @return array{records: array, total: int} Mensagens de UM chat, mais recentes primeiro. */
    public function fetchMessages(string $remoteJid, int $page = 1, int $limit = 50): array
    {
        $result = $this->request('POST', "/chat/findMessages/{$this->instance}", [
            'where' => ['key' => ['remoteJid' => $remoteJid]],
            'page' => $page,
            'limit' => $limit,
        ]);

        $messages = $result['messages'] ?? [];
        return [
            'records' => $messages['records'] ?? [],
            'total' => (int) ($messages['total'] ?? 0),
        ];
    }

    /** Lanca RuntimeException se o envio falhar (numero invalido/nao existe no WhatsApp, instancia
     *  desconectada, etc) -- antes essa falha passava batido (a API devolve HTTP 400 mas o corpo
     *  json ainda decodifica normal, sem excecao). Quem chama (Notifier::sendWhatsApp) ja captura
     *  e loga, entao isso so melhora a visibilidade do problema, nao muda o comportamento de fora. */
    public function sendText(string $to, string $text): array
    {
        $digits = preg_replace('/\D/', '', $to);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        $result = $this->request('POST', "/message/sendText/{$this->instance}", [
            'number' => $digits,
            'text' => $text,
            'linkPreview' => false,
        ]);

        if (empty($result['key']['id'])) {
            throw new \RuntimeException('Falha ao enviar WhatsApp pra ' . $digits . ': ' . json_encode($result));
        }

        return $result;
    }

    /** @param array|object $payload */
    private function request(string $method, string $path, $payload = []): array
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
