<?php

namespace App\Core;

use App\Models\Order;

/**
 * Cobranca urgente pro cliente que JA PAGOU mas ainda NAO solicitou a Garantia Estendida (90
 * dias) -- ele tem so 15 dias apos a confirmacao do pagamento pra pedir. Pedido explicito do
 * usuario: a solicitacao em si (WarrantyRequest, ja existente em /painel/minhas-garantias) NAO
 * muda -- isso aqui e' so o lembrete escalando em urgencia nos dias 1/5/10/15 (WhatsApp + e-mail).
 * Lazy-check (sem cron nesse plano Hostinger), mesmo padrao de QuoteLeadReminder.
 */
class ExtendedWarrantyReminder
{
    private const SCHEDULE_DAYS = [1, 5, 10, 15];

    public static function processDue(): void
    {
        $orders = Order::pendingExtendedWarrantyReminders();
        $now = time();

        foreach ($orders as $order) {
            $count = (int) ($order['warranty_reminder_count'] ?? 0);
            if ($count >= count(self::SCHEDULE_DAYS)) {
                continue;
            }

            $daysSince = (int) floor(($now - strtotime($order['verified_at'])) / 86400);
            $targetDay = self::SCHEDULE_DAYS[$count];

            if ($daysSince >= $targetDay) {
                Notifier::garantiaEstendidaLembrete($order, $count + 1);
                Order::markWarrantyReminderSent((int) $order['id'], $count + 1);
            }
        }
    }
}
