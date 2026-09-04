<?php
use App\Core\View;
?>
<h1>Vendas por Vendedor</h1>
<p class="section-sub">Ranking, cobertura por região e leads na base — pra saber onde reforçar o time.</p>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($from) ?>">
    <input type="date" name="to" value="<?= View::e($to) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<div class="cards-grid">
    <div class="dash-card">
        <span>Vendedores ativos no período</span>
        <strong><?= count(array_filter($ranking, fn ($r) => (int) $r['order_count'] > 0)) ?></strong>
    </div>
    <div class="dash-card">
        <span>Total de leads na base</span>
        <strong><?= array_sum(array_map(fn ($r) => (int) $r['lead_count'], $ranking)) ?></strong>
    </div>
    <div class="dash-card">
        <span>Leads em aberto (não convertidos/descartados)</span>
        <strong><?= array_sum(array_map(fn ($r) => (int) $r['lead_open_count'], $ranking)) ?></strong>
    </div>
    <div class="dash-card">
        <span>Ticket médio geral</span>
        <?php $activeRanking = array_filter($ranking, fn ($r) => (int) $r['order_count'] > 0); ?>
        <strong>R$ <?= number_format($activeRanking ? array_sum(array_map(fn ($r) => (float) $r['avg_ticket'], $activeRanking)) / count($activeRanking) : 0, 2, ',', '.') ?></strong>
    </div>
</div>

<div class="cards-grid-2">
    <div>
        <h3 class="section-title">Ranking de vendedores</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th></th><th>Vendedor</th><th>Região</th><th>Licenciado</th><th>Pedidos</th><th>Valor vendido</th><th>Ticket médio</th><th>Comissão</th><th>Leads</th><th>Conversão</th></tr></thead>
                <tbody>
                    <?php foreach ($ranking as $i => $r): ?>
                        <tr>
                            <td><?= $i === 0 && (float) $r['total_value'] > 0 ? '🏆' : ($i === 1 && (float) $r['total_value'] > 0 ? '🥈' : ($i === 2 && (float) $r['total_value'] > 0 ? '🥉' : '')) ?></td>
                            <td><?= View::e($r['name']) ?></td>
                            <td><?= View::e(trim(($r['city'] ?: '') . ($r['state'] ? '/' . $r['state'] : '')) ?: '—') ?></td>
                            <td><?= View::e($r['licenciado_name'] ?? '—') ?></td>
                            <td><?= (int) $r['order_count'] ?></td>
                            <td>R$ <?= number_format((float) $r['total_value'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float) $r['avg_ticket'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float) $r['commission_total'], 2, ',', '.') ?></td>
                            <td><?= (int) $r['lead_count'] ?> <?php if ((int) $r['lead_open_count'] > 0): ?><span class="hint-text">(<?= (int) $r['lead_open_count'] ?> em aberto)</span><?php endif; ?></td>
                            <td><?= $r['conversion_pct'] !== null ? View::e($r['conversion_pct']) . '%' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ranking): ?>
                        <tr><td colspan="10">Nenhum vendedor cadastrado ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="hint-text">Conversão = pedidos no período ÷ leads atribuídos a esse vendedor (histórico total, não só do período).</p>
    </div>

    <div>
        <h3 class="section-title">Top produtos no período</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Produto</th><th>Qtd.</th><th>Valor</th></tr></thead>
                <tbody>
                    <?php foreach ($topProducts as $p): ?>
                        <tr>
                            <td><?= View::e($p['name']) ?></td>
                            <td><?= (int) $p['total_qty'] ?></td>
                            <td>R$ <?= number_format((float) $p['total_value'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$topProducts): ?>
                        <tr><td colspan="3">Nenhuma venda no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3 class="section-title">Vendedores sem leads atribuídos</h3>
        <?php $noLeads = array_filter($ranking, fn ($r) => (int) $r['lead_count'] === 0); ?>
        <?php if ($noLeads): ?>
            <ul class="check-list" style="list-style:none;padding:0;">
                <?php foreach ($noLeads as $r): ?>
                    <li>⚠️ <?= View::e($r['name']) ?> — nenhum lead na base ainda</li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="hint-text">Todos os vendedores têm ao menos 1 lead atribuído.</p>
        <?php endif; ?>
    </div>
</div>
