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
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1><?= View::e($title) ?></h1>
    <p class="auth-hint"><?= View::e($text) ?></p>

    <?php if ($showWidget): ?>
        <div id="clicksign-widget-container" style="width:100%;height:85vh;min-height:700px;border-radius:12px;overflow:hidden;margin-top:10px;"></div>
        <p class="hint-text" id="clicksign-poll-hint" style="display:none;">Confirmando sua assinatura com o ClickSign...</p>
        <script src="https://cdn-public-library.clicksign.com/embedded/embedded.min-2.1.0.js"></script>
        <script>
        (function () {
            var widget = new Clicksign(<?= json_encode($envelope['clicksign_signer_id']) ?>);
            widget.endpoint = <?= json_encode($clicksignBaseUrl) ?>;
            widget.origin = window.location.origin;

            var polling = false;
            var attempts = 0;
            var MAX_ATTEMPTS = 8;

            function pollStatus() {
                if (polling) return;
                polling = true;
                var hint = document.getElementById('clicksign-poll-hint');
                if (hint) hint.style.display = 'block';
                attempt();
            }

            function attempt() {
                attempts++;
                var form = document.getElementById('clicksign-refresh-form');
                var formData = new FormData(form);
                fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        // O widget as vezes reporta "assinado" no navegador um pouco antes do
                        // ClickSign fechar o envelope de fato -- tenta de novo com espaçamento
                        // crescente em vez de desistir na primeira checagem.
                        if (data.status && data.status !== 'aguardando_assinatura') {
                            window.location.reload();
                            return;
                        }
                        if (attempts < MAX_ATTEMPTS) {
                            setTimeout(attempt, Math.min(3000 * attempts, 12000));
                        } else {
                            polling = false;
                            var hint = document.getElementById('clicksign-poll-hint');
                            if (hint) hint.textContent = 'Já assinou? Clique em "Verificar novamente" abaixo.';
                        }
                    })
                    .catch(function () {
                        polling = false;
                    });
            }

            widget.on('signed', pollStatus);
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
