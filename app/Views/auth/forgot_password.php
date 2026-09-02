<?php
use App\Core\Csrf;
use App\Core\View;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recuperar senha — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">

    <?php if ($step === 'sent'): ?>
        <h1>Verifique seu e-mail</h1>
        <p class="auth-hint">Se <?= View::e($email) ?> tiver uma conta de cliente, enviamos um código de 6 dígitos para redefinir a senha. O código vale por 15 minutos.</p>
        <a href="/painel/redefinir-senha?email=<?= urlencode($email) ?>" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:10px;">Já tenho o código</a>
        <a class="auth-back" href="/painel/login">&larr; Voltar para o login</a>
    <?php else: ?>
        <h1>Esqueceu sua senha?</h1>
        <p class="auth-hint">Disponível apenas para contas de cliente. Informe seu e-mail que enviaremos um código de verificação.</p>
        <form action="/painel/esqueci-senha" method="post" class="auth-form">
            <?= Csrf::field() ?>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus>
            <button type="submit" class="btn btn-primary">Enviar código</button>
        </form>
        <a class="auth-back" href="/painel/login">&larr; Voltar para o login</a>
    <?php endif; ?>
</div>
</body>
</html>
