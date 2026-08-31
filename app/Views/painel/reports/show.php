<?php
use App\Core\View;
?>
<div class="page-header">
    <h1><?= View::e($title) ?></h1>
    <a href="/painel/financeiro/relatorios" class="btn btn-outline">← Todos os relatórios</a>
</div>

<form method="get" class="filter-bar">
    <label>Período: <input type="date" name="from" value="<?= View::e($from) ?>"></label>
    <label>até <input type="date" name="to" value="<?= View::e($to) ?>"></label>
    <button type="submit" class="btn btn-outline">Visualizar</button>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><?php foreach ($report['columns'] as $col): ?><th><?= View::e($col) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
            <?php foreach ($report['rows'] as $row): ?>
                <tr><?php foreach ($row as $cell): ?><td><?= View::e((string) $cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            <?php if (!$report['rows']): ?>
                <tr><td colspan="<?= count($report['columns']) ?>">Nenhum dado no período selecionado.</td></tr>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($report['totals'])): ?>
            <tfoot>
                <?php foreach ($report['totals'] as $label => $value): ?>
                    <tr><td colspan="<?= count($report['columns']) - 1 ?>" style="text-align:right"><strong><?= View::e($label) ?></strong></td><td><strong><?= View::e($value) ?></strong></td></tr>
                <?php endforeach; ?>
            </tfoot>
        <?php endif; ?>
    </table>
</div>
