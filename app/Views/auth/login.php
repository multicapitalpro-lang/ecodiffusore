<?php
use App\Core\Csrf;
$erro = isset($_GET['erro']);
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Entrar — Painel Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/css/painel.css">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="/assets/img/logo.svg" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Área do Cliente / Licenciado</h1>
    <?php if ($erro): ?>
        <p class="form-msg form-msg-erro">E-mail ou senha inválidos.</p>
    <?php endif; ?>
    <form action="/painel/login" method="post" class="auth-form">
        <?= Csrf::field() ?>
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus>
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required>
        <button type="submit" class="btn btn-primary">Entrar</button>
    </form>
    <a class="auth-back" href="/">&larr; Voltar para o site</a>
</div>
</body>
</html>
