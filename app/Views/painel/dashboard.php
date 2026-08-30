<?php
use App\Core\View;
$role = $user['role_slug'] ?? '';
$hasMetrics = isset($metrics);
?>
<h1>Bem-vindo(a), <?= View::e($user['name']) ?></h1>

<?php if ($hasMetrics): ?>
    <form method="get" class="filter-bar">
        <label>Período: <input type="date" name="from" value="<?= View::e($from) ?>"></label>
        <label>até <input type="date" name="to" value="<?= View::e($to) ?>"></label>
        <button type="submit" class="btn btn-outline">Visualizar</button>
    </form>

    <div class="cards-grid">
        <div class="dash-card">
            <span>Valor Total</span>
            <strong>R$ <?= number_format($metrics['total_value'], 2, ',', '.') ?></strong>
            <small class="<?= $changes['total_value'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['total_value'] >= 0 ? '+' : '' ?><?= $changes['total_value'] ?>% vs. período anterior</small>
        </div>
        <div class="dash-card">
            <span>Quantidade de Pedidos</span>
            <strong><?= $metrics['order_count'] ?></strong>
            <small class="<?= $changes['order_count'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['order_count'] >= 0 ? '+' : '' ?><?= $changes['order_count'] ?>%</small>
        </div>
        <div class="dash-card">
            <span>Produtos Vendidos</span>
            <strong><?= $metrics['products_sold'] ?></strong>
            <small class="<?= $changes['products_sold'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['products_sold'] >= 0 ? '+' : '' ?><?= $changes['products_sold'] ?>%</small>
        </div>
        <div class="dash-card">
            <span>Ticket Médio</span>
            <strong>R$ <?= number_format($metrics['ticket_medio'], 2, ',', '.') ?></strong>
            <small class="<?= $changes['ticket_medio'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['ticket_medio'] >= 0 ? '+' : '' ?><?= $changes['ticket_medio'] ?>%</small>
        </div>
    </div>

    <h3 class="section-title">Valor total de pedidos por dia</h3>
    <div class="chart-box">
        <?= $chartSvg ?>
        <p class="chart-legend"><span class="dot dot-current"></span> Período atual &nbsp; <span class="dot dot-previous"></span> Período anterior</p>
    </div>

    <div class="two-col">
        <div>
            <h3 class="section-title">Top produtos vendidos</h3>
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
        </div>
        <div>
            <h3 class="section-title">Resumo</h3>
            <table class="data-table">
                <tbody>
                    <tr><td>Faturamento</td><td>R$ <?= number_format($metrics['total_value'], 2, ',', '.') ?></td></tr>
                    <tr><td>Custo dos produtos</td><td>R$ <?= number_format($costTotal, 2, ',', '.') ?></td></tr>
                    <tr><td>Margem bruta</td><td>R$ <?= number_format($grossMargin, 2, ',', '.') ?></td></tr>
                    <tr><td>Impostos / Taxas</td><td>R$ 0,00 <span class="hint-inline">(não modelado ainda)</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($role === 'admin'): ?>
        <h3 class="section-title">Outros indicadores</h3>
        <div class="cards-grid">
            <div class="dash-card">
                <span>Leads recebidos</span>
                <strong><?= (int) ($leadCount ?? 0) ?></strong>
                <a href="/painel/leads">Ver todos</a>
            </div>
            <div class="dash-card">
                <span>Usuários</span>
                <a href="/painel/usuarios">Gerenciar usuários e papéis</a>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="cards-grid">
        <div class="dash-card dash-card-soon">
            <span>Meu pedido</span>
            <p>Em breve</p>
        </div>
    </div>
<?php endif; ?>
