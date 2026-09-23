<?php

namespace App\Models;

use App\Core\Database;

/** Registro de "esbarrou no paywall" (Fase 86) -- toda vez que SubscriptionGate::requireAccess()
 *  bloqueia uma acao com um $feature nomeado, grava aqui. Alimenta o banner de remarketing (sem
 *  WhatsApp por decisao do usuario -- so destaque visual quando a pessoa volta a abrir o painel/app). */
class SubscriptionPaywallHit
{
    public const LABELS = [
        'financeiro' => 'Financeiro (Caixas/Contas a Pagar e Receber)',
        'whatsapp' => 'WhatsApp integrado',
        'relatorios' => 'Relatórios completos',
        'email_profissional' => 'E-mail Profissional',
        'calendario' => 'Calendário da Equipe',
        'simulador_comissao' => 'Simulador de Comissão',
    ];

    public static function record(int $userId, ?int $licenciadoId, string $feature): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO subscription_paywall_hits (user_id, licenciado_id, feature) VALUES (:uid, :lid, :feature)'
        );
        $stmt->execute(['uid' => $userId, 'lid' => $licenciadoId, 'feature' => $feature]);
    }

    /** Ultimo toque no paywall de toda a rede desse Licenciado (ele mesmo, Gestor ou Vendedor) --
     *  usado pro banner "vocês tentaram usar X" saber o que mostrar. Null se nunca bateu em nada. */
    public static function latestForLicenciado(int $licenciadoId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT h.*, u.name AS user_name FROM subscription_paywall_hits h
             JOIN users u ON u.id = h.user_id
             WHERE h.licenciado_id = :lid ORDER BY h.created_at DESC LIMIT 1'
        );
        $stmt->execute(['lid' => $licenciadoId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
