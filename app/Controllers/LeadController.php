<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;

class LeadController
{
    public function index(): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();

        $leads = $this->scopedLeads($user);
        $stages = LeadStage::all();

        $columns = [];
        foreach ($stages as $stage) {
            $columns[$stage['slug']] = array_values(array_filter($leads, fn ($l) => $l['status'] === $stage['slug']));
        }

        $isViewOnly = in_array($user['role_slug'], array_merge([Roles::SELLER], Roles::NATIONAL_SUPPORT), true);

        View::render('painel/leads/index', [
            'user' => $user,
            'stages' => $stages,
            'columns' => $columns,
            'canAssign' => !$isViewOnly,
            'isViewOnly' => $isViewOnly,
            'sellers' => !$isViewOnly ? User::allByRole('vendedor') : [],
        ]);
    }

    public function updateStatus(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        if (in_array(Auth::user()['role_slug'], Roles::NATIONAL_SUPPORT, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            Router::redirect('/painel/leads');
        }

        $status = $_POST['status'] ?? '';
        if (in_array($status, LeadStage::allSlugs(), true)) {
            Lead::updateStatus((int) $id, $status);
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true]);
        }
        Router::redirect('/painel/leads');
    }

    public function assign(string $id): void
    {
        Auth::requireRole(Roles::MANAGEMENT);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            Router::redirect('/painel/leads');
        }

        $sellerId = !empty($_POST['seller_id']) ? (int) $_POST['seller_id'] : null;
        Lead::assignTo((int) $id, $sellerId);

        if (Response::isAjax()) {
            Response::json(['ok' => true]);
        }
        Router::redirect('/painel/leads');
    }

    /** Exclusao liberada pra qualquer STAFF (inclusive Gerente/Supervisor, que sao view-only pro
     * resto do CRM) -- pedido explicito, e diferente de editar dados comerciais, e mais
     * "faxina" de cadastro duplicado/invalido. */
    public function destroy(string $id): void
    {
        Auth::requireRole(Roles::STAFF);
        $user = Auth::user();
        $id = (int) $id;

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/leads?erro=csrf');
        }

        $allowedIds = array_column($this->scopedLeads($user), 'id');
        if (!in_array($id, $allowedIds, true)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        Lead::delete($id);
        Router::redirect('/painel/leads?sucesso=1');
    }

    /** Nova coluna do kanban -- compartilhada com toda a equipe (o mesmo lead e visto por gente
     * diferente dependendo do escopo, entao o status precisa ser uma unica fonte de verdade). */
    public function addStage(): void
    {
        Auth::requireRole(Roles::STAFF);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/leads?erro=csrf');
        }

        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            LeadStage::create($name);
        }

        Router::redirect('/painel/leads?sucesso=2');
    }

    private function scopedLeads(array $user): array
    {
        $role = $user['role_slug'];

        if ($role === 'admin') {
            return Lead::all();
        }
        if ($role === Roles::SELLER) {
            return Lead::forScope(User::downlineIds((int) $user['id']), false);
        }
        if ($role === 'supervisor') {
            return Lead::forScope(User::supervisedIds((int) $user['id']), false);
        }
        if ($role === 'gerente') {
            return Lead::forScope(User::nationalIds((int) $user['id']), false);
        }
        return Lead::forScope(User::downlineIds((int) $user['id']), true);
    }
}
