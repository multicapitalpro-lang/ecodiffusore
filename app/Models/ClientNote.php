<?php

namespace App\Models;

use App\Core\Database;

class ClientNote
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM client_notes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forClient(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT cn.*, u.name AS user_name FROM client_notes cn
             LEFT JOIN users u ON u.id = cn.user_id
             WHERE cn.client_id = :id ORDER BY cn.created_at DESC'
        );
        $stmt->execute(['id' => $clientId]);
        return $stmt->fetchAll();
    }

    /** Uma nova nota conta como "acabei de dar retorno" -- fecha qualquer lembrete pendente
     *  anterior desse mesmo cliente automaticamente, sem precisar de um botao "concluir" separado.
     * @param ?array $attachment resultado de App\Core\FileUpload::storeClientNoteAttachment() */
    public static function create(int $clientId, int $userId, string $note, ?string $followUpDate = null, ?array $attachment = null): void
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            'INSERT INTO client_notes (client_id, user_id, note, follow_up_date, attachment_path, attachment_original_name)
             VALUES (:client_id, :user_id, :note, :follow_up_date, :attachment_path, :attachment_original_name)'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'user_id' => $userId,
            'note' => $note,
            'follow_up_date' => $followUpDate,
            'attachment_path' => $attachment['stored_name'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
        ]);

        $db->prepare('UPDATE client_notes SET follow_up_done = 1 WHERE client_id = :id AND follow_up_done = 0 AND id != :new_id')
            ->execute(['id' => $clientId, 'new_id' => $db->lastInsertId()]);
    }
}
