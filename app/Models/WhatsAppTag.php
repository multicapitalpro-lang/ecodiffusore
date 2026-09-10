<?php

namespace App\Models;

use App\Core\Database;

/** Tags de classificacao de conversa (Fase 33) -- separadas do status do Lead (pedido explicito
 *  do usuario), por usuario dono da instancia (cada Vendedor/Gestor/Licenciado tem as suas). */
class WhatsAppTag
{
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_tags WHERE user_id = :uid ORDER BY name');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function create(int $userId, string $name, string $color): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_tags (user_id, name, color) VALUES (:uid, :name, :color)
             ON DUPLICATE KEY UPDATE color = VALUES(color)'
        );
        $stmt->execute(['uid' => $userId, 'name' => trim($name), 'color' => $color]);

        $existing = Database::connection()->prepare('SELECT id FROM whatsapp_tags WHERE user_id = :uid AND name = :name');
        $existing->execute(['uid' => $userId, 'name' => trim($name)]);
        return (int) $existing->fetchColumn();
    }

    public static function delete(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM whatsapp_tags WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public static function assignToChat(int $chatId, int $tagId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT IGNORE INTO whatsapp_chat_tags (chat_id, tag_id) VALUES (:chat_id, :tag_id)'
        );
        $stmt->execute(['chat_id' => $chatId, 'tag_id' => $tagId]);
    }

    public static function removeFromChat(int $chatId, int $tagId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM whatsapp_chat_tags WHERE chat_id = :chat_id AND tag_id = :tag_id'
        );
        $stmt->execute(['chat_id' => $chatId, 'tag_id' => $tagId]);
    }
}
