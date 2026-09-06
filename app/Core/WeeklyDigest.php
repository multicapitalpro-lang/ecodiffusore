<?php

namespace App\Core;

use App\Models\Order;
use App\Models\User;
use PDO;

/**
 * Resumo semanal automatico por e-mail (desempenho da equipe nos ultimos 7 dias) pro Licenciado/
 * Gestor que ativar. Sem cron nesse plano Hostinger -- mesmo padrao "lazy check" ja usado em
 * App\Core\ReportScheduler: roda no carregamento do Dashboard, so dispara de fato quando ja faz
 * 7 dias ou mais desde o ultimo envio (ou nunca foi enviado).
 */
class WeeklyDigest
{
    public static function processDue(): void
    {
        $ids = Database::connection()->query(
            "SELECT id FROM users WHERE weekly_digest_enabled = 1
             AND (weekly_digest_last_sent IS NULL OR weekly_digest_last_sent <= DATE_SUB(CURDATE(), INTERVAL 7 DAY))"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $userId) {
            self::sendFor((int) $userId);
        }
    }

    private static function sendFor(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        // downlineIds inclui o proprio usuario + gestor(es) -- sellerRanking() ja filtra pra so
        // role_slug='vendedor', entao o resumo sai limpo mesmo passando o downline inteiro.
        $sellerIds = User::downlineIds($userId);
        $from = date('Y-m-d', strtotime('-7 days'));
        $to = date('Y-m-d', strtotime('-1 day'));

        $rows = Order::sellerRanking($from, $to, $sellerIds);
        $periodLabel = date('d/m', strtotime($from)) . ' a ' . date('d/m', strtotime($to));

        Notifier::weeklyDigest($user, $rows, $periodLabel);

        $stmt = Database::connection()->prepare('UPDATE users SET weekly_digest_last_sent = CURDATE() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    public static function setEnabled(int $userId, bool $enabled): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET weekly_digest_enabled = :enabled WHERE id = :id');
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $userId]);
    }
}
