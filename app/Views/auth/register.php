<?php
use App\Core\Csrf;
use App\Core\View;
$old = $old ?? [];
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar conta — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="/assets/img/logo.svg" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Criar minha conta de cliente</h1>
    <p class="auth-hint">Cadastro para clientes compradores. Se você é licenciado, gerente, supervisor ou admin, peça ao administrador para criar seu acesso.</p>
    <form action="/painel/cadastro" method="post" class="auth-form">
        <?= Csrf::field() ?>
        <label for="name">Nome completo</label>
        <input type="text" id="name" name="name" value="<?= View::e($old['name'] ?? '') ?>" required>
        <?php if (!empty($errors['name'])): ?><p class="field-error"><?= View::e($errors['name']) ?></p><?php endif; ?>

        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= View::e($old['email'] ?? '') ?>" required>
        <?php if (!empty($errors['email'])): ?><p class="field-error"><?= View::e($errors['email']) ?></p><?php endif; ?>

        <label for="whatsapp">WhatsApp</label>
        <input type="text" id="whatsapp" name="whatsapp" value="<?= View::e($old['whatsapp'] ?? '') ?>" required>
        <?php if (!empty($errors['whatsapp'])): ?><p class="field-error"><?= View::e($errors['whatsapp']) ?></p><?php endif; ?>

        <label for="password">Senha</label>
        <input type="password" id="password" name="password" minlength="8" required>
        <label for="password_confirm">Confirme a senha</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
        <?php if (!empty($errors['password'])): ?><p class="field-error"><?= View::e($errors['password']) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary">Criar conta</button>
    </form>
    <a class="auth-back" href="/painel/login">Já tenho conta — entrar</a>
</div>
</body>
</html>
