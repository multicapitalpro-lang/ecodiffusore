<?php
use App\Core\Csrf;
use App\Core\View;
$status = $user['licenciado_onboarding_status'];
$messages = [
    'aguardando_assinatura' => ['Falta pouco!', 'Assine o contrato e confirme sua identidade abaixo.'],
    'aguardando_aprovacao' => ['Documentos enviados!', 'Seu contrato foi assinado e sua identidade confirmada. Seu cadastro está em análise — avisamos assim que for aprovado.'],
    'assinatura_recusada' => ['Assinatura recusada', 'A assinatura do contrato foi recusada. Fale com quem cadastrou você.'],
    'kyc_recusado' => ['Verificação de identidade não confirmada', 'Não conseguimos confirmar sua identidade pelos documentos enviados. Fale com quem cadastrou você.'],
];
[$title, $text] = $messages[$status] ?? ['Aguardando', 'Aguardando confirmação.'];
$showWidget = $status === 'aguardando_assinatura' && !empty($envelope['clicksign_signer_id']);
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Aguardando assinatura — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box <?= $showWidget ? 'auth-box-wide' : '' ?>">
    <img src="<?= View::asset('/assets/img/logo-mark.svg') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1><?= View::e($title) ?></h1>
    <p class="auth-hint"><?= View::e($text) ?></p>

    <?php if ($showWidget): ?>
        <div id="clicksign-widget-container" style="width:100%;height:600px;border-radius:12px;overflow:hidden;margin-top:10px;"></div>
        <script src="https://cdn-public-library.clicksign.com/embedded/embedded.min-2.1.0.js"></script>
        <script>
        (function () {
            var widget = new Clicksign(<?= json_encode($envelope['clicksign_signer_id']) ?>);
            widget.endpoint = <?= json_encode($clicksignBaseUrl) ?>;
            widget.origin = window.location.origin;
            widget.on('signed', function () {
                document.getElementById('clicksign-refresh-form').submit();
            });
            widget.mount('clicksign-widget-container');
        })();
        </script>
    <?php endif; ?>

    <form id="clicksign-refresh-form" action="/painel/licenciados/aguardando-assinatura/verificar" method="post" class="inline-form" style="display:block;margin-top:14px;">
        <?= Csrf::field() ?>
        <button type="submit" class="link-button"><?= $showWidget ? 'Já assinei — verificar novamente' : 'Verificar novamente' ?></button>
    </form>

    <a class="auth-back" href="/painel/logout">Sair</a>
</div>
</body>
</html>
