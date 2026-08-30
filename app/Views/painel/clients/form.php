<?php
use App\Core\Csrf;
use App\Core\View;
$isEdit = $editing !== null;
$action = $isEdit ? '/painel/clientes/' . (int) $editing['id'] : '/painel/clientes';
$values = $editing ?? ($old ?? []);
$redirectTo = $_GET['redirect_to'] ?? '';
?>
<h1><?= $isEdit ? 'Editar cliente' : 'Novo cliente' ?></h1>

<form action="<?= $action ?><?= $redirectTo ? '?redirect_to=' . urlencode($redirectTo) : '' ?>" method="post" class="panel-form">
    <?= Csrf::field() ?>

    <label for="name">Nome / Razão social</label>
    <input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
    <?php if (!empty($errors['name'])): ?><p class="field-error"><?= View::e($errors['name']) ?></p><?php endif; ?>

    <label for="document">CPF/CNPJ</label>
    <input type="text" id="document" name="document" value="<?= View::e($values['document'] ?? '') ?>">

    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" value="<?= View::e($values['email'] ?? '') ?>">
    <?php if (!empty($errors['email'])): ?><p class="field-error"><?= View::e($errors['email']) ?></p><?php endif; ?>

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

    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="/painel/clientes" class="btn btn-outline">Cancelar</a>
</form>
