<?php

namespace App\Models;

use App\Core\Database;

class ReportSchedule
{
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT rs.*, u.name AS recipient_name, u.email AS recipient_email
             FROM report_schedules rs JOIN users u ON u.id = rs.recipient_user_id
             ORDER BY rs.created_at DESC'
        );
        return $stmt->fetchAll();
    }

    public static function activeDue(): array
    {
        $stmt = Database::connection()->query(
            "SELECT rs.*, u.name AS recipient_name, u.email AS recipient_email
             FROM report_schedules rs JOIN users u ON u.id = rs.recipient_user_id
             WHERE rs.active = 1"
        );
        $schedules = $stmt->fetchAll();

        return array_filter($schedules, function ($s) {
            if (!$s['last_sent_at']) {
                return true;
            }
            $elapsedDays = (strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($s['last_sent_at'])))) / 86400;
            return match ($s['frequency']) {
                'diario' => $elapsedDays >= 1,
                'semanal' => $elapsedDays >= 7,
                'mensal' => $elapsedDays >= 28,
                default => false,
            };
        });
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO report_schedules (report_type, recipient_user_id, frequency, created_by)
             VALUES (:report_type, :recipient_user_id, :frequency, :created_by)'
        );
        $stmt->execute([
            'report_type' => $data['report_type'],
            'recipient_user_id' => $data['recipient_user_id'],
            'frequency' => $data['frequency'],
            'created_by' => $data['created_by'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM report_schedules WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function touchSent(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE report_schedules SET last_sent_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
