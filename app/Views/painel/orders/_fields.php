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
        <label for="client_id">Cliente</label>
        <select id="client_id" name="client_id" required>
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
        <label for="order_date">Data do pedido</label>
        <input type="date" id="order_date" name="order_date" value="<?= View::e($values['order_date'] ?? date('Y-m-d')) ?>" required>
        <p class="field-error" data-error-for="order_date"><?= View::e($errors['order_date'] ?? '') ?></p>
    </div>
</div>

<?php if (!$isVendedor): ?>
    <label for="seller_id">Vendedor</label>
    <select id="seller_id" name="seller_id">
        <option value="">Sem vendedor definido</option>
        <?php foreach ($sellers as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (int) ($values['seller_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                <?= View::e($s['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
<?php endif; ?>

<h3 class="section-title">Veículo</h3>
<div class="form-grid-2">
    <div>
        <label for="vehicle_type">Tipo de veículo</label>
        <select id="vehicle_type" name="vehicle_type">
            <option value="">Selecione...</option>
            <?php foreach (['Caminhão', 'Ônibus', 'Máquina agrícola', 'Máquina de linha amarela', 'Gerador', 'Outro'] as $tipo): ?>
                <option value="<?= View::e($tipo) ?>" <?= ($values['vehicle_type'] ?? '') === $tipo ? 'selected' : '' ?>><?= View::e($tipo) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="vehicle_plate">Placa (opcional)</label>
        <input type="text" id="vehicle_plate" name="vehicle_plate" maxlength="10" style="text-transform:uppercase" value="<?= View::e($values['vehicle_plate'] ?? '') ?>">
    </div>
</div>

<label>Foto do documento do veículo (opcional)</label>
<label class="file-drop" data-file-drop>
    <span data-file-drop-label>Solte o arquivo aqui ou clique para adicionar (PDF, JPG, PNG — até 5MB)</span>
    <input type="file" id="vehicle_document" name="vehicle_document" accept=".pdf,.jpg,.jpeg,.png,.webp">
</label>
<ul class="file-list" data-file-list></ul>
<p class="hint-text">Guardado pra referência e futura análise automática do veículo.</p>
<p class="field-error" data-error-for="vehicle_document"><?= View::e($errors['vehicle_document'] ?? '') ?></p>

<h3 class="section-title">Produtos</h3>
<?php if ($pricingTiers): ?>
    <p class="hint-text" style="margin-top:0;">Preço negociado livremente (piso R$ <?= number_format((float) $pricingTiers[0]['min_price'], 2, ',', '.') ?>) — a faixa de preço define a % de comissão do Licenciado: <?php foreach ($pricingTiers as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?>R$ <?= number_format((float) $t['min_price'], 2, ',', '.') ?><?= $t['max_price'] !== null ? '–' . number_format((float) $t['max_price'], 2, ',', '.') : ' acima' ?> = <?= number_format((float) $t['licenciado_commission_pct'], 2, ',', '.') ?>%<?php endforeach; ?>.</p>
<?php endif; ?>
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

<p class="order-total">Total do pedido: <strong id="order-total">R$ 0,00</strong></p>

<label for="notes">Observações</label>
<textarea id="notes" name="notes"><?= View::e($values['notes'] ?? '') ?></textarea>
