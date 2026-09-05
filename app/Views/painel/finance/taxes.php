<?php
use App\Core\View;

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');
$presets = [
    'Hoje' => ['from' => $today, 'to' => $today],
    'Esta semana' => ['from' => $weekStart, 'to' => $today],
    'Este mês' => ['from' => $monthStart, 'to' => $today],
    'Este ano' => ['from' => $yearStart, 'to' => $today],
];
?>
<div class="page-header">
    <h1>Controle Fiscal</h1>
</div>
<p class="hint-text" style="margin-top:0;">Imposto e custo real calculados pela tabela de preços por quantidade (mesma faixa que define a comissão do Licenciado) — só pedidos verificados no período.</p>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($from) ?>">
    <input type="date" name="to" value="<?= View::e($to) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php foreach ($presets as $label => $range): ?>
        <a class="link-small" href="?from=<?= $range['from'] ?>&to=<?= $range['to'] ?>"><?= $label ?></a>
    <?php endforeach; ?>
</form>

<div class="cards-grid">
    <div class="dash-card">
        <span>Faturamento vendido</span>
        <strong>R$ <?= number_format($totals['revenue'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Imposto devido</span>
        <strong>R$ <?= number_format($totals['tax'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Custo real do produto</span>
        <strong>R$ <?= number_format($totals['cost'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Margem líquida</span>
        <strong>R$ <?= number_format($totals['net'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Pedidos verificados</span>
        <strong><?= (int) $totals['count'] ?></strong>
    </div>
</div>

<h3 class="section-title">Por pedido</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>Pedido</th><th>Data</th><th>Cliente</th><th>Qtd.</th><th>Faturamento</th><th>Imposto</th><th>Custo</th><th>Margem líquida</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="/painel/pedidos/<?= (int) $r['id'] ?>">#<?= (int) $r['id'] ?></a></td>
                    <td><?= View::e(date('d/m/Y', strtotime($r['order_date']))) ?></td>
                    <td><?= View::e($r['client_name']) ?></td>
                    <td><?= (int) $r['total_qty'] ?></td>
                    <td>R$ <?= number_format((float) $r['total_value'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($r['tax'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($r['cost'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($r['net'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8">Nenhum pedido verificado nesse período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
