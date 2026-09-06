<?php

namespace App\Models;

use App\Core\Database;

/**
 * Timeline de observacoes por Lead (ligacoes, retorno combinado etc.) + lembrete de follow-up
 * opcional -- mesmo padrao ja usado em App\Models\ClientNote, so que pra Lead (que antes so tinha
 * o campo "message" capturado na entrada, sem espaco pra anotar o que aconteceu depois).
 */
class LeadNote
{
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM lead_notes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forLead(int $leadId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ln.*, u.name AS user_name FROM lead_notes ln
             LEFT JOIN users u ON u.id = ln.user_id
             WHERE ln.lead_id = :id ORDER BY ln.created_at DESC'
        );
        $stmt->execute(['id' => $leadId]);
        return $stmt->fetchAll();
    }

    /** Uma nova nota conta como "acabei de dar retorno" -- fecha qualquer lembrete pendente
     *  anterior desse mesmo lead automaticamente.
     * @param ?array $attachment resultado de App\Core\FileUpload::storeLeadNoteAttachment() */
    public static function create(int $leadId, int $userId, string $note, ?string $followUpDate = null, ?array $attachment = null): void
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            'INSERT INTO lead_notes (lead_id, user_id, note, follow_up_date, attachment_path, attachment_original_name)
             VALUES (:lead_id, :user_id, :note, :follow_up_date, :attachment_path, :attachment_original_name)'
        );
        $stmt->execute([
            'lead_id' => $leadId,
            'user_id' => $userId,
            'note' => $note,
            'follow_up_date' => $followUpDate,
            'attachment_path' => $attachment['stored_name'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
        ]);

        $db->prepare('UPDATE lead_notes SET follow_up_done = 1 WHERE lead_id = :id AND follow_up_done = 0 AND id != :new_id')
            ->execute(['id' => $leadId, 'new_id' => $db->lastInsertId()]);
    }

    /** Notas de varios leads de uma vez (evita N+1 no board do Kanban, que ja carrega todos os
     *  leads escopados numa tela so) -- agrupadas por lead_id, mais recente primeiro.
     * @return array<int, array> lead_id => lista de notas */
    public static function forLeads(array $leadIds): array
    {
        if (!$leadIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT ln.*, u.name AS user_name FROM lead_notes ln
             LEFT JOIN users u ON u.id = ln.user_id
             WHERE ln.lead_id IN ({$placeholders}) ORDER BY ln.created_at DESC"
        );
        $stmt->execute(array_values($leadIds));

        $byLead = [];
        foreach ($stmt->fetchAll() as $row) {
            $byLead[(int) $row['lead_id']][] = $row;
        }
        return $byLead;
    }

    /** Follow-up pendente (nao concluido) de cada lead em $leadIds, pra mostrar o aviso no Kanban
     *  -- so o mais recente de cada lead (se tiver mais de uma linha aberta por algum motivo).
     * @return array<int, string> lead_id => follow_up_date (Y-m-d) */
    public static function pendingFollowUps(array $leadIds): array
    {
        if (!$leadIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT lead_id, MAX(follow_up_date) AS follow_up_date FROM lead_notes
             WHERE lead_id IN ({$placeholders}) AND follow_up_done = 0 AND follow_up_date IS NOT NULL
             GROUP BY lead_id"
        );
        $stmt->execute(array_values($leadIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['lead_id']] = $row['follow_up_date'];
        }
        return $result;
    }
}
