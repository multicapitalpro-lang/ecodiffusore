<?php

namespace App\Models;

use App\Core\Database;

/**
 * Solicitacao de e-mail profissional no dominio (Fase 84) -- ex: comercial.cascavel@
 * ecodiffusorebrasil.com.br. Beneficio de quem tem assinatura ativa (ver App\Core\SubscriptionGate),
 * nao um produto cobrado a parte -- a decisao do usuario foi embutir isso no valor da assinatura
 * que ja existe (App\Core\SubscriptionPlans), nao criar uma cobranca nova. Provisionamento real da
 * caixa (criar no hPanel da Hostinger) e' manual do admin -- sem API publica documentada pra isso
 * em hospedagem compartilhada -- entao esta tabela so' rastreia o pedido/status, nunca cria a
 * caixa de verdade.
 */
class LicenciadoEmailRequest
{
    public const DOMAIN = 'ecodiffusorebrasil.com.br';

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, u.name AS user_name FROM licenciado_email_requests r JOIN users u ON u.id = r.user_id WHERE r.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forUser(int $userId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM licenciado_email_requests WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT r.*, u.name AS user_name FROM licenciado_email_requests r JOIN users u ON u.id = r.user_id ORDER BY
                FIELD(r.status, "pendente", "ativo", "recusado"), r.created_at DESC'
        )->fetchAll();
    }

    /** Cota conta pendente + ativo -- um pedido pendente ja "reserva" a vaga, senao o sistema
     *  deixaria aceitar mais pedidos do que o plano de hospedagem realmente comporta. */
    public static function countTowardQuota(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM licenciado_email_requests WHERE status IN ('pendente', 'ativo')")
            ->fetchColumn();
    }

    public static function addressTaken(string $fullAddress): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM licenciado_email_requests WHERE full_address = :addr AND status != 'recusado'"
        );
        $stmt->execute(['addr' => $fullAddress]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public static function create(int $userId, string $localPart): int
    {
        $fullAddress = strtolower($localPart) . '@' . self::DOMAIN;

        $stmt = Database::connection()->prepare(
            'INSERT INTO licenciado_email_requests (user_id, local_part, full_address) VALUES (:uid, :local, :full)'
        );
        $stmt->execute(['uid' => $userId, 'local' => $localPart, 'full' => $fullAddress]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function approve(int $id, int $resolvedByUserId, ?string $note): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE licenciado_email_requests SET status = 'ativo', admin_note = :note,
                resolved_by_user_id = :resolver, resolved_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['note' => $note ?: null, 'resolver' => $resolvedByUserId, 'id' => $id]);
    }

    public static function reject(int $id, int $resolvedByUserId, ?string $note): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE licenciado_email_requests SET status = 'recusado', admin_note = :note,
                resolved_by_user_id = :resolver, resolved_at = NOW() WHERE id = :id"
        );
        $stmt->execute(['note' => $note ?: null, 'resolver' => $resolvedByUserId, 'id' => $id]);
    }
}
