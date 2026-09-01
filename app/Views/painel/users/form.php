<?php
use App\Core\Csrf;
use App\Core\View;
$isEdit = $editing !== null;
$action = $isEdit ? '/painel/usuarios/' . (int) $editing['id'] : '/painel/usuarios';
$values = $editing ?? ($old ?? []);
?>
<h1><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>

<form action="<?= $action ?>" method="post" class="panel-form">
    <?= Csrf::field() ?>

    <label for="name">Nome</label>
    <input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
    <?php if (!empty($errors['name'])): ?><p class="field-error"><?= View::e($errors['name']) ?></p><?php endif; ?>

    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" value="<?= View::e($values['email'] ?? '') ?>" required>
    <?php if (!empty($errors['email'])): ?><p class="field-error"><?= View::e($errors['email']) ?></p><?php endif; ?>

    <label for="whatsapp">WhatsApp</label>
    <input type="text" id="whatsapp" name="whatsapp" value="<?= View::e($values['whatsapp'] ?? '') ?>">

    <div class="form-grid-2">
        <div>
            <label for="city">Cidade</label>
            <input type="text" id="city" name="city" value="<?= View::e($values['city'] ?? '') ?>">
        </div>
        <div>
            <label for="state">UF</label>
            <input type="text" id="state" name="state" maxlength="2" style="text-transform:uppercase" value="<?= View::e($values['state'] ?? '') ?>">
        </div>
    </div>
    <p class="hint-text">Pra Licenciado: usado pra achar automaticamente o vendedor mais próximo de um cliente que pede orçamento pela landing page.</p>

    <label for="role_id">Papel</label>
    <select id="role_id" name="role_id" required>
        <option value="">Selecione...</option>
        <?php foreach ($roles as $role): ?>
            <option value="<?= (int) $role['id'] ?>" <?= (int) ($values['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                <?= View::e($role['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($errors['role_id'])): ?><p class="field-error"><?= View::e($errors['role_id']) ?></p><?php endif; ?>

    <label for="manager_id">Reporta para</label>
    <select id="manager_id" name="manager_id" <?= count($managers) <= 1 ? 'disabled' : '' ?>>
        <?php if ($user['role_slug'] === 'admin'): ?><option value="">Ninguém (topo da hierarquia)</option><?php endif; ?>
        <?php foreach ($managers as $m): ?>
            <option value="<?= (int) $m['id'] ?>" <?= (int) ($values['manager_id'] ?? ($user['role_slug'] !== 'admin' ? $managers[0]['id'] : 0)) === (int) $m['id'] ? 'selected' : '' ?>>
                <?php
                $managerRoleLabels = ['gestor' => 'Gestor', 'licenciado' => 'Licenciado', 'gerente' => 'Gerente'];
                ?>
                <?= View::e($m['name']) ?> (<?= $managerRoleLabels[$m['role_slug']] ?? $m['role_slug'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!empty($errors['manager_id'])): ?><p class="field-error"><?= View::e($errors['manager_id']) ?></p><?php endif; ?>

    <?php if ($canSetCommission): ?>
        <label for="commission_pct">Comissão desta pessoa (%)</label>
        <input type="number" id="commission_pct" name="commission_pct" step="0.01" min="0" max="100"
               value="<?= View::e((string) ($values['commission_pct'] ?? '')) ?>" placeholder="Ex: 15.00">
        <p class="hint-text">Para licenciado: % fixo contratual sobre o total do pedido (define o "pool" da região). Para gestor/vendedor: % do pool do licenciado que será repassado a esta pessoa. Para gerente/supervisor: % do total do pedido pago direto pela Ecodiffusore (não sai do pool de ninguém).</p>
    <?php endif; ?>

    <label for="discount_limit_pct">Limite de desconto sem aprovação (%)</label>
    <input type="number" id="discount_limit_pct" name="discount_limit_pct" step="0.01" min="0" max="100"
           value="<?= View::e((string) ($values['discount_limit_pct'] ?? '')) ?>" placeholder="Vazio = sem limite (nunca precisa aprovar)">
    <p class="hint-text">Se o desconto do pedido/orçamento passar desse %, fica travado até um gestor/licenciado/admin aprovar.</p>

    <label for="status">Status</label>
    <select id="status" name="status">
        <option value="active" <?= ($values['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativo</option>
        <option value="inactive" <?= ($values['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
    </select>

    <?php if (!$isEdit): ?>
        <label for="password">Senha inicial</label>
        <div class="password-field">
            <input type="password" id="password" name="password" minlength="8" required>
            <?= View::passwordToggle('password') ?>
        </div>
        <?php if (!empty($errors['password'])): ?><p class="field-error"><?= View::e($errors['password']) ?></p><?php endif; ?>
    <?php else: ?>
        <label class="checkbox-label">
            <input type="checkbox" name="reset_password" value="1"> Gerar nova senha temporária para este usuário
        </label>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="/painel/usuarios" class="btn btn-outline">Cancelar</a>
</form>
