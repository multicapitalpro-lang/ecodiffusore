<?php
use App\Core\Csrf;
use App\Core\View;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirme seu e-mail — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Confirme seu cadastro</h1>
    <p class="auth-hint">Enviamos um código de 6 dígitos para o seu e-mail. Digite abaixo para confirmar sua conta.</p>

    <?php if ($erro === '1'): ?>
        <p class="form-msg form-msg-erro">Sessão inválida, tente novamente.</p>
    <?php elseif ($erro === '2'): ?>
        <p class="form-msg form-msg-erro">Código inválido ou expirado.</p>
    <?php elseif ($enviado): ?>
        <p class="form-msg form-msg-ok">Novo código enviado para o seu e-mail.</p>
    <?php endif; ?>

    <form action="/painel/verificar-email" method="post" class="auth-form">
        <?= Csrf::field() ?>
        <label for="code">Código de confirmação</label>
        <input type="text" id="code" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required autofocus>
        <button type="submit" class="btn btn-primary">Confirmar</button>
    </form>

    <form action="/painel/verificar-email/reenviar" method="post" class="inline-form" style="display:block;margin-top:14px;">
        <?= Csrf::field() ?>
        <button type="submit" class="link-button">Reenviar código</button>
    </form>

    <a class="auth-back" href="/painel/logout">Sair</a>
</div>
</body>
</html>
