<?php
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
?>
<div class="page-header">
    <h1>Produtos</h1>
    <a href="/painel/produtos/novo" class="btn btn-primary">+ Novo produto</a>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Produto salvo com sucesso.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>SKU</th><th>Nome</th><th>À vista</th><th>Parcela (6x)</th><th>Custo</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= View::e($p['sku']) ?></td>
                    <td><?= View::e($p['name']) ?></td>
                    <td>R$ <?= number_format((float) $p['price_cash'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $p['price_installment'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $p['cost_price'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $p['active'] ? 'active' : 'inactive' ?>"><?= $p['active'] ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><a href="/painel/produtos/<?= (int) $p['id'] ?>/editar">Editar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
