<?php

namespace App\Core;

use App\Models\Order;

/**
 * Reconsulta o status real de rastreio (Correios) dos pedidos ainda em transito -- mesmo "lazy
 * check" sem cron do NfeStatusChecker/ExtendedWarrantyReminder, disparado a cada carga do
 * Dashboard. So reconsulta pedido que ja tem tracking_code, ainda nao foi entregue, e nao foi
 * checado nas ultimas 6 horas (Order::pendingCorreiosCheck()) -- evita bater na API dos Correios
 * a cada carga de pagina pro mesmo pedido.
 */
class CorreiosTrackingChecker
{
    public static function processDue(): void
    {
        $pending = Order::pendingCorreiosCheck();
        if (!$pending) {
            return;
        }

        $client = new CorreiosClient();

        foreach ($pending as $row) {
            try {
                $result = $client->track($row['tracking_code']);
                // Sempre grava tracking_checked_at (mesmo sem resultado) -- senao um codigo que
                // falha (ex: ainda nao postado) seria reconsultado a cada carga do Dashboard em
                // vez de respeitar a janela de 6h de Order::pendingCorreiosCheck().
                Order::updateTrackingStatus(
                    (int) $row['id'],
                    $result['status'] ?? null,
                    $result['date'] ?? null,
                    $result['entregue'] ?? false
                );
            } catch (\Throwable $e) {
                // Best-effort -- uma falha nao trava os demais pedidos nem a carga do Dashboard.
            }
        }
    }
}
