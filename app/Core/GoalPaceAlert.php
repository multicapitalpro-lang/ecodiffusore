<?php

namespace App\Core;

use App\Models\Goal;
use App\Models\User;

/** Fase 94: rotina lazy (sem cron nesse plano Hostinger, mesmo padrao de InactivityAlert/
 *  CalendarEvent::dueForReminder) -- roda a cada carregamento de /painel/metas, varre as metas
 *  em andamento e avisa quem esta visivelmente atrasado (10+ pontos percentuais abaixo do
 *  esperado pros dias ja decorridos). So alerta quem tem acesso a assinatura ativa -- ferramenta
 *  premium, mesma trava de App\Core\SubscriptionGate ja usada em Calendario/Simulador. */
class GoalPaceAlert
{
    private const RESEND_AFTER_DAYS = 3;

    public static function processDue(): void
    {
        foreach (Goal::allActive() as $goal) {
            self::checkGoal($goal);
        }
    }

    private static function checkGoal(array $goal): void
    {
        $lastAlert = $goal['pace_alert_sent_at'] ?? null;
        if ($lastAlert && strtotime($lastAlert) > strtotime('-' . self::RESEND_AFTER_DAYS . ' days')) {
            return;
        }

        $seller = User::find((int) $goal['seller_id']);
        if (!$seller || !SubscriptionGate::hasAccess($seller)) {
            return;
        }

        $pace = Goal::pace($goal);
        if ($pace['status'] !== 'atrasado') {
            return;
        }

        Notifier::metaForaDoRitmo(array_merge($goal, $pace));
        Goal::markPaceAlertSent((int) $goal['id']);
    }
}
