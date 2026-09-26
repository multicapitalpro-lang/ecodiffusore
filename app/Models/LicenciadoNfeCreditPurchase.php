<?php

namespace App\Models;

use App\Core\Database;

/** Fase 138: creditos pre-pagos de nota fiscal (ferramenta premium "Nota Fiscal Automatica") --
 *  mesmo mecanismo de checkout prepago via Mercado Pago que LicenciadoSeatAddon/LicenciadoSubscription
 *  ja usam, so que aqui o credito e' CONSUMIVEL (nao expira por tempo, vai sendo descontado a
 *  cada nota emitida) em vez de expirar por periodo. Consumo de verdade (emissaoes) e' Fase 2,
 *  quando o provedor de nota for escolhido -- por enquanto so acumula saldo comprado.
 *
 *  IMPORTANTE: o pagamento desses creditos vai pra conta PESSOAL do desenvolvedor (mesmo Mercado
 *  Pago ja configurado em config.php pras outras ferramentas premium), nao pra Ecodiffusore --
 *  decisao de negocio do usuario, ja documentada nas outras ferramentas de assinatura. */
class LicenciadoNfeCreditPurchase
{
    public static function create(int $licenciadoId, int $quantity, float $amount): array
    {
        $reference = bin2hex(random_bytes(16));

        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_nfe_credit_purchases (licenciado_id, quantity, amount, status, external_reference)
             VALUES (:licenciado_id, :quantity, :amount, "pendente", :reference)'
        );
        $stmt->execute(['licenciado_id' => $licenciadoId, 'quantity' => $quantity, 'amount' => $amount, 'reference' => $reference]);

        return self::find((int) Database::connection()->lastInsertId());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_nfe_credit_purchases WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByExternalReference(string $reference): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_nfe_credit_purchases WHERE external_reference = :ref');
        $stmt->execute(['ref' => $reference]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setCheckoutUrl(int $id, string $url): void
    {
        $stmt = Database::connection()->prepare('UPDATE licenciado_nfe_credit_purchases SET checkout_url = :url WHERE id = :id');
        $stmt->execute(['url' => $url, 'id' => $id]);
    }

    public static function markPaid(int $id, string $mpPaymentId): void
    {
        $purchase = self::find($id);
        if (!$purchase || $purchase['status'] === 'ativa') {
            return;
        }

        $stmt = Database::connection()->prepare(
            "UPDATE licenciado_nfe_credit_purchases SET status = 'ativa', mp_payment_id = :mp_id, paid_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['mp_id' => $mpPaymentId, 'id' => $id]);
    }

    /** Saldo de notas disponiveis pra emitir -- soma de tudo que foi pago. Fase 2 vai descontar o
     *  que ja foi efetivamente emitido; por enquanto e' so o total comprado. */
    public static function balanceFor(int $licenciadoId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM licenciado_nfe_credit_purchases WHERE licenciado_id = :lid AND status = 'ativa'"
        );
        $stmt->execute(['lid' => $licenciadoId]);
        return (int) $stmt->fetchColumn();
    }

    public static function forUser(int $licenciadoId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_nfe_credit_purchases WHERE licenciado_id = :lid ORDER BY created_at DESC');
        $stmt->execute(['lid' => $licenciadoId]);
        return $stmt->fetchAll();
    }
}
