<?php
use App\Core\View;
?>
<h1>Funil de Conversão</h1>
<p class="section-sub">Lead → Orçamento → Pedido, com a taxa de perda em cada etapa — pra ver onde a rede está esfriando venda.</p>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($from) ?>">
    <input type="date" name="to" value="<?= View::e($to) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<?php
$leadToQuotePct = $leadCount > 0 ? round($quoteCount / $leadCount * 100, 1) : null;
$quoteToOrderPct = $quoteCount > 0 ? round($orderCount / $quoteCount * 100, 1) : null;
$leadToOrderPct = $leadCount > 0 ? round($orderCount / $leadCount * 100, 1) : null;
?>

<div class="funnel-stages">
    <div class="funnel-stage">
        <span class="funnel-stage-label">Leads</span>
        <strong class="funnel-stage-value"><?= (int) $leadCount ?></strong>
    </div>
    <div class="funnel-stage-arrow">
        <span>→</span>
        <span class="funnel-stage-pct"><?= $leadToQuotePct !== null ? $leadToQuotePct . '%' : '—' ?></span>
    </div>
    <div class="funnel-stage">
        <span class="funnel-stage-label">Orçamentos</span>
        <strong class="funnel-stage-value"><?= (int) $quoteCount ?></strong>
        <span class="hint-text">R$ <?= number_format($quoteValue, 2, ',', '.') ?></span>
    </div>
    <div class="funnel-stage-arrow">
        <span>→</span>
        <span class="funnel-stage-pct"><?= $quoteToOrderPct !== null ? $quoteToOrderPct . '%' : '—' ?></span>
    </div>
    <div class="funnel-stage">
        <span class="funnel-stage-label">Pedidos</span>
        <strong class="funnel-stage-value"><?= (int) $orderCount ?></strong>
        <span class="hint-text">R$ <?= number_format($orderValue, 2, ',', '.') ?></span>
    </div>
</div>

<div class="cards-grid">
    <div class="dash-card">
        <span>Conversão geral (Lead → Pedido)</span>
        <strong><?= $leadToOrderPct !== null ? $leadToOrderPct . '%' : '—' ?></strong>
    </div>
    <div class="dash-card">
        <span>Perda entre Lead e Orçamento</span>
        <strong><?= max(0, $leadCount - $quoteCount) ?></strong>
    </div>
    <div class="dash-card">
        <span>Perda entre Orçamento e Pedido</span>
        <strong><?= max(0, $quoteCount - $orderCount) ?></strong>
    </div>
</div>

<h3 class="section-title" style="margin-top:28px">Por vendedor / licenciado</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Nome</th><th>Papel</th><th>Leads</th><th>Orçamentos</th><th>Pedidos</th><th>Lead → Orçamento</th><th>Orçamento → Pedido</th></tr></thead>
        <tbody>
            <?php foreach ($breakdown as $b): ?>
                <tr>
                    <td><?= View::e($b['name']) ?></td>
                    <td><?= View::e(ucfirst($b['role_slug'])) ?></td>
                    <td><?= (int) $b['lead_count'] ?></td>
                    <td><?= (int) $b['quote_count'] ?></td>
                    <td><?= (int) $b['order_count'] ?></td>
                    <td><?= $b['lead_to_quote_pct'] !== null ? View::e($b['lead_to_quote_pct']) . '%' : '—' ?></td>
                    <td><?= $b['quote_to_order_pct'] !== null ? View::e($b['quote_to_order_pct']) . '%' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$breakdown): ?>
                <tr><td colspan="7">Nenhum lead, orçamento ou pedido no período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<p class="hint-text">As porcentagens comparam quantos registros de cada etapa foram criados no período — não necessariamente o mesmo lead virando orçamento virando pedido.</p>
