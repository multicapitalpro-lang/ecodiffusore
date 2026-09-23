<?php

namespace App\Controllers\Api;

use App\Core\ApiAuth;
use App\Core\ApiResponse;
use App\Core\Notifier;
use App\Core\Roles;
use App\Core\SubscriptionGate;
use App\Models\CalendarEvent;
use App\Models\User;

/** Fase 103: Calendario compartilhado pro app -- mesma logica de
 *  App\Controllers\CalendarController, atras do mesmo paywall ('calendario'). */
class CalendarController
{
    private function requireGate(): array
    {
        $user = ApiAuth::requireUser();
        if (!in_array($user['role_slug'], Roles::STAFF, true)) {
            ApiResponse::error('Papel sem acesso a essa função.', 403);
        }
        if (!SubscriptionGate::hasAccess($user)) {
            ApiResponse::error('Assinatura do Licenciado inativa -- regularize no painel web.', 402);
        }
        return $user;
    }

    private function serialize(array $e): array
    {
        return [
            'id' => (int) $e['id'],
            'title' => $e['title'],
            'description' => $e['description'],
            'event_type' => $e['event_type'],
            'event_type_label' => CalendarEvent::TYPE_LABELS[$e['event_type']] ?? $e['event_type'],
            'starts_at' => $e['starts_at'],
            'ends_at' => $e['ends_at'],
            'location' => $e['location'],
            'status' => $e['status'],
            'owner_id' => (int) $e['owner_id'],
            'owner_name' => $e['owner_name'],
            'lead_name' => $e['lead_name'] ?? null,
            'client_name' => $e['client_name'] ?? null,
            'participants' => CalendarEvent::participantsFor((int) $e['id']),
        ];
    }

    private function sendDueReminders(): void
    {
        foreach (CalendarEvent::dueForReminder() as $event) {
            Notifier::calendarEventReminder($event);
            CalendarEvent::markReminderSent((int) $event['id']);
        }
    }

    /** Licenciado (rede toda), Gestor/Vendedor (mesma rede do Licenciado deles), Admin (todo mundo). */
    private function teamIds(array $user): array
    {
        if ($user['role_slug'] === 'admin') {
            return array_map(fn ($u) => (int) $u['id'], User::all());
        }
        $licenciadoId = $user['role_slug'] === 'licenciado' ? (int) $user['id'] : (User::licenciadoFor((int) $user['id'])['id'] ?? null);
        return $licenciadoId ? User::downlineIds((int) $licenciadoId) : [(int) $user['id']];
    }

    private function participantOptions(array $user): array
    {
        $ids = $this->teamIds($user);
        return array_values(array_filter(User::all(), fn ($u) => in_array((int) $u['id'], $ids, true)));
    }

    private function allowedParticipantIds(array $user, array $requested): array
    {
        $allowed = array_map(fn ($u) => (int) $u['id'], $this->participantOptions($user));
        return array_values(array_intersect(array_map('intval', $requested), $allowed));
    }

    public function index(): void
    {
        $user = $this->requireGate();
        $this->sendDueReminders();

        $view = $_GET['view'] ?? 'minha';
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = date('Y-m-d', strtotime($from . ' +6 days'));

        $userIds = $view === 'equipe' ? $this->teamIds($user) : [(int) $user['id']];
        $events = CalendarEvent::forRange($userIds, $from . ' 00:00:00', $to . ' 23:59:59');

        ApiResponse::json([
            'events' => array_map(fn ($e) => $this->serialize($e), $events),
            'can_see_team' => count($this->teamIds($user)) > 1,
            'participant_options' => array_map(fn ($u) => ['id' => (int) $u['id'], 'name' => $u['name']], $this->participantOptions($user)),
        ]);
    }

    public function store(): void
    {
        $user = $this->requireGate();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        if (trim($body['title'] ?? '') === '' || empty($body['starts_at'])) {
            ApiResponse::error('Informe título e data/hora de início.', 422);
        }

        $participantIds = $this->allowedParticipantIds($user, $body['participants'] ?? []);
        $id = CalendarEvent::create((int) $user['id'], $body, $participantIds);

        ApiResponse::json(['ok' => true, 'id' => $id], 201);
    }

    private function authorizeEvent(array $user, int $id): array
    {
        $event = CalendarEvent::find($id);
        if (!$event || (!CalendarEvent::isOwnerOrParticipant($event, (int) $user['id']) && $user['role_slug'] !== 'admin')) {
            ApiResponse::error('Evento não encontrado.', 404);
        }
        return $event;
    }

    public function show(string $id): void
    {
        $user = $this->requireGate();
        $event = $this->authorizeEvent($user, (int) $id);
        ApiResponse::json(['event' => $this->serialize($event)]);
    }

    public function update(string $id): void
    {
        $user = $this->requireGate();
        $event = $this->authorizeEvent($user, (int) $id);
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        if (trim($body['title'] ?? '') === '' || empty($body['starts_at'])) {
            ApiResponse::error('Informe título e data/hora de início.', 422);
        }

        CalendarEvent::update((int) $id, array_merge($body, ['owner_id' => $event['owner_id']]));
        $participantIds = $this->allowedParticipantIds($user, $body['participants'] ?? []);
        CalendarEvent::setParticipants((int) $id, $participantIds);

        ApiResponse::json(['ok' => true]);
    }

    public function markStatus(string $id): void
    {
        $user = $this->requireGate();
        $this->authorizeEvent($user, (int) $id);
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        CalendarEvent::markStatus((int) $id, $body['status'] ?? '');
        ApiResponse::json(['ok' => true]);
    }

    public function destroy(string $id): void
    {
        $user = $this->requireGate();
        $this->authorizeEvent($user, (int) $id);
        CalendarEvent::delete((int) $id);
        ApiResponse::json(['ok' => true]);
    }
}
