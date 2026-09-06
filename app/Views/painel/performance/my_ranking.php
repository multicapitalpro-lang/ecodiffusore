<?php
use App\Core\View;
$myId = (int) $user['id'];
?>
<h1>Meu ranking</h1>
<p class="section-sub">Como você está em relação ao resto do time, no período.</p>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($from) ?>">
    <input type="date" name="to" value="<?= View::e($to) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<div class="table-scroll" style="margin-top:16px;">
    <table class="data-table">
        <thead><tr><th></th><th>Vendedor</th><th>Pedidos</th><th>Valor vendido</th><th>Ticket médio</th><th>Conversão</th></tr></thead>
        <tbody>
            <?php foreach ($ranking as $i => $r): ?>
                <tr <?= (int) $r['seller_id'] === $myId ? 'style="background:var(--bg-accent, #eef8f0); font-weight:600;"' : '' ?>>
                    <td><?= $i === 0 && (float) $r['total_value'] > 0 ? '🏆' : ($i === 1 && (float) $r['total_value'] > 0 ? '🥈' : ($i === 2 && (float) $r['total_value'] > 0 ? '🥉' : '')) ?></td>
                    <td><?= View::e($r['name']) ?><?= (int) $r['seller_id'] === $myId ? ' (você)' : '' ?></td>
                    <td><?= (int) $r['order_count'] ?></td>
                    <td>R$ <?= number_format((float) $r['total_value'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $r['avg_ticket'], 2, ',', '.') ?></td>
                    <td><?= $r['conversion_pct'] !== null ? View::e($r['conversion_pct']) . '%' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ranking): ?>
                <tr><td colspan="6">Sem dados no período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
