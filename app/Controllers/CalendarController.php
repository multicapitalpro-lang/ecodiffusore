<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Notifier;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\SubscriptionGate;
use App\Core\View;
use App\Models\CalendarEvent;
use App\Models\User;

/** Calendario compartilhado da equipe (Fase 92) -- ferramenta premium, atras do paywall da
 *  assinatura (mesmo tratamento do WhatsApp integrado -- bloqueio total, nao "ver mascarado"). */
class CalendarController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'calendario');

        $this->sendDueReminders();

        $view = $_GET['view'] ?? 'minha';
        $from = $_GET['from'] ?? date('Y-m-d');
        $to = date('Y-m-d', strtotime($from . ' +6 days'));

        $userIds = $view === 'equipe' ? $this->teamIds($user) : [(int) $user['id']];
        $events = CalendarEvent::forRange($userIds, $from . ' 00:00:00', $to . ' 23:59:59');

        View::render('painel/calendar/index', [
            'user' => $user,
            'events' => $events,
            'view' => $view,
            'from' => $from,
            'canSeeTeam' => count($this->teamIds($user)) > 1,
            'participantOptions' => $this->participantOptions($user),
            'typeLabels' => CalendarEvent::TYPE_LABELS,
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'calendario');
        $isFragment = isset($_GET['fragment']);

        View::render('painel/calendar/form', [
            'user' => $user,
            'editing' => null,
            'participantOptions' => $this->participantOptions($user),
            'typeLabels' => CalendarEvent::TYPE_LABELS,
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function store(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'calendario');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => ['_geral' => 'Sessão expirada, recarregue a página.']]);
            }
            Router::redirect('/painel/calendario?erro=1');
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            if (Response::isAjax()) {
                Response::json(['ok' => false, 'errors' => $errors]);
            }
            Router::redirect('/painel/calendario?erro=1');
        }

        $participantIds = $this->allowedParticipantIds($user, $_POST['participants'] ?? []);
        $id = CalendarEvent::create((int) $user['id'], $_POST, $participantIds);

        if (Response::isAjax()) {
            Response::json(['ok' => true, 'redirect' => '/painel/calendario?sucesso=1']);
        }
        Router::redirect('/painel/calendario?sucesso=1');
    }

    public function edit(string $id): void
    {
        $event = $this->authorizeEvent((int) $id);
        $isFragment = isset($_GET['fragment']);

        View::render('painel/calendar/form', [
            'user' => Auth::user(),
            'editing' => $event,
            'participantIds' => CalendarEvent::participantIdsFor((int) $id),
            'participantOptions' => $this->participantOptions(Auth::user()),
            'typeLabels' => CalendarEvent::TYPE_LABELS,
            'errors' => [],
            'isModal' => $isFragment,
        ], $isFragment ? null : 'painel');
    }

    public function update(string $id): void
    {
        $event = $this->authorizeEvent((int) $id);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect("/painel/calendario/{$id}/editar?erro=1");
        }

        $errors = $this->validate($_POST);
        if ($errors) {
            Router::redirect("/painel/calendario/{$id}/editar?erro=1");
        }

        CalendarEvent::update($id, array_merge($_POST, ['owner_id' => $event['owner_id']]));
        $participantIds = $this->allowedParticipantIds($user, $_POST['participants'] ?? []);
        CalendarEvent::setParticipants($id, $participantIds);

        Router::redirect('/painel/calendario?sucesso=1');
    }

    public function markStatus(string $id): void
    {
        $this->authorizeEvent((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/calendario?erro=1');
        }

        CalendarEvent::markStatus($id, $_POST['status'] ?? '');
        Router::redirect('/painel/calendario?sucesso=1');
    }

    public function destroy(string $id): void
    {
        $this->authorizeEvent((int) $id);
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/calendario?erro=1');
        }

        CalendarEvent::delete($id);
        Router::redirect('/painel/calendario?sucesso=2');
    }

    private function authorizeEvent(int $id): array
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        SubscriptionGate::requireAccess($user, 'calendario');

        $event = CalendarEvent::find($id);
        if (!$event || (!CalendarEvent::isOwnerOrParticipant($event, (int) $user['id']) && $user['role_slug'] !== 'admin')) {
            Router::redirect('/painel/calendario?erro=2');
        }

        return $event;
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

    private function validate(array $input): array
    {
        $errors = [];
        if (trim($input['title'] ?? '') === '') {
            $errors['title'] = 'Informe o título.';
        }
        if (empty($input['starts_at'])) {
            $errors['starts_at'] = 'Informe data/hora de início.';
        }
        return $errors;
    }
}
