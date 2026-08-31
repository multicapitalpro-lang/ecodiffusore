<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\AuditLog;

class AuditController
{
    public function index(): void
    {
        Auth::requireRole(['admin']);

        View::render('painel/audit/index', [
            'user' => Auth::user(),
            'logs' => AuditLog::recent(200),
        ]);
    }
}
