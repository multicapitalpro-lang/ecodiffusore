<?php
use App\Core\View;
$isModal = $isModal ?? false;
$metricType = $goal['metric_type'] ?? 'valor';
$fmt = fn (float $v) => $metricType === 'quantidade'
    ? number_format($v, 0, ',', '.') . ' un.'
    : 'R$ ' . number_format($v, 2, ',', '.');

$statusInfo = [
    'batida' => ['label' => '🎉 Meta batida', 'class' => ''],
    'adiantado' => ['label' => '🚀 Adiantado', 'class' => ''],
    'no_ritmo' => ['label' => '✅ No ritmo', 'class' => ''],
    'atrasado' => ['label' => '⚠️ Atrasado', 'class' => 'dash-card-danger'],
    'encerrada_nao_batida' => ['label' => '⏱️ Encerrada, meta não batida', 'class' => 'dash-card-danger'],
][$pace['status']] ?? ['label' => $pace['status'], 'class' => ''];
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Ritmo da meta</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <div class="page-header">
        <h1>📈 Ritmo da meta</h1>
    </div>
<?php endif; ?>

    <h3 class="section-title" style="margin-top:0;"><?= View::e($goal['name']) ?></h3>
    <p class="hint-inline"><?= $goal['seller_name'] ? View::e($goal['seller_name']) : 'Geral' ?> · <?= View::e(date('d/m/Y', strtotime($goal['start_date']))) ?> a <?= View::e(date('d/m/Y', strtotime($goal['end_date']))) ?></p>

    <div class="dash-card <?= $statusInfo['class'] ?>" style="text-align:left;margin-top:14px;">
        <span><?= $statusInfo['label'] ?></span>
        <strong style="font-size:1.6rem;"><?= $pace['pct'] ?>%</strong>
        <span class="hint-inline">esperado nesse ponto do período: <?= $pace['expected_pct'] ?>%</span>
    </div>

    <div class="table-scroll" style="margin-top:16px;">
        <table class="data-table">
            <tbody>
                <tr><td>Alcançado</td><td><?= $fmt($pace['achieved']) ?> de <?= $fmt($pace['target']) ?></td></tr>
                <tr><td>Dias decorridos</td><td><?= (int) $pace['elapsed_days'] ?> de <?= (int) $pace['total_days'] ?> dia(s)</td></tr>
                <tr><td>Dias restantes</td><td><?= (int) $pace['remaining_days'] ?></td></tr>
                <tr><td>Sua média diária até agora</td><td><?= $fmt($pace['daily_average']) ?>/dia</td></tr>
                <?php if (!$pace['reached'] && $pace['remaining_days'] > 0): ?>
                    <tr><td>Ritmo necessário pra bater a meta</td><td><strong><?= $fmt($pace['daily_needed']) ?>/dia</strong></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pace['status'] === 'atrasado'): ?>
        <p class="hint-text" style="margin-top:10px;">Nesse ritmo, a meta não é batida a tempo. Aumentando pra <?= $fmt($pace['daily_needed']) ?>/dia no que resta do período, ainda dá pra chegar lá.</p>
    <?php endif; ?>

<?php if ($isModal): ?>
    </div>
<?php endif; ?>
