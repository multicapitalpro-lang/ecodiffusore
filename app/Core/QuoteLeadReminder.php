<?php

namespace App\Core;

use App\Models\Quote;

/**
 * Lembrete calmo pro PROPRIO lead/cliente quando um orcamento fica parado sem resposta -- "lazy
 * check" no Dashboard, mesmo padrao ja usado pelo Resumo Semanal/Lembrete de follow-up (sem cron
 * nesse plano Hostinger). Regua deliberadamente curta (2 disparos, dias 3 e 7 depois do orcamento)
 * pra recuperar venda esfriada sem virar spam -- preocupacao levantada explicitamente na hora de
 * sugerir essa funcionalidade.
 */
class QuoteLeadReminder
{
    private const SCHEDULE_DAYS = [3, 7];

    public static function processDue(): void
    {
        $quotes = Quote::all(['status' => 'aberto']);
        $today = strtotime(date('Y-m-d'));

        foreach ($quotes as $quote) {
            $count = (int) ($quote['lead_reminder_count'] ?? 0);
            if ($count >= count(self::SCHEDULE_DAYS)) {
                continue;
            }
            if (!empty($quote['valid_until']) && strtotime($quote['valid_until']) < $today) {
                continue;
            }

            $daysSince = (int) floor(($today - strtotime($quote['quote_date'])) / 86400);
            $targetDay = self::SCHEDULE_DAYS[$count];

            if ($daysSince >= $targetDay) {
                Notifier::orcamentoLembreteLead($quote);
                self::markSent((int) $quote['id'], $count + 1);
            }
        }
    }

    private static function markSent(int $quoteId, int $newCount): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE quotes SET lead_reminder_count = :count, lead_reminder_last_sent_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['count' => $newCount, 'id' => $quoteId]);
    }
}
