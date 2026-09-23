<?php

namespace App\Models;

use App\Core\Database;

/** Fase 95: Indicacoes Premiadas. Um indicador (Licenciado/Gestor/Vendedor) registra alguem que
 *  poderia virar Licenciado/Gestor/Vendedor -- o cadastro de verdade continua acontecendo pela
 *  tela normal de Usuarios (sem mudar esse fluxo), o indicador so' VINCULA a indicacao ao cadastro
 *  depois que ele existir. Quando esse cadastro vira ativo, o indicador ganha o direito a uma
 *  premiacao (registrada/paga manualmente, mesmo espirito de Goal::markRewardPaid). */
class Referral
{
    public const STATUS_LABELS = [
        'indicado' => 'Indicado',
        'em_contato' => 'Em contato',
        'cadastrado' => 'Cadastrado, aguardando ativação',
        'ativo' => 'Ativo — prêmio liberado',
        'descartado' => 'Descartado',
    ];

    public const TARGET_ROLE_LABELS = ['licenciado' => 'Licenciado', 'gestor' => 'Gestor', 'vendedor' => 'Vendedor'];

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, u.name AS referrer_name, c.name AS converted_user_name, c.status AS converted_user_status,
                    c.licenciado_onboarding_status AS converted_onboarding_status
             FROM referrals r
             JOIN users u ON u.id = r.referrer_id
             LEFT JOIN users c ON c.id = r.converted_user_id
             WHERE r.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forReferrer(int $referrerId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, c.name AS converted_user_name FROM referrals r
             LEFT JOIN users c ON c.id = r.converted_user_id
             WHERE r.referrer_id = :rid ORDER BY r.created_at DESC'
        );
        $stmt->execute(['rid' => $referrerId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO referrals (referrer_id, name, whatsapp, target_role, notes)
             VALUES (:referrer_id, :name, :whatsapp, :target_role, :notes)'
        );
        $stmt->execute([
            'referrer_id' => $data['referrer_id'],
            'name' => $data['name'],
            'whatsapp' => $data['whatsapp'] ?: null,
            'target_role' => in_array($data['target_role'] ?? '', ['licenciado', 'gestor', 'vendedor'], true) ? $data['target_role'] : 'vendedor',
            'notes' => $data['notes'] ?: null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** Candidatos pra vincular essa indicacao: gente do papel certo, ja cadastrada na rede do
     *  indicador, que ainda nao esta vinculada a NENHUMA outra indicacao (evita vincular a mesma
     *  pessoa duas vezes) nem e' o proprio indicador. */
    public static function linkableUsers(array $referral, array $downlineIds): array
    {
        if (!$downlineIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($downlineIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT u.id, u.name FROM users u JOIN roles ro ON ro.id = u.role_id
             WHERE ro.slug = ? AND u.id IN ({$placeholders})
               AND u.id NOT IN (SELECT converted_user_id FROM referrals WHERE converted_user_id IS NOT NULL)
             ORDER BY u.name"
        );
        $stmt->execute(array_merge([$referral['target_role']], $downlineIds));
        return $stmt->fetchAll();
    }

    public static function markContacted(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE referrals SET status = 'em_contato' WHERE id = :id AND status = 'indicado'");
        $stmt->execute(['id' => $id]);
    }

    public static function linkToUser(int $id, int $userId): void
    {
        $stmt = Database::connection()->prepare("UPDATE referrals SET converted_user_id = :uid, status = 'cadastrado' WHERE id = :id");
        $stmt->execute(['uid' => $userId, 'id' => $id]);
    }

    public static function markDiscarded(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE referrals SET status = 'descartado' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public static function markActive(int $id): void
    {
        $stmt = Database::connection()->prepare("UPDATE referrals SET status = 'ativo' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /** Indicacoes ja vinculadas a um cadastro, esperando esse cadastro virar ativo -- usado so
     *  pela rotina lazy (App\Core\ReferralActivationCheck). */
    public static function allPendingActivation(): array
    {
        $stmt = Database::connection()->query(
            "SELECT r.*, c.status AS converted_user_status, c.role_id AS converted_role_id,
                    c.licenciado_onboarding_status AS converted_onboarding_status
             FROM referrals r JOIN users c ON c.id = r.converted_user_id
             WHERE r.status = 'cadastrado'"
        );
        return $stmt->fetchAll();
    }

    public static function setReward(int $id, ?string $description, ?float $amount): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE referrals SET reward_description = :description, reward_amount = :amount WHERE id = :id'
        );
        $stmt->execute(['description' => $description ?: null, 'amount' => $amount ?: null, 'id' => $id]);
    }

    public static function markRewardPaid(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE referrals SET reward_paid = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
