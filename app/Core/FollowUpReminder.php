<?php

namespace App\Core;

use App\Models\User;

/**
 * Lembrete diario de follow-up por WhatsApp pro Vendedor -- "lazy check" no Dashboard, mesmo
 * padrao do WeeklyDigest (sem cron nesse plano Hostinger). Um lead com retorno agendado pra hoje
 * (ou atrasado) sem lembrete ja enviado hoje entra na lista; agrupa por vendedor pra mandar 1
 * mensagem so, mesmo com varios leads no mesmo dia.
 */
class FollowUpReminder
{
    public static function processDue(): void
    {
        $rows = Database::connection()->query(
            "SELECT l.id, l.name AS lead_name, l.assigned_to_user_id
             FROM leads l
             JOIN lead_notes n ON n.lead_id = l.id
             WHERE n.follow_up_done = 0 AND n.follow_up_date IS NOT NULL AND n.follow_up_date <= CURDATE()
               AND l.assigned_to_user_id IS NOT NULL
               AND (l.follow_up_reminder_sent_at IS NULL OR DATE(l.follow_up_reminder_sent_at) < CURDATE())
             GROUP BY l.id"
        )->fetchAll();

        if (!$rows) {
            return;
        }

        $bySeller = [];
        foreach ($rows as $row) {
            $bySeller[(int) $row['assigned_to_user_id']][] = $row;
        }

        foreach ($bySeller as $sellerId => $leads) {
            $seller = User::find($sellerId);
            if (!$seller) {
                continue;
            }

            $names = array_map(fn ($l) => $l['lead_name'], $leads);
            Notifier::followUpLembrete($seller, $names);

            $ids = array_map(fn ($l) => (int) $l['id'], $leads);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::connection()->prepare("UPDATE leads SET follow_up_reminder_sent_at = NOW() WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
        }
    }
}
