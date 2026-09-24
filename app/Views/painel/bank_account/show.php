<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $user */
/** @var array $errors */
$values = $user;
$isReview = !empty($user['bank_data_completed_at']) && !$errors;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= $isReview ? 'Meus dados bancários' : 'Dados bancários obrigatórios' ?> — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box auth-box-wide">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <?php if ($isReview): ?>
        <h1>Meus dados bancários</h1>
        <p class="auth-hint">Dados usados pra você receber suas comissões. Pode atualizar quando precisar.</p>
    <?php else: ?>
        <h1>Dados bancários obrigatórios</h1>
        <p class="auth-hint">Antes de liberar o acesso completo ao painel, precisamos dos dados bancários/Pix pra onde suas comissões serão pagas. Sem isso preenchido não dá pra continuar.</p>
    <?php endif; ?>

    <?php if ($isReview): ?>
        <p><a href="/painel">← Voltar pro painel</a></p>
    <?php endif; ?>

    <form action="/painel/dados-bancarios" method="post" class="auth-form">
        <?= Csrf::field() ?>

        <div class="form-grid-2">
            <div>
                <label for="bank_code">Código do banco</label>
                <input type="text" id="bank_code" name="bank_code" value="<?= View::e($values['bank_code'] ?? '') ?>" placeholder="Ex: 341" required>
                <?php if (!empty($errors['bank_code'])): ?><p class="field-error"><?= View::e($errors['bank_code']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="bank_name">Banco</label>
                <input type="text" id="bank_name" name="bank_name" value="<?= View::e($values['bank_name'] ?? '') ?>" placeholder="Ex: Itaú" required>
                <?php if (!empty($errors['bank_name'])): ?><p class="field-error"><?= View::e($errors['bank_name']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="bank_agency">Agência</label>
                <input type="text" id="bank_agency" name="bank_agency" value="<?= View::e($values['bank_agency'] ?? '') ?>" required>
                <?php if (!empty($errors['bank_agency'])): ?><p class="field-error"><?= View::e($errors['bank_agency']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="bank_account_type">Tipo de conta</label>
                <select id="bank_account_type" name="bank_account_type" required>
                    <option value="">Selecione...</option>
                    <option value="corrente" <?= ($values['bank_account_type'] ?? '') === 'corrente' ? 'selected' : '' ?>>Conta corrente</option>
                    <option value="poupanca" <?= ($values['bank_account_type'] ?? '') === 'poupanca' ? 'selected' : '' ?>>Conta poupança</option>
                </select>
                <?php if (!empty($errors['bank_account_type'])): ?><p class="field-error"><?= View::e($errors['bank_account_type']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="bank_account">Conta</label>
                <input type="text" id="bank_account" name="bank_account" value="<?= View::e($values['bank_account'] ?? '') ?>" required>
                <?php if (!empty($errors['bank_account'])): ?><p class="field-error"><?= View::e($errors['bank_account']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="bank_account_digit">Dígito</label>
                <input type="text" id="bank_account_digit" name="bank_account_digit" maxlength="5" value="<?= View::e($values['bank_account_digit'] ?? '') ?>" required>
                <?php if (!empty($errors['bank_account_digit'])): ?><p class="field-error"><?= View::e($errors['bank_account_digit']) ?></p><?php endif; ?>
            </div>
        </div>

        <label for="pix_key">Chave Pix</label>
        <input type="text" id="pix_key" name="pix_key" value="<?= View::e($values['pix_key'] ?? '') ?>" placeholder="CPF, e-mail, telefone ou chave aleatória" required>
        <?php if (!empty($errors['pix_key'])): ?><p class="field-error"><?= View::e($errors['pix_key']) ?></p><?php endif; ?>

        <label for="payment_document">CPF ou CNPJ (titular da conta)</label>
        <input type="text" id="payment_document" name="payment_document" value="<?= View::e($values['payment_document'] ?? '') ?>" placeholder="000.000.000-00" required>
        <?php if (!empty($errors['payment_document'])): ?><p class="field-error"><?= View::e($errors['payment_document']) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary" style="margin-top:16px;">Salvar</button>
    </form>
</div>
</body>
</html>
