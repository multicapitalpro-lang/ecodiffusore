<?php

namespace App\Models;

use App\Core\Database;
use App\Core\SubscriptionPlans;

/**
 * Assinatura do Licenciado (Fase 32) -- desbloqueia Relatorios/Financeiro (Caixas, Contas a Pagar/
 * Receber, Controle Fiscal/Antecipacoes) pra ele, Gestor e Vendedor da rede dele. Modelo de
 * pagamento: prepago por periodo (nao e' assinatura recorrente de verdade na Mercado Pago -- Checkout
 * simples, igual o Asaas ja usa aqui, evita guardar dado de cartao) -- vence em expires_at, renovacao
 * e' um novo pagamento que estende a partir do vencimento atual (ou de agora, se ja tiver vencido).
 */
class LicenciadoSubscription
{
    public static function create(int $userId, string $plan): array
    {
        $reference = bin2hex(random_bytes(16));
        $amount = SubscriptionPlans::PRICES[$plan];

        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_subscriptions (user_id, plan, status, amount, external_reference)
             VALUES (:user_id, :plan, "pendente", :amount, :reference)'
        );
        $stmt->execute(['user_id' => $userId, 'plan' => $plan, 'amount' => $amount, 'reference' => $reference]);

        return self::find((int) Database::connection()->lastInsertId());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_subscriptions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByExternalReference(string $reference): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_subscriptions WHERE external_reference = :ref');
        $stmt->execute(['ref' => $reference]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setCheckoutUrl(int $id, string $url): void
    {
        $stmt = Database::connection()->prepare('UPDATE licenciado_subscriptions SET checkout_url = :url WHERE id = :id');
        $stmt->execute(['url' => $url, 'id' => $id]);
    }

    /** Ultima assinatura ativa (status=ativa e ainda nao vencida) de um Licenciado -- null se
     *  nunca assinou ou se venceu (nesse caso fica "expirada" so quando o lazy-check rodar, mas
     *  isActive() ja considera vencida na hora certa independente do status gravado). */
    public static function activeFor(int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM licenciado_subscriptions WHERE user_id = :uid AND status = 'ativa' AND expires_at > NOW()
             ORDER BY expires_at DESC LIMIT 1"
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function isActive(int $userId): bool
    {
        return self::activeFor($userId) !== null;
    }

    /** Historico completo (mais recente primeiro) -- usado na tela de assinatura pro Licenciado
     *  ver pagamentos passados/pendentes. */
    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_subscriptions WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Confirma pagamento (webhook Mercado Pago) -- estende a partir do vencimento atual se ainda
     *  ativo (renovacao antecipada nao perde tempo pago), ou a partir de agora se vencido/nunca
     *  assinou. */
    public static function markPaid(int $id, string $mpPaymentId): void
    {
        $subscription = self::find($id);
        if (!$subscription || $subscription['status'] === 'ativa') {
            return;
        }

        $current = self::activeFor((int) $subscription['user_id']);
        $base = $current ? max(strtotime($current['expires_at']), time()) : time();
        $months = SubscriptionPlans::MONTHS[$subscription['plan']];
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$months} months", $base));

        $stmt = Database::connection()->prepare(
            "UPDATE licenciado_subscriptions SET status = 'ativa', mp_payment_id = :mp_id,
                started_at = NOW(), expires_at = :expires_at WHERE id = :id"
        );
        $stmt->execute(['mp_id' => $mpPaymentId, 'expires_at' => $expiresAt, 'id' => $id]);
    }
}
