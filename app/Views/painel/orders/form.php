<?php
use App\Core\Csrf;
use App\Core\View;
$isEdit = $editing !== null;
$action = $isEdit ? '/painel/pedidos/' . (int) $editing['id'] : '/painel/pedidos';
$values = $editing ?? [];
$isLicenciado = ($user['role_slug'] ?? '') === 'licenciado';
?>
<h1><?= $isEdit ? 'Editar Pedido #' . (int) $editing['id'] : 'Incluir Pedido' ?></h1>

<?php if (!empty($errors['items'])): ?>
    <p class="form-msg form-msg-erro"><?= View::e($errors['items']) ?></p>
<?php endif; ?>

<form action="<?= $action ?>" method="post" class="panel-form panel-form-wide" id="order-form">
    <?= Csrf::field() ?>

    <div class="form-grid-2">
        <div>
            <label for="client_id">Cliente</label>
            <select id="client_id" name="client_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($values['client_id'] ?? $preselectClientId) === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= View::e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="/painel/clientes/novo?redirect_to=pedido-novo" class="link-small">+ Cadastrar novo cliente</a>
            <?php if (!empty($errors['client_id'])): ?><p class="field-error"><?= View::e($errors['client_id']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="order_date">Data do pedido</label>
            <input type="date" id="order_date" name="order_date" value="<?= View::e($values['order_date'] ?? date('Y-m-d')) ?>" required>
            <?php if (!empty($errors['order_date'])): ?><p class="field-error"><?= View::e($errors['order_date']) ?></p><?php endif; ?>
        </div>
    </div>

    <?php if (!$isLicenciado): ?>
        <label for="seller_id">Vendedor / Licenciado</label>
        <select id="seller_id" name="seller_id">
            <option value="">Sem vendedor definido</option>
            <?php foreach ($sellers as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) ($values['seller_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= View::e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>

    <h3 class="section-title">Produtos</h3>
    <div class="table-scroll">
        <table class="data-table" id="items-table">
            <thead>
                <tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody id="items-body">
                <?php
                $rows = $items ?: [['product_id' => '', 'quantity' => 1, 'unit_price' => '']];
                foreach ($rows as $item):
                ?>
                <tr class="item-row">
                    <td>
                        <select name="product_id[]" class="item-product" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" data-price="<?= (float) $p['price_cash'] ?>"
                                    <?= (int) ($item['product_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= View::e($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" name="quantity[]" class="item-qty" min="1" value="<?= (int) ($item['quantity'] ?? 1) ?>" required></td>
                    <td><input type="number" step="0.01" name="unit_price[]" class="item-price" value="<?= View::e((string) ($item['unit_price'] ?? '')) ?>" required></td>
                    <td class="item-subtotal">R$ 0,00</td>
                    <td><button type="button" class="btn-remove-row" aria-label="Remover">&times;</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" id="add-item-row" class="btn btn-outline btn-sm">+ Adicionar produto</button>

    <p class="order-total">Total do pedido: <strong id="order-total">R$ 0,00</strong></p>

    <label for="notes">Observações</label>
    <textarea id="notes" name="notes"><?= View::e($values['notes'] ?? '') ?></textarea>

    <button type="submit" class="btn btn-primary">Salvar Pedido</button>
    <a href="/painel/pedidos" class="btn btn-outline">Cancelar</a>
</form>

<script src="<?= View::asset('/assets/js/painel.js') ?>"></script>
