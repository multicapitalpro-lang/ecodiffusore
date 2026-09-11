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

    /** URL da foto de perfil publica do contato, ou null se nao tiver/privacidade impedir --
     *  confirmado ao vivo (v2.3.7): POST /chat/fetchProfilePictureUrl/{instance} {number}, devolve
     *  {wuid, profilePictureUrl}. Aceita numero cru ou JID completo, mesma normalizacao de sendText. */
    public function fetchProfilePictureUrl(string $to): ?string
    {
        $result = $this->request('POST', "/chat/fetchProfilePictureUrl/{$this->instance}", [
            'number' => $this->normalizeNumber($to),
        ]);

        return $result['profilePictureUrl'] ?? null;
    }

    /** "Apagar para todos" de uma mensagem que EU mandei -- confirmado ao vivo (v2.3.7): DELETE
     *  /chat/deleteMessageForEveryone/{instance} exige id/remoteJid/fromMe no corpo. So funciona
     *  pra mensagem que a propria Evolution reconhece como enviada por essa instancia (mensagem
     *  antiga demais ou que nunca passou por aqui falha -- deixa o RuntimeException subir, quem
     *  chama decide a mensagem de erro pro usuario). */
    public function deleteMessageForEveryone(string $remoteJid, string $messageId): array
    {
        return $this->request('DELETE', "/chat/deleteMessageForEveryone/{$this->instance}", [
            'id' => $messageId,
            'remoteJid' => $remoteJid,
            'fromMe' => true,
        ]);
    }

    /** Converte uma mensagem de midia (imagem/video/audio/documento/figurinha) ja recebida pra
     *  base64 -- confirmado ao vivo (v2.3.7): POST /chat/getBase64FromMediaMessage/{instance} com
     *  {message:{key:{...}}, convertToMp4:false}, devolve {base64, mimetype, fileName, size,
     *  mediaType}. $key precisa ser o objeto key COMPLETO da mensagem original (id/fromMe/remoteJid/
     *  participant p/ grupo) -- so o id sozinho nao basta, por isso WhatsAppMessage guarda o key
     *  inteiro (wa_key_json) na hora de importar, nao so o wa_message_id. */
    public function fetchMediaBase64(array $key): array
    {
        return $this->request('POST', "/chat/getBase64FromMediaMessage/{$this->instance}", [
            'message' => ['key' => $key],
            'convertToMp4' => false,
        ]);
    }

    /** Lanca RuntimeException se o envio falhar (numero invalido/nao existe no WhatsApp, instancia
     *  desconectada, etc) -- antes essa falha passava batido (a API devolve HTTP 400 mas o corpo
     *  json ainda decodifica normal, sem excecao). Quem chama (Notifier::sendWhatsApp) ja captura
     *  e loga, entao isso so melhora a visibilidade do problema, nao muda o comportamento de fora. */
    public function sendText(string $to, string $text): array
    {
        $result = $this->request('POST', "/message/sendText/{$this->instance}", [
            'number' => $this->normalizeNumber($to),
            'text' => $text,
            'linkPreview' => false,
        ]);

        if (empty($result['key']['id'])) {
            throw new \RuntimeException('Falha ao enviar WhatsApp pra ' . $to . ': ' . json_encode($result));
        }

        return $result;
    }

    /** Imagem/video/documento -- $mediatype precisa ser exatamente "image"/"video"/"document"
     *  (confirmado ao vivo: e' o que /message/sendMedia/{instance} exige). */
    public function sendMedia(string $to, string $mediatype, string $mimetype, string $base64, ?string $fileName, ?string $caption): array
    {
        $payload = [
            'number' => $this->normalizeNumber($to),
            'mediatype' => $mediatype,
            'mimetype' => $mimetype,
            'media' => $base64,
        ];
        if ($fileName) {
            $payload['fileName'] = $fileName;
        }
        if ($caption) {
            $payload['caption'] = $caption;
        }

        $result = $this->request('POST', "/message/sendMedia/{$this->instance}", $payload);
        if (empty($result['key']['id'])) {
            throw new \RuntimeException('Falha ao enviar mídia pra ' . $to . ': ' . json_encode($result));
        }

        return $result;
    }

    /** Audio/nota de voz -- endpoint separado de sendMedia (confirmado ao vivo:
     *  /message/sendWhatsAppAudio/{instance}, campo "audio" em vez de "media"). */
    public function sendAudio(string $to, string $base64): array
    {
        $result = $this->request('POST', "/message/sendWhatsAppAudio/{$this->instance}", [
            'number' => $this->normalizeNumber($to),
            'audio' => $base64,
        ]);
        if (empty($result['key']['id'])) {
            throw new \RuntimeException('Falha ao enviar áudio pra ' . $to . ': ' . json_encode($result));
        }

        return $result;
    }

    /** $to pode ser um JID completo (contato normal "...@s.whatsapp.net", contato com privacidade
     *  de numero ativada "...@lid", ou grupo "...@g.us" -- ver WhatsAppInboxController, sempre manda
     *  $chat['remote_jid']) ou um numero de telefone cru (Notifier::sendWhatsApp manda so digitos/
     *  mascara). Extrair só os digitos de um JID já pronto CORROMPE o destino -- um contato "@lid"
     *  (identificador interno opaco da funcao de privacidade de numero do WhatsApp, nao e' um
     *  telefone de verdade) virava uma sequencia de digitos que nao correspondia a ninguem, e o
     *  envio falhava sempre pra esses contatos. Confirmado ao vivo contra a Evolution API: o campo
     *  "number" aceita o JID completo direto (ela mesma resolve/normaliza), entao so formata como
     *  numero brasileiro quando NÃO for um JID (sem "@"). */
    private function normalizeNumber(string $to): string
    {
        if (str_contains($to, '@')) {
            return $to;
        }

        $digits = preg_replace('/\D/', '', $to);
        if (strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        return $digits;
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

        // DELETE precisa entrar aqui tambem -- /chat/deleteMessageForEveryone exige corpo (id/
        // remoteJid/fromMe) mesmo sendo DELETE, confirmado ao vivo (sem isso a Evolution devolve
        // 400 "instance requires property..." por nunca receber o corpo).
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
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
