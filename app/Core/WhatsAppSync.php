<?php

namespace App\Core;

use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;

/**
 * Sincroniza chats/mensagens da Evolution API pro banco local (Fase 33) -- usado tanto na
 * sincronizacao inicial (logo apos conectar, ou pelo botao manual "Sincronizar") quanto pelo
 * webhook (mensagem nova chegando em tempo real). Idempotente: reimportar a mesma mensagem
 * (mesmo wa_message_id) nao duplica, ver WhatsAppMessage::create().
 */
class WhatsAppSync
{
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
        $key = $m['key'] ?? [];
        $waId = $key['id'] ?? null;
        $direction = !empty($key['fromMe']) ? 'out' : 'in';
        $type = $m['messageType'] ?? 'text';
        $body = self::extractBody($m);
        $sentAt = !empty($m['messageTimestamp'])
            ? date('Y-m-d H:i:s', (int) $m['messageTimestamp'])
            : date('Y-m-d H:i:s');
        $sender = $m['pushName'] ?? null;

        WhatsAppMessage::create($chatId, $waId, $direction, $sender, $body, $type, $sentAt);
        WhatsAppChat::touchLastMessage($chatId, $body ?: '[mídia]', $sentAt, $touchUnread && $direction === 'in');
    }

    /** So texto de verdade vira corpo pesquisavel/exibido em preview -- midia (imagem/audio/video/
     *  documento/figurinha) fica so com um resumo textual por enquanto (baixar/exibir o arquivo em
     *  si fica pra uma fase seguinte, fora do escopo pedido agora). */
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
}
