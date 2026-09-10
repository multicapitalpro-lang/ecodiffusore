<?php

namespace App\Models;

use App\Core\Database;

/** Instancia Evolution API de um Licenciado/Gestor/Vendedor (Fase 33) -- 1 usuario = no maximo 1
 *  instancia (UNIQUE em user_id), nome gerado a partir do id do usuario pra nunca colidir. */
class WhatsAppInstance
{
    public static function forUser(int $userId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_instances WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_instances WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByName(string $instanceName): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM whatsapp_instances WHERE instance_name = :name');
        $stmt->execute(['name' => $instanceName]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Nome estavel e sem colisao -- "u{id}" (o mesmo usuario sempre gera o mesmo nome, entao
     *  reconectar depois de uma queda reaproveita a mesma instancia em vez de criar outra). */
    public static function nameFor(int $userId): string
    {
        return 'u' . $userId;
    }

    public static function create(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_instances (user_id, instance_name, status) VALUES (:uid, :name, "connecting")'
        );
        $stmt->execute(['uid' => $userId, 'name' => self::nameFor($userId)]);
        return self::find((int) Database::connection()->lastInsertId());
    }

    public static function updateStatus(int $id, string $status, ?string $phoneNumber = null, ?string $profileName = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE whatsapp_instances SET status = :status,
                phone_number = COALESCE(:phone, phone_number),
                profile_name = COALESCE(:profile, profile_name),
                connected_at = CASE WHEN :status2 = "open" AND connected_at IS NULL THEN NOW() ELSE connected_at END
             WHERE id = :id'
        );
        $stmt->execute(['status' => $status, 'status2' => $status, 'phone' => $phoneNumber, 'profile' => $profileName, 'id' => $id]);
    }

    public static function touchSync(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE whatsapp_instances SET last_synced_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM whatsapp_instances WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
