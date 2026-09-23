<?php

namespace App\Models;

use App\Core\Database;

/**
 * Calendario compartilhado da equipe (Fase 92) -- reuniao/visita/instalacao/tarefa, visivel pro
 * dono + participantes convidados, e pro Licenciado/Gestor enxergar a agenda de toda a rede dele
 * (nao so os proprios eventos). Ferramenta premium, atras do SubscriptionGate.
 */
class CalendarEvent
{
    public const TYPE_LABELS = [
        'reuniao' => 'Reunião',
        'visita' => 'Visita',
        'instalacao' => 'Instalação',
        'tarefa' => 'Tarefa',
        'outro' => 'Outro',
    ];

    public static function create(int $ownerId, array $data, array $participantIds = []): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO calendar_events (owner_id, title, description, event_type, starts_at, ends_at, location, lead_id, client_id, order_id)
             VALUES (:owner_id, :title, :description, :event_type, :starts_at, :ends_at, :location, :lead_id, :client_id, :order_id)'
        );
        $stmt->execute(self::params($ownerId, $data));
        $id = (int) $db->lastInsertId();

        self::setParticipants($id, $participantIds);

        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE calendar_events SET title = :title, description = :description, event_type = :event_type,
                starts_at = :starts_at, ends_at = :ends_at, location = :location,
                lead_id = :lead_id, client_id = :client_id, order_id = :order_id
             WHERE id = :id'
        );
        $params = self::params((int) $data['owner_id'], $data);
        unset($params['owner_id']);
        $params['id'] = $id;
        $stmt->execute($params);
    }

    private static function params(int $ownerId, array $data): array
    {
        return [
            'owner_id' => $ownerId,
            'title' => trim($data['title']),
            'description' => trim($data['description'] ?? '') ?: null,
            'event_type' => in_array($data['event_type'] ?? '', array_keys(self::TYPE_LABELS), true) ? $data['event_type'] : 'outro',
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?: null,
            'location' => trim($data['location'] ?? '') ?: null,
            'lead_id' => $data['lead_id'] ?: null,
            'client_id' => $data['client_id'] ?: null,
            'order_id' => $data['order_id'] ?: null,
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ce.*, u.name AS owner_name, l.name AS lead_name, c.name AS client_name
             FROM calendar_events ce
             LEFT JOIN users u ON u.id = ce.owner_id
             LEFT JOIN leads l ON l.id = ce.lead_id
             LEFT JOIN clients c ON c.id = ce.client_id
             WHERE ce.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM calendar_events WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function markStatus(int $id, string $status): void
    {
        if (!in_array($status, ['agendado', 'concluido', 'cancelado'], true)) {
            return;
        }
        $stmt = Database::connection()->prepare('UPDATE calendar_events SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function setParticipants(int $eventId, array $userIds): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM calendar_event_participants WHERE event_id = :id')->execute(['id' => $eventId]);

        $stmt = $db->prepare('INSERT IGNORE INTO calendar_event_participants (event_id, user_id) VALUES (:event_id, :user_id)');
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $stmt->execute(['event_id' => $eventId, 'user_id' => $userId]);
        }
    }

    public static function participantIdsFor(int $eventId): array
    {
        $stmt = Database::connection()->prepare('SELECT user_id FROM calendar_event_participants WHERE event_id = :id');
        $stmt->execute(['id' => $eventId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    public static function participantsFor(int $eventId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.name FROM calendar_event_participants p JOIN users u ON u.id = p.user_id WHERE p.event_id = :id ORDER BY u.name'
        );
        $stmt->execute(['id' => $eventId]);
        return $stmt->fetchAll();
    }

    /** Eventos onde a pessoa e' dona OU participante, dentro do periodo -- usado tanto pra "minha
     *  agenda" (userIds = [self]) quanto pra visao gerencial (userIds = downlineIds do
     *  Licenciado/Gestor). */
    public static function forRange(array $userIds, string $from, string $to): array
    {
        if (!$userIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT DISTINCT ce.*, u.name AS owner_name, l.name AS lead_name, c.name AS client_name
             FROM calendar_events ce
             LEFT JOIN users u ON u.id = ce.owner_id
             LEFT JOIN leads l ON l.id = ce.lead_id
             LEFT JOIN clients c ON c.id = ce.client_id
             LEFT JOIN calendar_event_participants p ON p.event_id = ce.id
             WHERE (ce.owner_id IN ({$placeholders}) OR p.user_id IN ({$placeholders}))
               AND ce.starts_at >= :from AND ce.starts_at <= :to
             ORDER BY ce.starts_at ASC"
        );
        $stmt->execute(array_merge($userIds, $userIds, ['from' => $from, 'to' => $to]));
        return $stmt->fetchAll();
    }

    /** True se a pessoa e' dona ou participante -- usado pra autorizar acesso ao evento
     *  individual (edicao/exclusao/mudanca de status). */
    public static function isOwnerOrParticipant(array $event, int $userId): bool
    {
        return (int) $event['owner_id'] === $userId || in_array($userId, self::participantIdsFor((int) $event['id']), true);
    }

    /** Eventos comecando nos proximos ~65min, ainda sem lembrete mandado -- lazy-check (sem cron
     *  nesse plano Hostinger, mesmo padrao de Lead::expireStaleAssignments/Order::
     *  flagStalePaymentPending), chamado a cada carga da tela de Calendario/Dashboard. */
    public static function dueForReminder(): array
    {
        $stmt = Database::connection()->query(
            "SELECT * FROM calendar_events
             WHERE status = 'agendado' AND reminder_sent_at IS NULL
               AND starts_at > NOW() AND starts_at <= DATE_ADD(NOW(), INTERVAL 65 MINUTE)"
        );
        return $stmt->fetchAll();
    }

    public static function markReminderSent(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE calendar_events SET reminder_sent_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
