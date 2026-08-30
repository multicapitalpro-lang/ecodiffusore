<?php
use App\Core\Csrf;
use App\Core\View;
$isEdit = $editing !== null;
$action = $isEdit ? '/painel/produtos/' . (int) $editing['id'] : '/painel/produtos';
$values = $editing ?? ($old ?? []);
?>
<h1><?= $isEdit ? 'Editar produto' : 'Novo produto' ?></h1>

<form action="<?= $action ?>" method="post" class="panel-form">
    <?= Csrf::field() ?>

    <label for="sku">SKU</label>
    <input type="text" id="sku" name="sku" value="<?= View::e($values['sku'] ?? '') ?>" required>
    <?php if (!empty($errors['sku'])): ?><p class="field-error"><?= View::e($errors['sku']) ?></p><?php endif; ?>

    <label for="name">Nome</label>
    <input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
    <?php if (!empty($errors['name'])): ?><p class="field-error"><?= View::e($errors['name']) ?></p><?php endif; ?>

    <label for="price_cash">Preço à vista (R$)</label>
    <input type="number" step="0.01" id="price_cash" name="price_cash" value="<?= View::e((string) ($values['price_cash'] ?? '')) ?>" required>
    <?php if (!empty($errors['price_cash'])): ?><p class="field-error"><?= View::e($errors['price_cash']) ?></p><?php endif; ?>

    <label for="price_installment">Valor da parcela 6x (R$)</label>
    <input type="number" step="0.01" id="price_installment" name="price_installment" value="<?= View::e((string) ($values['price_installment'] ?? '')) ?>" required>
    <?php if (!empty($errors['price_installment'])): ?><p class="field-error"><?= View::e($errors['price_installment']) ?></p><?php endif; ?>

    <label for="cost_price">Custo (R$)</label>
    <input type="number" step="0.01" id="cost_price" name="cost_price" value="<?= View::e((string) ($values['cost_price'] ?? '0')) ?>">

    <label class="checkbox-label">
        <input type="checkbox" name="active" value="1" <?= (($values['active'] ?? 1)) ? 'checked' : '' ?>> Ativo
    </label>

    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="/painel/produtos" class="btn btn-outline">Cancelar</a>
</form>
