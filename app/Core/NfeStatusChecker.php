<?php

namespace App\Core;

use App\Models\Order;

/**
 * Emissao de NF-e na Asaas e' assincrona (depende de aprovacao da prefeitura) -- na hora que
 * PaymentController::maybeIssueInvoice() cria a nota, o pdfUrl quase sempre ainda vem vazio. Esse
 * "lazy check" (mesmo padrao de QuoteLeadReminder/FollowUpReminder, sem cron nesse plano
 * Hostinger) reconsulta as notas pendentes a cada carga do Dashboard, ate o pdfUrl aparecer (ou
 * a nota ser cancelada/dar erro, que tambem para de reconsultar -- ver Order::pendingNfeCheck()).
 */
class NfeStatusChecker
{
    public static function processDue(): void
    {
        $pending = Order::pendingNfeCheck();
        if (!$pending) {
            return;
        }

        $client = new AsaasClient();

        foreach ($pending as $row) {
            try {
                $invoice = $client->getInvoice($row['nfe_invoice_id']);
                Order::updateNfe(
                    (int) $row['id'],
                    $row['nfe_invoice_id'],
                    $invoice['status'] ?? null,
                    $invoice['pdfUrl'] ?? null,
                    $invoice['number'] ?? null
                );
            } catch (\Throwable $e) {
                // Best-effort -- uma falha de rede/API nao deve travar as outras notas pendentes
                // nem a carga do Dashboard. Fica na fila e tenta de novo na proxima carga.
            }
        }
    }
}
