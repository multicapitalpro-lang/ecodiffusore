<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Models\Lead;
use App\Models\User;

class LeadController
{
    private const STATUSES = ['novo', 'contatado', 'convertido', 'descartado'];

    public function index(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();
        $role = $user['role_slug'];

        if ($role === 'admin') {
            $leads = Lead::all();
        } elseif ($role === 'licenciado') {
            $leads = Lead::forScope(User::downlineIds((int) $user['id']), false);
        } else {
            $leads = Lead::forScope(User::downlineIds((int) $user['id']), true);
        }

        $columns = [];
        foreach (self::STATUSES as $status) {
            $columns[$status] = array_values(array_filter($leads, fn ($l) => $l['status'] === $status));
        }

        View::render('painel/leads/index', [
            'user' => $user,
            'columns' => $columns,
            'canAssign' => $role !== 'licenciado',
            'sellers' => $role !== 'licenciado' ? User::allByRole('licenciado') : [],
        ]);
    }

    public function updateStatus(string $id): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            if (Response::isAjax()) {
                Response::json(['ok' => false]);
            }
            Router::redirect('/painel/leads');
        }

        $status = $_POST['status'] ?? '';
        if (in_array($status, self::STATUSES, true)) {
            Lead::updateStatus((int) $id, $status);
        }

        if (Response::isAjax()) {
            Response::json(['ok' => true]);
        }
        Router::redirect('/painel/leads');
    }

    public function assign(string $id): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor']);

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
}
