<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
$values = $values ?? [];
$openModal = isset($_GET['novo']) || $errors;
?>
<div class="page-header">
    <h1>Produtos</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-product">+ Novo produto</button>
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

<dialog class="modal" id="modal-product" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Novo produto</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/produtos" method="post" class="panel-form ajax-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar produto</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
