<?php

namespace App\Core;

use App\Models\SellerActivity;
use App\Models\User;

/**
 * Alerta diario por WhatsApp pra rede (gestor/licenciado/supervisor/gerente/admin) quando um
 * Vendedor fica SellerActivity::INACTIVITY_DAYS dias ou mais sem atividade -- "lazy check" no
 * Dashboard, mesmo padrao do WeeklyDigest. So reenvia depois de RESEND_AFTER_DAYS do ultimo
 * alerta sobre o mesmo vendedor, pra nao virar spam diario enquanto ele continua parado.
 */
class InactivityAlert
{
    private const RESEND_AFTER_DAYS = 7;

    public static function processDue(): void
    {
        foreach (User::allByRole('vendedor') as $seller) {
            self::checkSeller($seller);
        }
    }

    private static function checkSeller(array $seller): void
    {
        $lastAlert = $seller['inactivity_alert_sent_at'] ?? null;
        if ($lastAlert && strtotime($lastAlert) > strtotime('-' . self::RESEND_AFTER_DAYS . ' days')) {
            return;
        }

        $inactive = SellerActivity::inactiveAmong([$seller]);
        if (!$inactive) {
            return;
        }

        Notifier::vendedorInativo($seller, $inactive[0]['days_inactive']);

        $stmt = Database::connection()->prepare('UPDATE users SET inactivity_alert_sent_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => (int) $seller['id']]);
    }
}
