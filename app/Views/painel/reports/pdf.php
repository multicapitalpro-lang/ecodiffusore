<?php
/** @var array $report */
/** @var string $title */
/** @var string $from */
/** @var string $to */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    .report-letterhead { border-bottom: 2px solid #1a7a4c; padding-bottom: 10px; margin-bottom: 16px; }
    .report-letterhead-brand { font-size: 13px; font-weight: bold; color: #1a7a4c; letter-spacing: .5px; text-transform: uppercase; }
    .report-letterhead-title { margin: 4px 0; font-size: 18px; }
    .report-letterhead-meta { display: flex; justify-content: space-between; color: #666; font-size: 10px; }
    table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.data-table th, table.data-table td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; white-space: nowrap; }
    table.data-table th { background: #f0f4f2; font-weight: bold; }
    table.data-table td:not(:first-child), table.data-table th:not(:first-child) { text-align: right; }
    tr.report-row-header td { background: #f7f7f7; font-weight: bold; }
    tr.report-row-bold td { font-weight: bold; }
    .report-split { width: 100%; display: table; }
    .report-split-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
    .report-split-col h3 { font-size: 12px; margin: 0 0 6px; }
    .cards-grid { margin-bottom: 14px; }
    .dash-card { display: inline-block; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; margin: 0 8px 8px 0; min-width: 130px; }
    .dash-card span { display: block; font-size: 9px; color: #666; text-transform: uppercase; }
    .dash-card strong { display: block; font-size: 13px; margin-top: 2px; }
</style>
</head>
<body>
<?php include __DIR__ . '/_report_body.php'; ?>
</body>
</html>
