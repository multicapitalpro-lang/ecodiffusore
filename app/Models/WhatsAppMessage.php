<?php

namespace App\Models;

use App\Core\Database;

class WhatsAppMessage
{
    public static function forChat(int $chatId, int $limit = 200): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM whatsapp_messages WHERE chat_id = :chat_id ORDER BY sent_at ASC LIMIT :limit'
        );
        $stmt->bindValue('chat_id', $chatId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Idempotente por (chat_id, wa_message_id) -- o mesmo evento de webhook pode chegar mais de
     *  uma vez (reentrega padrao de qualquer webhook), e a sincronizacao inicial roda de novo toda
     *  vez que o usuario abre a tela. wa_message_id nulo (mensagens mandadas por nos, cujo id real
     *  so a Evolution devolve) sempre insere -- sem chave pra deduplicar contra. */
    public static function create(int $chatId, ?string $waMessageId, string $direction, ?string $senderName, ?string $body, string $type, string $sentAt): int
    {
        if ($waMessageId !== null) {
            $stmt = Database::connection()->prepare(
                'SELECT id FROM whatsapp_messages WHERE chat_id = :chat_id AND wa_message_id = :wa_id'
            );
            $stmt->execute(['chat_id' => $chatId, 'wa_id' => $waMessageId]);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                return (int) $existing;
            }
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_messages (chat_id, wa_message_id, direction, sender_name, body, message_type, sent_at)
             VALUES (:chat_id, :wa_id, :direction, :sender, :body, :type, :sent_at)'
        );
        $stmt->execute([
            'chat_id' => $chatId,
            'wa_id' => $waMessageId,
            'direction' => $direction,
            'sender' => $senderName,
            'body' => $body,
            'type' => $type,
            'sent_at' => $sentAt,
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
