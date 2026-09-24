<?php

namespace App\Core;

use App\Models\ReportSchedule;

/**
 * Processa os agendamentos de relatorio vencidos (App\Models\ReportSchedule::activeDue()) e
 * envia por e-mail -- sem essa checagem, "Agendamentos" em Financeiro > Relatorios so gravava
 * a preferencia no banco e nunca disparava nada (nao ha cron nesse plano Hostinger). Segue o
 * mesmo padrao "lazy check" ja usado em Order::expireStalePending(): roda no carregamento de
 * uma pagina bastante visitada (o Dashboard), nao em um agendador de verdade -- e' best-effort,
 * so dispara quando algum membro da equipe estiver logado no dia em que o relatorio vence.
 */
class ReportScheduler
{
    public static function processDue(): void
    {
        foreach (ReportSchedule::activeDue() as $schedule) {
            [$from, $to] = self::rangeFor($schedule['frequency']);
            $type = $schedule['report_type'];
            // Agendamento ja roda sem escopo de rede (sellerIds null, ver comentario da classe --
            // e' um envio global, nao vinculado a hierarquia de quem criou o agendamento). Papel
            // 'admin' aqui so' escolhe o MESMO rotulo/agrupamento que um Admin veria na tela (por
            // Licenca, Fase 114) -- consistente com o escopo global que esse envio ja tinha antes.
            $title = FinancialReports::title($type, 'admin');
            $report = FinancialReports::generate($type, $from, $to, null, 'admin');

            ob_start();
            View::render('painel/reports/pdf', compact('title', 'from', 'to', 'report'), null);
            $html = ob_get_clean();

            // Mailer::send so manda HTML puro (sem lib de anexo disponivel) -- o relatorio vai
            // no corpo do e-mail, com o mesmo layout usado no PDF baixavel manualmente.
            $sent = Mailer::send($schedule['recipient_email'], 'Relatório agendado: ' . $title, $html);

            if ($sent) {
                ReportSchedule::touchSent((int) $schedule['id']);
            }
        }
    }

    private static function rangeFor(string $frequency): array
    {
        return match ($frequency) {
            'diario' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'semanal' => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d', strtotime('-1 day'))],
            'mensal' => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
            default => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')],
        };
    }
}
