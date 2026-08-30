<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public static function allByRole(string $roleSlug): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.slug = :slug AND u.status = 'active' ORDER BY u.name"
        );
        $stmt->execute(['slug' => $roleSlug]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $emailVerified = array_key_exists('email_verified', $data) ? !empty($data['email_verified']) : true;

        $stmt = Database::connection()->prepare(
            'INSERT INTO users (role_id, name, email, whatsapp, password_hash, status, commission_pct, must_change_password, email_verified_at)
             VALUES (:role_id, :name, :email, :whatsapp, :password_hash, :status, :commission_pct, :must_change_password, :email_verified_at)'
        );
        $stmt->execute([
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => $data['status'] ?? 'active',
            'commission_pct' => $data['commission_pct'] ?? null,
            'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
            'email_verified_at' => $emailVerified ? date('Y-m-d H:i:s') : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function setVerificationCode(int $id, string $code): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET verification_code_hash = :hash, verification_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
             WHERE id = :id'
        );
        $stmt->execute(['hash' => password_hash($code, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function verifyEmailCode(int $id, string $code): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT verification_code_hash, verification_expires_at FROM users
             WHERE id = :id AND email_verified_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row || !$row['verification_code_hash'] || !$row['verification_expires_at']) {
            return false;
        }
        if ($row['verification_expires_at'] < date('Y-m-d H:i:s')) {
            return false;
        }
        if (!password_verify($code, $row['verification_code_hash'])) {
            return false;
        }

        $update = Database::connection()->prepare(
            'UPDATE users SET email_verified_at = NOW(), verification_code_hash = NULL, verification_expires_at = NULL
             WHERE id = :id'
        );
        $update->execute(['id' => $id]);

        return true;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET role_id = :role_id, name = :name, email = :email,
                whatsapp = :whatsapp, status = :status, commission_pct = :commission_pct WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?: null,
            'commission_pct' => $data['commission_pct'] ?? null,
            'status' => $data['status'],
        ]);
    }

    public static function resetPassword(int $id, string $newPassword): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    public static function changePassword(int $id, string $newPassword): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id'
        );
        $stmt->execute([
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $exceptId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public static function touchLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
