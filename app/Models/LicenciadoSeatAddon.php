<?php

namespace App\Models;

use App\Core\Database;
use App\Core\SubscriptionPlans;

/**
 * Vaga extra de colaborador (Fase 86) -- a assinatura base cobre Licenciado + ate
 * SubscriptionPlans::INCLUDED_SEATS colaboradores (Gestor+Vendedor); passar disso exige comprar
 * vagas extras, prepago por periodo igual LicenciadoSubscription (nao e' assinatura recorrente de
 * verdade na Mercado Pago). Uma linha pode cobrir mais de 1 vaga (quantity).
 */
class LicenciadoSeatAddon
{
    public static function create(int $licenciadoId, string $plan, int $quantity): array
    {
        $reference = bin2hex(random_bytes(16));
        $amount = SubscriptionPlans::SEAT_PRICES[$plan] * $quantity;

        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_seat_addons (licenciado_id, plan, quantity, status, amount, external_reference)
             VALUES (:licenciado_id, :plan, :quantity, "pendente", :amount, :reference)'
        );
        $stmt->execute(['licenciado_id' => $licenciadoId, 'plan' => $plan, 'quantity' => $quantity, 'amount' => $amount, 'reference' => $reference]);

        return self::find((int) Database::connection()->lastInsertId());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_seat_addons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByExternalReference(string $reference): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_seat_addons WHERE external_reference = :ref');
        $stmt->execute(['ref' => $reference]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setCheckoutUrl(int $id, string $url): void
    {
        $stmt = Database::connection()->prepare('UPDATE licenciado_seat_addons SET checkout_url = :url WHERE id = :id');
        $stmt->execute(['url' => $url, 'id' => $id]);
    }

    /** Soma das vagas extras ativas (ainda nao vencidas) desse Licenciado -- 0 se nunca comprou
     *  ou se todas ja venceram. */
    public static function activeSeatsFor(int $licenciadoId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM licenciado_seat_addons
             WHERE licenciado_id = :lid AND status = 'ativa' AND expires_at > NOW()"
        );
        $stmt->execute(['lid' => $licenciadoId]);
        return (int) $stmt->fetchColumn();
    }

    public static function forUser(int $licenciadoId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_seat_addons WHERE licenciado_id = :lid ORDER BY created_at DESC');
        $stmt->execute(['lid' => $licenciadoId]);
        return $stmt->fetchAll();
    }

    /** Mesma logica de LicenciadoSubscription::markPaid() -- estende a partir do vencimento atual
     *  se ainda tiver vaga extra ativa, ou de agora se nao tiver nenhuma vigente. Vagas extras
     *  compradas em momentos diferentes podem vencer em datas diferentes entre si -- normal, cada
     *  compra e' seu proprio periodo prepago. */
    public static function markPaid(int $id, string $mpPaymentId): void
    {
        $addon = self::find($id);
        if (!$addon || $addon['status'] === 'ativa') {
            return;
        }

        $months = ['mensal' => 1, 'semestral' => 6, 'anual' => 12][$addon['plan']];
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$months} months"));

        $stmt = Database::connection()->prepare(
            "UPDATE licenciado_seat_addons SET status = 'ativa', mp_payment_id = :mp_id,
                started_at = NOW(), expires_at = :expires_at WHERE id = :id"
        );
        $stmt->execute(['mp_id' => $mpPaymentId, 'expires_at' => $expiresAt, 'id' => $id]);
    }
}
