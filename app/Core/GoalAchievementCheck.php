<?php

namespace App\Core;

use App\Models\Goal;

/** Fase 97: rotina lazy (sem cron, mesmo padrao de GoalPaceAlert/ReferralActivationCheck) -- roda
 *  a cada carregamento de /painel/metas ou /painel/mural, varre metas em andamento e posta no
 *  Mural de Conquistas a primeira vez que cada uma bate 100%. Nao reusa GoalPaceAlert (aquela e'
 *  sobre CAIR do ritmo, essa e' sobre BATER a meta -- guardas de dedup independentes,
 *  achievement_posted_at nunca o mesmo campo que pace_alert_sent_at). */
class GoalAchievementCheck
{
    public static function processDue(): void
    {
        foreach (Goal::allActive() as $goal) {
            self::checkGoal($goal);
        }
    }

    private static function checkGoal(array $goal): void
    {
        if (!empty($goal['achievement_posted_at'])) {
            return;
        }

        if (!Goal::progress($goal)['reached']) {
            return;
        }

        TeamFeed::goalReached($goal);
        Goal::markAchievementPosted((int) $goal['id']);
    }
}
