<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
?>
<label for="sku">SKU</label>
<input type="text" id="sku" name="sku" value="<?= View::e($values['sku'] ?? '') ?>" required>
<p class="field-error" data-error-for="sku"><?= View::e($errors['sku'] ?? '') ?></p>

<label for="name">Nome</label>
<input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
<p class="field-error" data-error-for="name"><?= View::e($errors['name'] ?? '') ?></p>

<div class="form-grid-2">
    <div>
        <label for="price_cash">Preço mínimo / padrão (R$)</label>
        <input type="number" step="0.01" id="price_cash" name="price_cash" value="<?= View::e((string) ($values['price_cash'] ?? '')) ?>" required>
        <p class="field-error" data-error-for="price_cash"><?= View::e($errors['price_cash'] ?? '') ?></p>
    </div>
    <div>
        <label for="price_high">Preço máximo (R$)</label>
        <input type="number" step="0.01" id="price_high" name="price_high" value="<?= View::e((string) ($values['price_high'] ?? '')) ?>" required>
        <p class="field-error" data-error-for="price_high"><?= View::e($errors['price_high'] ?? '') ?></p>
    </div>
</div>
<p class="hint-text">O vendedor escolhe entre esses dois preços na hora da venda (proposta/pedido) — não há valores intermediários.</p>

<label for="price_installment">Valor da parcela 6x (R$)</label>
<input type="number" step="0.01" id="price_installment" name="price_installment" value="<?= View::e((string) ($values['price_installment'] ?? '')) ?>" required>
<p class="field-error" data-error-for="price_installment"><?= View::e($errors['price_installment'] ?? '') ?></p>

<label for="cost_price">Custo (R$)</label>
<input type="number" step="0.01" id="cost_price" name="cost_price" value="<?= View::e((string) ($values['cost_price'] ?? '0')) ?>">

<label class="checkbox-label">
    <input type="checkbox" name="active" value="1" <?= (($values['active'] ?? 1)) ? 'checked' : '' ?>> Ativo
</label>
