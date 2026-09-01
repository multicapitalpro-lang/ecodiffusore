<?php
use App\Core\View;
?>
<h1>Vendas por Vendedor</h1>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($from) ?>">
    <input type="date" name="to" value="<?= View::e($to) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<div class="cards-grid-2">
    <div>
        <h3 class="section-title">Ranking de vendedores</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Vendedor</th><th>Pedidos</th><th>Valor vendido</th><th>Comissão</th></tr></thead>
                <tbody>
                    <?php foreach ($ranking as $r): ?>
                        <tr>
                            <td><?= View::e($r['name']) ?></td>
                            <td><?= (int) $r['order_count'] ?></td>
                            <td>R$ <?= number_format((float) $r['total_value'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format((float) $r['commission_total'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$ranking): ?>
                        <tr><td colspan="4">Nenhum vendedor cadastrado ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
    </div>
</div>
