<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DateRange;
use App\Core\FinancialReports;
use App\Core\Pdf;
use App\Core\Roles;
use App\Core\Router;
use App\Core\View;
use App\Models\ReportSchedule;
use App\Models\User;

class ReportController
{
    private const ALLOWED_ROLES = Roles::MANAGEMENT;

    /** Fiscal/Antecipacoes sao dado nacional sensivel (nao da regiao de um Licenciado) -- so
     *  admin/gerente veem esse grupo. Gerente e' um papel de suporte nacional sem pool proprio
     *  (Roles::NATIONAL_SUPPORT), entao NAO teria acesso nenhum a Relatorios pelo gate normal
     *  (Roles::MANAGEMENT); index/show/pdf abrem uma excecao so pra esse grupo, restringindo
     *  gerente a EXATAMENTE esses dois tipos -- nunca aos relatorios regionais/de pool
     *  (Caixas, Contas, Comissoes) que ficam com Gestor/Licenciado/Admin de sempre. */
    private const NATIONAL_ONLY_TYPES = ['impostos', 'antecipacoes'];
    private const NATIONAL_ONLY_ROLES = ['admin', 'gerente'];
    private const NATIONAL_ONLY_GROUP = 'Fiscal e Antecipações';

    public function index(): void
    {
        Auth::requireRole([...self::ALLOWED_ROLES, ...self::NATIONAL_ONLY_ROLES]);
        $user = Auth::user();

        $catalog = FinancialReports::catalog();
        if (!in_array($user['role_slug'], self::NATIONAL_ONLY_ROLES, true)) {
            unset($catalog[self::NATIONAL_ONLY_GROUP]);
        } elseif (!in_array($user['role_slug'], self::ALLOWED_ROLES, true)) {
            // gerente (fora de Roles::MANAGEMENT): so enxerga o grupo nacional, resto e regional/pool
            $catalog = array_intersect_key($catalog, [self::NATIONAL_ONLY_GROUP => true]);
        }

        View::render('painel/reports/index', [
            'user' => $user,
            'catalog' => $catalog,
        ]);
    }

    public function show(string $type): void
    {
        Auth::requireRole([...self::ALLOWED_ROLES, ...self::NATIONAL_ONLY_ROLES]);
        $this->assertTypeAllowed($type, Auth::user());

        [$from, $to] = DateRange::fromRequest();

        View::render('painel/reports/show', [
            'user' => Auth::user(),
            'type' => $type,
            'title' => FinancialReports::title($type),
            'from' => $from,
            'to' => $to,
            'report' => FinancialReports::generate($type, $from, $to),
        ]);
    }

    public function pdf(string $type): void
    {
        Auth::requireRole([...self::ALLOWED_ROLES, ...self::NATIONAL_ONLY_ROLES]);
        $this->assertTypeAllowed($type, Auth::user());

        [$from, $to] = DateRange::fromRequest();
        $title = FinancialReports::title($type);
        $report = FinancialReports::generate($type, $from, $to);

        ob_start();
        View::render('painel/reports/pdf', compact('title', 'from', 'to', 'report'), null);
        $html = ob_get_clean();

        $filename = 'relatorio-' . $type . '-' . date('Y-m-d') . '.pdf';
        Pdf::download($html, $filename);
    }

    private function assertTypeAllowed(string $type, array $user): void
    {
        $isNationalType = in_array($type, self::NATIONAL_ONLY_TYPES, true);
        $isNationalRole = in_array($user['role_slug'], self::NATIONAL_ONLY_ROLES, true);

        // Dado nacional sensivel, so admin/gerente.
        if ($isNationalType && !$isNationalRole) {
            $this->deny();
        }
        // Gerente fica restrito aos relatorios nacionais -- nao acessa Caixas/Contas/Comissoes
        // (regional/pool) so por ter ganhado essa excecao de acesso a Relatorios.
        if ($user['role_slug'] === 'gerente' && !$isNationalType) {
            $this->deny();
        }
    }

    private function deny(): void
    {
        http_response_code(403);
        require BASE_PATH . '/app/Views/errors/403.php';
        exit;
    }

    public function schedules(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        View::render('painel/reports/schedules', [
            'user' => Auth::user(),
            'schedules' => ReportSchedule::all(),
            'catalog' => FinancialReports::catalog(),
            'recipients' => User::all(),
            'errors' => [],
        ]);
    }

    public function storeSchedule(): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/relatorios/agendamentos?erro=1');
        }

        if (empty($_POST['report_type']) || empty($_POST['recipient_user_id'])) {
            Router::redirect('/painel/financeiro/relatorios/agendamentos?erro=1');
        }

        ReportSchedule::create([
            'report_type' => $_POST['report_type'],
            'recipient_user_id' => (int) $_POST['recipient_user_id'],
            'frequency' => $_POST['frequency'] ?? 'mensal',
            'created_by' => Auth::user()['id'],
        ]);

        Router::redirect('/painel/financeiro/relatorios/agendamentos?sucesso=1');
    }

    public function deleteSchedule(string $id): void
    {
        Auth::requireRole(self::ALLOWED_ROLES);

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Router::redirect('/painel/financeiro/relatorios/agendamentos');
        }

        ReportSchedule::delete((int) $id);

        Router::redirect('/painel/financeiro/relatorios/agendamentos?sucesso=1');
    }
}
