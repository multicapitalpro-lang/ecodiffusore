<?php
use App\Core\Csrf;
use App\Core\View;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Redefinir senha — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="/assets/img/logo.svg" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Redefinir senha</h1>
    <p class="auth-hint">Digite o código recebido por e-mail e a nova senha.</p>
    <?php if (!empty($errors['code'])): ?>
        <p class="form-msg form-msg-erro"><?= View::e($errors['code']) ?></p>
    <?php endif; ?>
    <form action="/painel/redefinir-senha" method="post" class="auth-form">
        <?= Csrf::field() ?>
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= View::e($email) ?>" required>

        <label for="code">Código recebido</label>
        <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus>

        <label for="password">Nova senha</label>
        <input type="password" id="password" name="password" minlength="8" required>
        <label for="password_confirm">Confirme a nova senha</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
        <?php if (!empty($errors['password'])): ?><p class="field-error"><?= View::e($errors['password']) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary">Salvar nova senha</button>
    </form>
    <a class="auth-back" href="/painel/esqueci-senha">Não recebeu? Pedir novo código</a>
</div>
</body>
</html>
