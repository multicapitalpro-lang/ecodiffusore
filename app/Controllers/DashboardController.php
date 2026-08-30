<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Router;
use App\Core\View;
use App\Models\Lead;

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::user();

        if (!empty($user['must_change_password'])) {
            Router::redirect('/painel/trocar-senha');
        }

        $data = ['user' => $user];

        if ($user['role_slug'] === 'admin') {
            $data['leadCount'] = Lead::count();
        }

        if ($user['role_slug'] === 'licenciado') {
            $data['myLeads'] = Lead::forUser((int) $user['id']);
        }

        View::render('painel/dashboard', $data);
    }
}
