<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
?>
<div class="form-grid-2">
    <div>
        <label for="min_price">Preço mínimo da faixa (R$)</label>
        <input type="number" id="min_price" name="min_price" step="0.01" min="0" value="<?= View::e((string) ($values['min_price'] ?? '')) ?>" required>
        <p class="field-error" data-error-for="min_price"><?= View::e($errors['min_price'] ?? '') ?></p>
    </div>
    <div>
        <label for="max_price">Preço máximo da faixa (R$)</label>
        <input type="number" id="max_price" name="max_price" step="0.01" min="0" value="<?= View::e((string) ($values['max_price'] ?? '')) ?>" placeholder="Deixe em branco = sem limite superior">
        <p class="field-error" data-error-for="max_price"><?= View::e($errors['max_price'] ?? '') ?></p>
    </div>
</div>

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
