<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
?>
<label for="name">Nome / Razão social</label>
<input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
<p class="field-error" data-error-for="name"><?= View::e($errors['name'] ?? '') ?></p>

<label for="document">CPF/CNPJ</label>
<input type="text" id="document" name="document" value="<?= View::e($values['document'] ?? '') ?>">

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

<label for="status">Status</label>
<select id="status" name="status">
    <option value="ativo" <?= ($values['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
    <option value="inativo" <?= ($values['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
</select>
