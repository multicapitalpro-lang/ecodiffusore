<?php
use App\Core\View;
/** @var string $type 'saida' (Contas a Pagar) ou 'entrada' (Contas a Receber) */
/** @var array $accounts */
/** @var array $categoryGroups */
/** @var array $clients */
/** @var array $values */
/** @var array $errors */
/** @var bool $isEdit se true, esconde os campos de recorrencia (so fazem sentido na criacao) */
$values = $values ?? [];
$errors = $errors ?? [];
$isEdit = $isEdit ?? false;
$personLabel = $type === 'entrada' ? 'Cliente' : 'Fornecedor';
?>
<input type="hidden" name="type" value="<?= $type ?>">

<label for="pay-client">
    <?= $personLabel ?>
</label>
<select id="pay-client" name="client_id" <?= $isEdit ? '' : 'required' ?>>
    <option value="">Selecione...</option>
    <?php foreach ($clients as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) ($values['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
    <?php endforeach; ?>
</select>
<button type="button" class="link-small link-button" data-modal-open="modal-client-inline">+ Cadastrar <?= mb_strtolower($personLabel) ?></button>
<p class="field-error" data-error-for="client_id"><?= View::e($errors['client_id'] ?? '') ?></p>

<label for="pay-amount">Valor (R$)</label>
<input type="number" step="0.01" id="pay-amount" name="amount" value="<?= View::e((string) ($values['amount'] ?? '')) ?>" required>
<p class="field-error" data-error-for="amount"><?= View::e($errors['amount'] ?? '') ?></p>

<div class="form-grid-2">
    <div>
        <label for="pay-issue">Emissão</label>
        <input type="date" id="pay-issue" name="issue_date" value="<?= View::e($values['issue_date'] ?? date('Y-m-d')) ?>" required>
    </div>
    <div>
        <label for="pay-competencia">Competência</label>
        <input type="date" id="pay-competencia" name="competencia" value="<?= View::e($values['competencia'] ?? date('Y-m-d')) ?>" required>
    </div>
</div>

<label for="pay-due">Vencimento</label>
<input type="date" id="pay-due" name="due_date" value="<?= View::e($values['due_date'] ?? '') ?>" required>
<p class="field-error" data-error-for="due_date"><?= View::e($errors['due_date'] ?? '') ?></p>

<label for="pay-history">Histórico</label>
<textarea id="pay-history" name="description" maxlength="2000"><?= View::e($values['description'] ?? '') ?></textarea>

<div class="form-grid-2">
    <div>
        <label for="pay-method">Forma de pagamento</label>
        <select id="pay-method" name="payment_method">
            <option value="">Selecione...</option>
            <option value="boleto">Boleto</option>
            <option value="pix">Pix</option>
            <option value="cartao">Cartão</option>
            <option value="transferencia">Transferência</option>
            <option value="dinheiro">Dinheiro</option>
        </select>
    </div>
    <div>
        <label for="pay-account">Conta financeira</label>
        <select id="pay-account" name="account_id" required>
            <?php foreach ($accounts as $acc): ?>
                <option value="<?= (int) $acc['id'] ?>" <?= (int) ($values['account_id'] ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>><?= View::e($acc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="form-grid-2">
    <div>
        <label for="pay-category">Categoria</label>
        <select id="pay-category" name="category_id">
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
    </div>
    <div>
        <label for="pay-doc">Nº documento</label>
        <input type="text" id="pay-doc" name="document_number" value="<?= View::e($values['document_number'] ?? '') ?>">
    </div>
</div>

<div class="form-grid-2">
    <div>
        <label for="pay-interest">Juros mensal (%)</label>
        <input type="number" step="0.01" id="pay-interest" name="interest_pct" value="<?= View::e((string) ($values['interest_pct'] ?? '0')) ?>">
    </div>
    <div>
        <label for="pay-penalty">Multa (%)</label>
        <input type="number" step="0.01" id="pay-penalty" name="penalty_pct" value="<?= View::e((string) ($values['penalty_pct'] ?? '0')) ?>">
    </div>
</div>

<?php if (!$isEdit): ?>
    <div class="form-grid-2">
        <div>
            <label for="pay-recurrence">Repetir esse lançamento</label>
            <select id="pay-recurrence" name="recurrence_frequency">
                <option value="">Não repetir</option>
                <option value="semanal" <?= ($values['recurrence_frequency'] ?? '') === 'semanal' ? 'selected' : '' ?>>Semanalmente</option>
                <option value="mensal" <?= ($values['recurrence_frequency'] ?? '') === 'mensal' ? 'selected' : '' ?>>Mensalmente</option>
                <option value="anual" <?= ($values['recurrence_frequency'] ?? '') === 'anual' ? 'selected' : '' ?>>Anualmente</option>
            </select>
        </div>
        <div>
            <label for="pay-recurrence-count">Quantas vezes (incluindo esta)</label>
            <input type="number" id="pay-recurrence-count" name="recurrence_count" min="1" max="60" value="<?= View::e((string) ($values['recurrence_count'] ?? '1')) ?>">
        </div>
    </div>
    <p class="hint-text" style="margin-top:-8px;">Gera todas as ocorrências futuras já na criação (ex: aluguel 12x cria os 12 lançamentos mensais de uma vez).</p>
<?php endif; ?>

<?php if (!$isEdit): ?>
    <?php include __DIR__ . '/_attachment_field.php'; ?>
<?php else: ?>
    <p class="hint-text">Os anexos já enviados continuam disponíveis na coluna "Anexos" da tabela — essa edição não adiciona novos.</p>
<?php endif; ?>
