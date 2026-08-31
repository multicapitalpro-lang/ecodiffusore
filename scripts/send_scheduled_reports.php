<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Config;
use App\Core\FinancialReports;
use App\Core\Mailer;
use App\Models\ReportSchedule;

Config::load();

function dateRangeForFrequency(string $frequency): array
{
    $today = date('Y-m-d');
    return match ($frequency) {
        'diario' => [$today, $today],
        'semanal' => [date('Y-m-d', strtotime('-6 days')), $today],
        'mensal' => [date('Y-m-01'), $today],
        default => [$today, $today],
    };
}

function renderReportHtml(string $title, array $report): string
{
    $html = '<h2 style="font-family:sans-serif;color:#0d1b3d;">' . htmlspecialchars($title) . '</h2>';
    $html .= '<table style="border-collapse:collapse;font-family:sans-serif;font-size:13px;width:100%;">';
    $html .= '<tr>';
    foreach ($report['columns'] as $col) {
        $html .= '<th style="background:#0d1b3d;color:#fff;padding:8px;text-align:left;">' . htmlspecialchars($col) . '</th>';
    }
    $html .= '</tr>';
    foreach ($report['rows'] as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td style="padding:6px 8px;border-bottom:1px solid #e3e7ee;">' . htmlspecialchars((string) $cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
}

$due = ReportSchedule::activeDue();
$sent = 0;

foreach ($due as $schedule) {
    [$from, $to] = dateRangeForFrequency($schedule['frequency']);
    $report = FinancialReports::generate($schedule['report_type'], $from, $to);
    $title = FinancialReports::title($schedule['report_type']);

    $ok = Mailer::send(
        $schedule['recipient_email'],
        'Relatório: ' . $title . ' — Ecodiffusore Brasil',
        '<p>Olá, ' . htmlspecialchars($schedule['recipient_name']) . '!</p>'
        . '<p>Segue o relatório <strong>' . htmlspecialchars($title) . '</strong> referente ao período de '
        . date('d/m/Y', strtotime($from)) . ' a ' . date('d/m/Y', strtotime($to)) . '.</p>'
        . renderReportHtml($title, $report)
    );

    if ($ok) {
        ReportSchedule::touchSent((int) $schedule['id']);
        $sent++;
    }
}

echo "Relatorios enviados: {$sent} de " . count($due) . " devidos.\n";
