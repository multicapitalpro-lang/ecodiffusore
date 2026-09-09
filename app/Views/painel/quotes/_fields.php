<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
/** @var array $items */
/** @var array $clients */
/** @var array $products */
/** @var array $pricingTiers */
/** @var array $sellers */
/** @var bool $isVendedor */
/** @var int $preselectClientId */
?>
<?php if (!empty($errors['items'])): ?>
    <p class="form-msg form-msg-erro"><?= View::e($errors['items']) ?></p>
<?php endif; ?>

<div class="form-grid-2">
    <div>
        <label for="q-client_id">Cliente</label>
        <select id="q-client_id" name="client_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($values['client_id'] ?? $preselectClientId) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= View::e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="link-small link-button" data-modal-open="modal-client-inline">+ Cadastrar novo cliente</button>
        <p class="field-error" data-error-for="client_id"><?= View::e($errors['client_id'] ?? '') ?></p>
    </div>

    <div>
        <label for="q-date">Data do orçamento</label>
        <input type="date" id="q-date" name="quote_date" value="<?= View::e($values['quote_date'] ?? date('Y-m-d')) ?>" required>
    </div>
</div>

<label for="q-valid">Válido até</label>
<input type="date" id="q-valid" name="valid_until" value="<?= View::e($values['valid_until'] ?? date('Y-m-d', strtotime('+7 days'))) ?>">

<?php if (!$isVendedor): ?>
    <label for="q-seller">Vendedor</label>
    <select id="q-seller" name="seller_id">
        <option value="">Sem vendedor definido</option>
        <?php foreach ($sellers as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (int) ($values['seller_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
        <?php endforeach; ?>
    </select>
<?php endif; ?>

<h3 class="section-title">Produtos</h3>
<?php if ($pricingTiers): ?>
    <p class="hint-text" style="margin-top:0;">Preço negociado livremente (piso R$ <?= number_format((float) $pricingTiers[0]['min_price'], 2, ',', '.') ?>) — a faixa de preço define a % de comissão do Licenciado: <?php foreach ($pricingTiers as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?>R$ <?= number_format((float) $t['min_price'], 2, ',', '.') ?><?= $t['max_price'] !== null ? '–' . number_format((float) $t['max_price'], 2, ',', '.') : ' acima' ?> = <?= number_format((float) $t['licenciado_commission_pct'], 2, ',', '.') ?>%<?php endforeach; ?>.</p>
<?php endif; ?>
<div class="table-scroll">
    <table class="data-table" id="qitems-table">
        <thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th><th></th></tr></thead>
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
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($item['product_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
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

<p class="order-total">Total do orçamento: <strong id="order-total">R$ 0,00</strong></p>

<label for="q-notes">Observações</label>
<textarea id="q-notes" name="notes"><?= View::e($values['notes'] ?? '') ?></textarea>
