<?php
use App\Core\View;
/** @var array $accounts */
/** @var array $categoryGroups */
/** @var array $clients */
/** @var array $values */
/** @var array $errors */
$values = $values ?? [];
$errors = $errors ?? [];
?>
<label for="tx-category">Categoria</label>
<select id="tx-category" name="category_id">
    <option value="">Sem categoria</option>
    <?php foreach ($categoryGroups as $group): ?>
        <optgroup label="<?= View::e($group['parent']['name']) ?>">
            <?php foreach ($group['children'] as $child): ?>
                <option value="<?= (int) $child['id'] ?>" <?= (int) ($values['category_id'] ?? 0) === (int) $child['id'] ? 'selected' : '' ?>>
                    <?= View::e($child['name']) ?>
                </option>
            <?php endforeach; ?>
        </optgroup>
    <?php endforeach; ?>
</select>

<div class="form-grid-2">
    <div>
        <label for="tx-due-date">Data</label>
        <input type="date" id="tx-due-date" name="due_date" value="<?= View::e($values['due_date'] ?? date('Y-m-d')) ?>" required>
    </div>
    <div>
        <label for="tx-amount">Valor (R$)</label>
        <input type="number" step="0.01" id="tx-amount" name="amount" value="<?= View::e((string) ($values['amount'] ?? '')) ?>" required>
        <p class="field-error" data-error-for="amount"><?= View::e($errors['amount'] ?? '') ?></p>
    </div>
</div>

<div class="form-grid-2">
    <div>
        <label for="tx-type">Tipo</label>
        <select id="tx-type" name="type">
            <option value="saida" <?= ($values['type'] ?? 'saida') === 'saida' ? 'selected' : '' ?>>Saída</option>
            <option value="entrada" <?= ($values['type'] ?? '') === 'entrada' ? 'selected' : '' ?>>Entrada</option>
        </select>
    </div>
    <div>
        <label for="tx-competencia">Competência</label>
        <input type="date" id="tx-competencia" name="competencia" value="<?= View::e($values['competencia'] ?? date('Y-m-d')) ?>">
    </div>
</div>

<label for="tx-account">Conta financeira</label>
<select id="tx-account" name="account_id" required>
    <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int) $acc['id'] ?>" <?= (int) ($values['account_id'] ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>><?= View::e($acc['name']) ?></option>
    <?php endforeach; ?>
</select>
<p class="field-error" data-error-for="account_id"><?= View::e($errors['account_id'] ?? '') ?></p>

<label for="tx-history">Histórico</label>
<textarea id="tx-history" name="description" maxlength="2000" required><?= View::e($values['description'] ?? '') ?></textarea>
<p class="field-error" data-error-for="description"><?= View::e($errors['description'] ?? '') ?></p>

<label for="tx-client">Cliente ou fornecedor</label>
<select id="tx-client" name="client_id">
    <option value="">Nenhum</option>
    <?php foreach ($clients as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) ($values['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
    <?php endforeach; ?>
</select>
<button type="button" class="link-small link-button" data-modal-open="modal-client-inline">+ Cadastrar novo cliente/fornecedor</button>

<?php include __DIR__ . '/_attachment_field.php'; ?>
