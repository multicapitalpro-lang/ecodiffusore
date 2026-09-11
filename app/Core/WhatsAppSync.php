<?php

namespace App\Core;

use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;

/**
 * Sincroniza chats/mensagens da Evolution API pro banco local (Fase 33) -- usado tanto na
 * sincronizacao inicial (logo apos conectar, ou pelo botao manual "Sincronizar") quanto pelo
 * webhook (mensagem nova chegando em tempo real). Idempotente: reimportar a mesma mensagem
 * (mesmo wa_message_id) nao duplica, ver WhatsAppMessage::create().
 *
 * Fase 34: passa a extrair metadados de midia (mimetype/filename/tamanho + o "key" completo,
 * necessario pra reconverter em base64 depois -- ver EvolutionApiClient::fetchMediaBase64) e a
 * tratar mensagens de revogacao (apagar-pra-todos) marcando a mensagem original como apagada em
 * vez de inserir um "protocolMessage" cru na tela.
 */
class WhatsAppSync
{
    private const MEDIA_TYPES = ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage', 'stickerMessage'];

    /** Pega a lista de chats (com a ultima mensagem de cada) -- historico completo que a propria
     *  Evolution ja mantem desde que a instancia foi conectada, confirmado testando ao vivo. */
    public static function pullChats(int $instanceId, string $instanceName): int
    {
        $client = new EvolutionApiClient($instanceName);
        $chats = $client->fetchChats();
        $count = 0;

        foreach ($chats as $c) {
            $jid = $c['remoteJid'] ?? null;
            if (!$jid) {
                continue;
            }

            $isGroup = str_ends_with($jid, '@g.us');
            $name = $c['pushName'] ?? null;
            $chatId = WhatsAppChat::upsert($instanceId, $jid, $name, $isGroup);

            if (!empty($c['lastMessage'])) {
                self::importMessage($chatId, $c['lastMessage'], false);
            }
            $count++;
        }

        return $count;
    }

    /** Pega as mensagens de UM chat especifico -- chamado quando o usuario abre a conversa pela
     *  primeira vez (a lista de chats so trouxe a ultima mensagem de cada, nao a conversa inteira). */
    public static function pullMessagesForChat(int $chatId, string $instanceName, string $remoteJid): int
    {
        $client = new EvolutionApiClient($instanceName);
        $result = $client->fetchMessages($remoteJid, 1, 100);
        $count = 0;

        foreach (array_reverse($result['records']) as $m) {
            self::importMessage($chatId, $m, false);
            $count++;
        }

        return $count;
    }

    /** @param array $m Mensagem no formato Evolution/Baileys (key/message/messageType/pushName/
     *  messageTimestamp). $touchUnread incrementa o contador de nao-lidas do chat -- so faz
     *  sentido quando a mensagem chega ao vivo pelo webhook, nao numa sincronizacao retroativa. */
    public static function importMessage(int $chatId, array $m, bool $touchUnread): void
    {
        $type = $m['messageType'] ?? 'text';

        // "Apagar para todos" chega como uma mensagem NOVA do tipo protocolMessage, referenciando o
        // key da mensagem original -- nunca deve virar uma linha visivel na tela, so atualiza a
        // mensagem que ela referencia.
        if ($type === 'protocolMessage') {
            self::handleProtocolMessage($chatId, $m);
            return;
        }

        $key = $m['key'] ?? [];
        $waId = $key['id'] ?? null;
        $direction = !empty($key['fromMe']) ? 'out' : 'in';
        $body = self::extractBody($m);
        $sentAt = !empty($m['messageTimestamp'])
            ? date('Y-m-d H:i:s', (int) $m['messageTimestamp'])
            : date('Y-m-d H:i:s');
        $sender = $m['pushName'] ?? null;

        $media = self::extractMediaInfo($m, $type);

        WhatsAppMessage::create(
            $chatId,
            $waId,
            $direction,
            $sender,
            $body,
            $type,
            $sentAt,
            $media['mimetype'] ?? null,
            $media['filename'] ?? null,
            $media['size_bytes'] ?? null,
            $media ? json_encode($key) : null
        );
        WhatsAppChat::touchLastMessage($chatId, $body ?: '[mídia]', $sentAt, $touchUnread && $direction === 'in');
    }

    private static function handleProtocolMessage(int $chatId, array $m): void
    {
        $revokedId = $m['message']['protocolMessage']['key']['id'] ?? null;
        if ($revokedId) {
            WhatsAppMessage::markDeleted($chatId, $revokedId);
        }
    }

    /** So texto de verdade vira corpo pesquisavel/exibido em preview -- midia (imagem/audio/video/
     *  documento/figurinha) fica com um resumo textual (icone + legenda, ou um rotulo generico sem
     *  legenda) usado como preview na lista de conversas; a THREAD (_thread.php) renderiza a midia de
     *  verdade e usa esse mesmo corpo so como legenda (com o icone removido na exibicao). */
    private static function extractBody(array $m): ?string
    {
        $msg = $m['message'] ?? [];

        if (isset($msg['conversation'])) {
            return $msg['conversation'];
        }
        if (isset($msg['extendedTextMessage']['text'])) {
            return $msg['extendedTextMessage']['text'];
        }
        if (isset($msg['imageMessage'])) {
            return '📷 ' . ($msg['imageMessage']['caption'] ?? '[imagem]');
        }
        if (isset($msg['videoMessage'])) {
            return '🎥 ' . ($msg['videoMessage']['caption'] ?? '[vídeo]');
        }
        if (isset($msg['audioMessage'])) {
            return '🎵 [áudio]';
        }
        if (isset($msg['documentMessage'])) {
            return '📄 ' . ($msg['documentMessage']['fileName'] ?? '[documento]');
        }
        if (isset($msg['stickerMessage'])) {
            return '🩹 [figurinha]';
        }

        return null;
    }

    /** @return array{mimetype:?string, filename:?string, size_bytes:?int}|null null se o tipo nao
     *  for midia (texto normal) -- $type ja vem confirmado ao vivo como identico ao nome da chave
     *  dentro de "message" (ex: messageType="imageMessage" <=> $msg['imageMessage']). */
    private static function extractMediaInfo(array $m, string $type): ?array
    {
        if (!in_array($type, self::MEDIA_TYPES, true)) {
            return null;
        }

        $media = $m['message'][$type] ?? null;
        if (!is_array($media)) {
            return null;
        }

        $fileLength = $media['fileLength'] ?? null;
        $sizeBytes = is_array($fileLength) ? (int) ($fileLength['low'] ?? 0) : (is_numeric($fileLength) ? (int) $fileLength : null);

        return [
            'mimetype' => $media['mimetype'] ?? null,
            'filename' => $media['fileName'] ?? null,
            'size_bytes' => $sizeBytes,
        ];
    }
}
