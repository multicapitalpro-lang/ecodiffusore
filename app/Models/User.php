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

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (role_id, name, email, whatsapp, password_hash, status, must_change_password)
             VALUES (:role_id, :name, :email, :whatsapp, :password_hash, :status, :must_change_password)'
        );
        $stmt->execute([
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?: null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => $data['status'] ?? 'active',
            'must_change_password' => !empty($data['must_change_password']) ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET role_id = :role_id, name = :name, email = :email,
                whatsapp = :whatsapp, status = :status WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?: null,
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
