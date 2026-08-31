<?php
use App\Core\View;
/** @var array $report */
/** @var string $title */
/** @var string $from */
/** @var string $to */
$periodLabel = date('d/m/Y', strtotime($from)) . ' a ' . date('d/m/Y', strtotime($to));
?>
<div class="report-sheet">
    <div class="report-letterhead">
        <div class="report-letterhead-brand">Ecodiffusore Brasil</div>
        <h2 class="report-letterhead-title"><?= View::e($title) ?></h2>
        <div class="report-letterhead-meta">
            <span><?= View::e($report['yearLabel'] ?? ('Período: ' . $periodLabel)) ?></span>
            <span>Emitido em <?= date('d/m/Y H:i') ?></span>
        </div>
    </div>

    <?php if (($report['kind'] ?? 'simple') === 'matrix'): ?>
        <div class="table-scroll">
            <table class="data-table report-matrix-table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <?php foreach ($report['periods'] as $p): ?><th><?= View::e($p) ?></th><?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr class="<?= $row['header'] ? 'report-row-header' : '' ?> <?= $row['bold'] ? 'report-row-bold' : '' ?>">
                            <td><?= View::e($row['label']) ?></td>
                            <?php foreach ($row['values'] as $v): ?>
                                <td><?= $v === null ? '' : 'R$ ' . number_format((float) $v, 2, ',', '.') ?></td>
                            <?php endforeach; ?>
                            <td><?= $row['total'] === null ? '' : 'R$ ' . number_format((float) $row['total'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif (($report['kind'] ?? '') === 'split'): ?>
        <div class="report-split">
            <div class="report-split-col">
                <h3><?= View::e($report['left']['title']) ?></h3>
                <table class="data-table">
                    <tbody>
                        <?php foreach ($report['left']['rows'] as $row): ?>
                            <tr><td><?= View::e($row[0]) ?></td><td><?= View::e($row[1]) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$report['left']['rows']): ?><tr><td colspan="2">Sem dados no período.</td></tr><?php endif; ?>
                    </tbody>
                    <tfoot><tr><td><strong>Total</strong></td><td><strong><?= View::e($report['left']['total']) ?></strong></td></tr></tfoot>
                </table>
            </div>
            <div class="report-split-col">
                <h3><?= View::e($report['right']['title']) ?></h3>
                <table class="data-table">
                    <tbody>
                        <?php foreach ($report['right']['rows'] as $row): ?>
                            <tr><td><?= View::e($row[0]) ?></td><td><?= View::e($row[1]) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$report['right']['rows']): ?><tr><td colspan="2">Sem dados no período.</td></tr><?php endif; ?>
                    </tbody>
                    <tfoot><tr><td><strong>Total</strong></td><td><strong><?= View::e($report['right']['total']) ?></strong></td></tr></tfoot>
                </table>
            </div>
        </div>

    <?php else: ?>
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
    <?php endif; ?>
</div>
