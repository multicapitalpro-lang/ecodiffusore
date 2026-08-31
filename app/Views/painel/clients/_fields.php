<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
/** @var array $sellers */
$sellers = $sellers ?? [];
?>
<label for="name">Nome / Razão social</label>
<input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
<p class="field-error" data-error-for="name"><?= View::e($errors['name'] ?? '') ?></p>

<div class="form-grid-2">
    <div>
        <label for="person_type">Tipo de pessoa</label>
        <select id="person_type" name="person_type">
            <option value="fisica" <?= ($values['person_type'] ?? 'fisica') === 'fisica' ? 'selected' : '' ?>>Pessoa Física</option>
            <option value="juridica" <?= ($values['person_type'] ?? '') === 'juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
        </select>
    </div>
    <div>
        <label for="document">CPF/CNPJ</label>
        <input type="text" id="document" name="document" value="<?= View::e($values['document'] ?? '') ?>">
    </div>
</div>

<label for="state_registration">Inscrição Estadual</label>
<input type="text" id="state_registration" name="state_registration" value="<?= View::e($values['state_registration'] ?? '') ?>" placeholder="Isento, se não houver">

<label for="email">E-mail</label>
<input type="email" id="email" name="email" value="<?= View::e($values['email'] ?? '') ?>">
<p class="field-error" data-error-for="email"><?= View::e($errors['email'] ?? '') ?></p>

<label for="whatsapp">WhatsApp</label>
<input type="text" id="whatsapp" name="whatsapp" value="<?= View::e($values['whatsapp'] ?? '') ?>">

<label for="city">Cidade</label>
<input type="text" id="city" name="city" value="<?= View::e($values['city'] ?? '') ?>">

<label for="state">UF</label>
<input type="text" id="state" name="state" maxlength="2" style="text-transform:uppercase" value="<?= View::e($values['state'] ?? '') ?>">

<label for="address">Endereço</label>
<input type="text" id="address" name="address" value="<?= View::e($values['address'] ?? '') ?>">

<div class="form-grid-2">
    <div>
        <label for="credit_limit_type">Limite de crédito</label>
        <select id="credit_limit_type" name="credit_limit_type">
            <option value="ilimitado" <?= ($values['credit_limit_type'] ?? 'ilimitado') === 'ilimitado' ? 'selected' : '' ?>>Ilimitado</option>
            <option value="zero" <?= ($values['credit_limit_type'] ?? '') === 'zero' ? 'selected' : '' ?>>Zero (sem crédito)</option>
            <option value="valor" <?= ($values['credit_limit_type'] ?? '') === 'valor' ? 'selected' : '' ?>>Valor definido</option>
        </select>
    </div>
    <div>
        <label for="credit_limit_value">Valor do limite (R$)</label>
        <input type="number" step="0.01" id="credit_limit_value" name="credit_limit_value" value="<?= View::e((string) ($values['credit_limit_value'] ?? '')) ?>" placeholder="Só se limite = Valor definido">
    </div>
</div>

<label for="payment_terms">Condição de pagamento</label>
<input type="text" id="payment_terms" name="payment_terms" value="<?= View::e($values['payment_terms'] ?? '') ?>" placeholder="Ex: 30/60/90 dias, à vista...">

<label for="seller_id">Vendedor vinculado</label>
<select id="seller_id" name="seller_id">
    <option value="">Sem vendedor</option>
    <?php foreach ($sellers as $s): ?>
        <option value="<?= (int) $s['id'] ?>" <?= (int) ($values['seller_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
    <?php endforeach; ?>
</select>

<label for="status">Status</label>
<select id="status" name="status">
    <option value="ativo" <?= ($values['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
    <option value="inativo" <?= ($values['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
</select>
