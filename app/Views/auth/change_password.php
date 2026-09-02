<?php
use App\Core\Csrf;
use App\Core\View;
$erro = $_GET['erro'] ?? null;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Trocar senha — Painel Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Defina sua nova senha</h1>
    <p class="auth-hint">Por segurança, você precisa criar uma senha nova antes de continuar.</p>
    <?php if ($erro === '1'): ?>
        <p class="form-msg form-msg-erro">Sessão inválida, tente novamente.</p>
    <?php elseif ($erro === '2'): ?>
        <p class="form-msg form-msg-erro">As senhas não conferem ou têm menos de 8 caracteres.</p>
    <?php endif; ?>
    <form action="/painel/trocar-senha" method="post" class="auth-form">
        <?= Csrf::field() ?>
        <label for="password">Nova senha</label>
        <div class="password-field">
            <input type="password" id="password" name="password" minlength="8" required autofocus>
            <?= View::passwordToggle('password') ?>
        </div>
        <label for="password_confirm">Confirme a nova senha</label>
        <div class="password-field">
            <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
            <?= View::passwordToggle('password_confirm') ?>
        </div>
        <button type="submit" class="btn btn-primary">Salvar senha</button>
    </form>
</div>
<script src="<?= View::asset('/assets/js/password-toggle.js') ?>"></script>
</body>
</html>
