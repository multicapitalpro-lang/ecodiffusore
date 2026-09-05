<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
?>
<label for="min_qty">A partir de quantas placas (no mesmo pedido)</label>
<input type="number" id="min_qty" name="min_qty" min="1" step="1" value="<?= View::e((string) ($values['min_qty'] ?? '')) ?>" required>
<p class="field-error" data-error-for="min_qty"><?= View::e($errors['min_qty'] ?? '') ?></p>

<label for="unit_price">Preço unitário (R$)</label>
<input type="number" id="unit_price" name="unit_price" step="0.01" value="<?= View::e((string) ($values['unit_price'] ?? '')) ?>" required>
<p class="field-error" data-error-for="unit_price"><?= View::e($errors['unit_price'] ?? '') ?></p>

<label for="licenciado_commission_pct">Comissão do Licenciado (%)</label>
<input type="number" id="licenciado_commission_pct" name="licenciado_commission_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['licenciado_commission_pct'] ?? '')) ?>" required>
<p class="field-error" data-error-for="licenciado_commission_pct"><?= View::e($errors['licenciado_commission_pct'] ?? '') ?></p>

<div class="form-grid-2">
    <div>
        <label for="cost_price">Custo (R$)</label>
        <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" value="<?= View::e((string) ($values['cost_price'] ?? '0')) ?>">
        <p class="field-error" data-error-for="cost_price"><?= View::e($errors['cost_price'] ?? '') ?></p>
    </div>
    <div>
        <label for="tax_pct">Imposto (%)</label>
        <input type="number" id="tax_pct" name="tax_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['tax_pct'] ?? '0')) ?>">
        <p class="field-error" data-error-for="tax_pct"><?= View::e($errors['tax_pct'] ?? '') ?></p>
    </div>
</div>
