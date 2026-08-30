<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Lead;

class LeadController
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'gerente', 'supervisor', 'licenciado']);
        $user = Auth::user();

        $leads = $user['role_slug'] === 'licenciado'
            ? Lead::forUser((int) $user['id'])
            : Lead::all();

        View::render('painel/leads/index', [
            'user' => $user,
            'leads' => $leads,
        ]);
    }
}
