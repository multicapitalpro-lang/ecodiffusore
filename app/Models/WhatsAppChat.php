<?php

namespace App\Models;

use App\Core\Database;

class WhatsAppChat
{
    public static function forInstance(int $instanceId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*,
                (SELECT GROUP_CONCAT(t.id, ":", t.name, ":", t.color SEPARATOR "|")
                 FROM whatsapp_chat_tags ct JOIN whatsapp_tags t ON t.id = ct.tag_id
                 WHERE ct.chat_id = c.id) AS tags_raw,
                l.name AS lead_name, l.status AS lead_status
             FROM whatsapp_chats c
             LEFT JOIN leads l ON l.id = c.lead_id
             WHERE c.instance_id = :instance_id
             ORDER BY c.last_message_at DESC'
        );
        $stmt->execute(['instance_id' => $instanceId]);
        return array_map([self::class, 'withParsedTags'], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*,
                (SELECT GROUP_CONCAT(t.id, ":", t.name, ":", t.color SEPARATOR "|")
                 FROM whatsapp_chat_tags ct JOIN whatsapp_tags t ON t.id = ct.tag_id
                 WHERE ct.chat_id = c.id) AS tags_raw,
                l.name AS lead_name, l.status AS lead_status
             FROM whatsapp_chats c LEFT JOIN leads l ON l.id = c.lead_id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? self::withParsedTags($row) : null;
    }

    public static function findByJid(int $instanceId, string $remoteJid): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM whatsapp_chats WHERE instance_id = :instance_id AND remote_jid = :jid'
        );
        $stmt->execute(['instance_id' => $instanceId, 'jid' => $remoteJid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Cria ou atualiza (nome/grupo) -- usado tanto pela sincronizacao inicial (fetchChats) quanto
     *  pelo webhook em toda mensagem nova. Nunca sobrescreve last_message_at/preview aqui -- isso
     *  e' responsabilidade de touchLastMessage(), chamado so quando ha mensagem de verdade. */
    public static function upsert(int $instanceId, string $remoteJid, ?string $name, bool $isGroup): int
    {
        $existing = self::findByJid($instanceId, $remoteJid);
        if ($existing) {
            if ($name && $name !== $existing['name']) {
                $stmt = Database::connection()->prepare('UPDATE whatsapp_chats SET name = :name WHERE id = :id');
                $stmt->execute(['name' => $name, 'id' => $existing['id']]);
            }
            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_chats (instance_id, remote_jid, name, is_group) VALUES (:instance_id, :jid, :name, :is_group)'
        );
        $stmt->execute(['instance_id' => $instanceId, 'jid' => $remoteJid, 'name' => $name, 'is_group' => $isGroup ? 1 : 0]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function touchLastMessage(int $chatId, string $preview, string $sentAt, bool $incrementUnread): void
    {
        $sql = 'UPDATE whatsapp_chats SET last_message_at = :sent_at, last_message_preview = :preview';
        if ($incrementUnread) {
            $sql .= ', unread_count = unread_count + 1';
        }
        $sql .= ' WHERE id = :id';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['sent_at' => $sentAt, 'preview' => mb_substr($preview, 0, 250), 'id' => $chatId]);
    }

    public static function markRead(int $chatId): void
    {
        $stmt = Database::connection()->prepare('UPDATE whatsapp_chats SET unread_count = 0 WHERE id = :id');
        $stmt->execute(['id' => $chatId]);
    }

    public static function linkLead(int $chatId, ?int $leadId): void
    {
        $stmt = Database::connection()->prepare('UPDATE whatsapp_chats SET lead_id = :lead_id WHERE id = :id');
        $stmt->execute(['lead_id' => $leadId, 'id' => $chatId]);
    }

    private static function withParsedTags(array $chat): array
    {
        $chat['tags'] = [];
        if (!empty($chat['tags_raw'])) {
            foreach (explode('|', $chat['tags_raw']) as $piece) {
                [$id, $name, $color] = array_pad(explode(':', $piece, 3), 3, null);
                if ($id !== null) {
                    $chat['tags'][] = ['id' => (int) $id, 'name' => $name, 'color' => $color];
                }
            }
        }
        unset($chat['tags_raw']);
        return $chat;
    }
}
