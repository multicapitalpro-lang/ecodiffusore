<?php
use App\Core\View;
$qs = http_build_query(['from' => $from, 'to' => $to]);
?>
<div class="page-header">
    <h1><?= View::e($title) ?></h1>
    <div class="page-header-actions">
        <a href="/painel/financeiro/relatorios/<?= View::e($type) ?>/pdf?<?= $qs ?>" class="btn btn-primary" target="_blank">Baixar PDF</a>
        <a href="/painel/financeiro/relatorios" class="btn btn-outline">← Todos os relatórios</a>
    </div>
</div>

<form method="get" class="filter-bar">
    <label>Período: <input type="date" name="from" value="<?= View::e($from) ?>"></label>
    <label>até <input type="date" name="to" value="<?= View::e($to) ?>"></label>
    <button type="submit" class="btn btn-outline">Visualizar</button>
</form>

<?php include __DIR__ . '/_report_body.php'; ?>
