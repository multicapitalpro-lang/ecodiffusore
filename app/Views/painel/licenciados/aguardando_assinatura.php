<?php
use App\Core\Csrf;
use App\Core\View;
$status = $user['licenciado_onboarding_status'];
$messages = [
    'aguardando_assinatura' => ['Falta pouco!', 'Assine o contrato e confirme sua identidade pelo link abaixo.'],
    'aguardando_aprovacao' => ['Documentos enviados!', 'Seu contrato foi assinado e sua identidade confirmada. Seu cadastro está em análise — avisamos assim que for aprovado.'],
    'assinatura_recusada' => ['Assinatura recusada', 'A assinatura do contrato foi recusada. Fale com quem cadastrou você ou tente novamente.'],
    'kyc_recusado' => ['Verificação de identidade não confirmada', 'Não conseguimos confirmar sua identidade pelos documentos enviados. Tente novamente pelo link abaixo.'],
];
[$title, $text] = $messages[$status] ?? ['Aguardando', 'Aguardando confirmação.'];
$showSigningLink = in_array($status, ['aguardando_assinatura', 'assinatura_recusada', 'kyc_recusado'], true);
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Aguardando assinatura — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box">
    <img src="<?= View::asset('/assets/img/logo-mark.svg') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1><?= View::e($title) ?></h1>
    <p class="auth-hint"><?= View::e($text) ?></p>

    <?php if ($showSigningLink && !empty($envelope['signing_url'])): ?>
        <a href="<?= View::e($envelope['signing_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:10px;">Assinar contrato e confirmar identidade</a>
    <?php endif; ?>

    <form action="/painel/licenciados/aguardando-assinatura/verificar" method="post" class="inline-form" style="display:block;margin-top:14px;">
        <?= Csrf::field() ?>
        <button type="submit" class="link-button"><?= $showSigningLink ? 'Já assinei — verificar novamente' : 'Verificar se já foi aprovado' ?></button>
    </form>

    <a class="auth-back" href="/painel/logout">Sair</a>
</div>
</body>
</html>
