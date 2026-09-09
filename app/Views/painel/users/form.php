<?php
use App\Core\Csrf;
use App\Core\ScreenPermissions;
use App\Core\View;
$isEdit = $editing !== null;
$isModal = $isModal ?? false;
$action = $isEdit ? '/painel/usuarios/' . (int) $editing['id'] : '/painel/usuarios';
$values = $editing ?? ($old ?? []);
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <h1><?= $isEdit ? 'Editar usuário' : 'Novo usuário' ?></h1>
<?php endif; ?>

<form action="<?= $action ?>" method="post" class="panel-form<?= $isModal ? ' ajax-form' : '' ?>">
    <?= Csrf::field() ?>

    <label for="name">Nome</label>
    <input type="text" id="name" name="name" value="<?= View::e($values['name'] ?? '') ?>" required>
    <p class="field-error" data-error-for="name"><?= !empty($errors['name']) ? View::e($errors['name']) : '' ?></p>

    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" value="<?= View::e($values['email'] ?? '') ?>" required>
    <p class="field-error" data-error-for="email"><?= !empty($errors['email']) ? View::e($errors['email']) : '' ?></p>

    <label for="whatsapp">WhatsApp</label>
    <input type="text" id="whatsapp" name="whatsapp" value="<?= View::e($values['whatsapp'] ?? '') ?>">

    <div class="form-grid-2">
        <div style="position:relative;">
            <label for="city">Cidade</label>
            <input type="text" id="city" name="city" value="<?= View::e($values['city'] ?? '') ?>" autocomplete="off" data-city-autocomplete data-city-uf-target="state">
            <div class="autocomplete-results" hidden></div>
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
            <option value="<?= (int) $role['id'] ?>" data-slug="<?= View::e($role['slug']) ?>" <?= (int) ($values['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                <?= View::e($role['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="field-error" data-error-for="role_id"><?= !empty($errors['role_id']) ? View::e($errors['role_id']) : '' ?></p>

    <?php if (!empty($supervisors)): ?>
        <div id="supervisor-field-wrap" style="display:none;">
            <label for="supervisor_id">Supervisor responsável</label>
            <select id="supervisor_id" name="supervisor_id">
                <option value="">— Nenhum (atribuir depois) —</option>
                <?php foreach ($supervisors as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($values['supervisor_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= View::e($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint-text">Só se aplica a Licenciado — quem cuida dele e ajuda a aumentar as vendas.</p>
        </div>
    <?php endif; ?>

    <label for="manager_id">Reporta para</label>
    <select id="manager_id" name="manager_id" <?= count($managers) <= 1 ? 'disabled' : '' ?>>
        <?php if ($user['role_slug'] === 'admin'): ?><option value="">Ninguém (topo da hierarquia)</option><?php endif; ?>
        <?php foreach ($managers as $m): ?>
            <option value="<?= (int) $m['id'] ?>" <?= (int) ($values['manager_id'] ?? ($user['role_slug'] !== 'admin' ? $managers[0]['id'] : 0)) === (int) $m['id'] ? 'selected' : '' ?>>
                <?php
                $managerRoleLabels = ['gestor' => 'Gestor', 'licenciado' => 'Licenciado', 'gerente' => 'Gerente', 'supervisor' => 'Supervisor'];
                ?>
                <?= View::e($m['name']) ?> (<?= $managerRoleLabels[$m['role_slug']] ?? $m['role_slug'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <p class="field-error" data-error-for="manager_id"><?= !empty($errors['manager_id']) ? View::e($errors['manager_id']) : '' ?></p>

    <?php if ($canSetCommission): ?>
        <div id="commission-pct-wrap">
            <label for="commission_pct">Comissão desta pessoa (%)</label>
            <input type="number" id="commission_pct" name="commission_pct" step="0.01" min="0" max="100"
                   value="<?= View::e((string) ($values['commission_pct'] ?? '')) ?>" placeholder="Ex: 15.00">
            <p class="hint-text">Para gestor: % do pool do licenciado que será repassado a esta pessoa. Para gerente/supervisor: % do total do pedido pago direto pela Ecodiffusore (não sai do pool de ninguém). Para vendedor: usado só se a venda não tiver faixa de preço configurada abaixo.</p>
        </div>

        <div id="licenciado-commission-note" style="display:none;">
            <p class="hint-text" style="margin-top:0;">A comissão do Licenciado agora vem sempre da <a href="/painel/tabela-precos" target="_blank">tabela de preços por quantidade</a> — não é mais definida aqui.</p>
        </div>

        <div id="vendedor-commission-wrap" style="display:none;">
            <label>Como pagar este Vendedor por venda?</label>
            <label class="checkbox-label">
                <input type="radio" name="commission_type" value="percentual" <?= ($values['commission_type'] ?? 'percentual') === 'percentual' ? 'checked' : '' ?>> % da venda
            </label>
            <label class="checkbox-label">
                <input type="radio" name="commission_type" value="fixo" <?= ($values['commission_type'] ?? '') === 'fixo' ? 'checked' : '' ?>> Valor fixo (R$) por unidade
            </label>

            <table class="data-table" style="margin-top:10px;">
                <thead><tr><th>A partir de</th><th>Preço unitário</th><th>Comissão do vendedor</th></tr></thead>
                <tbody>
                    <?php foreach ($pricingTiers as $tier): ?>
                        <tr>
                            <td><?= (int) $tier['min_qty'] ?> placa<?= (int) $tier['min_qty'] > 1 ? 's' : '' ?></td>
                            <td>R$ <?= number_format((float) $tier['unit_price'], 2, ',', '.') ?></td>
                            <td>
                                <input type="number" name="commission_tier_<?= (int) $tier['id'] ?>" step="0.01" min="0"
                                       value="<?= View::e((string) ($vendorTierValues[$tier['id']] ?? '')) ?>" style="width:100px;">
                                <p class="field-error" data-error-for="commission_tier_<?= (int) $tier['id'] ?>"><?= View::e($errors['commission_tier_' . $tier['id']] ?? '') ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="hint-text">Um valor por faixa (vazio = essa faixa cai no % de comissão padrão acima). Sai do pool que o Licenciado recebe da Ecodiffusore — não é um custo adicional.</p>
        </div>
    <?php endif; ?>

    <?php
    $allowedScreens = $allowedScreens ?? null;
    $checkedScreens = $allowedScreens === null ? array_keys(ScreenPermissions::SCREENS) : $allowedScreens;
    ?>
    <div id="screens-permissions-wrap" style="display:none;">
        <label>Telas que esta pessoa pode ver</label>
        <p class="hint-text" style="margin-top:0;">Restringe o menu e o acesso direto por link — desmarque só o que essa pessoa não deve ver.</p>
        <?php foreach (ScreenPermissions::SCREENS as $key => $label): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="screens[]" value="<?= View::e($key) ?>" <?= in_array($key, $checkedScreens, true) ? 'checked' : '' ?>> <?= View::e($label) ?>
            </label>
        <?php endforeach; ?>
    </div>

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
        <p class="field-error" data-error-for="password"><?= !empty($errors['password']) ? View::e($errors['password']) : '' ?></p>
    <?php else: ?>
        <label class="checkbox-label">
            <input type="checkbox" name="reset_password" value="1"> Gerar nova senha temporária para este usuário
        </label>
    <?php endif; ?>

    <div class="<?= $isModal ? 'modal-form-actions' : '' ?>">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <?php if ($isModal): ?>
            <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
        <?php else: ?>
            <a href="/painel/usuarios" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
    </div>
</form>
<?php if ($isModal): ?></div><?php endif; ?>
