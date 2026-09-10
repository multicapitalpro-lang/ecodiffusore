<?php
use App\Core\Csrf;
use App\Core\View;
$desconectado = isset($_GET['desconectado']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>💬 Meu WhatsApp</h1>
</div>

<p class="hint-text" style="margin-top:0;">Conecte seu próprio número pra conversar com seus leads direto pelo painel — mensagens, grupos e histórico ficam organizados aqui, com tags pra você classificar cada conversa.</p>

<?php if ($desconectado): ?>
    <p class="form-msg form-msg-ok">Aparelho desconectado.</p>
<?php endif; ?>
<?php if ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir. Tente novamente.</p>
<?php endif; ?>

<div class="settings-card" style="max-width:480px">
    <?php if (!$instance): ?>
        <h3 class="section-title">Conectar meu número</h3>
        <p>Você ainda não conectou seu WhatsApp aqui.</p>
        <form method="post" action="/painel/whatsapp/conectar">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary">Conectar meu WhatsApp</button>
        </form>
    <?php else: ?>
        <h3 class="section-title">Conexão do aparelho</h3>
        <p>Status: <strong id="wa-status">Verificando...</strong></p>

        <div id="wa-qr-wrap" hidden style="text-align:center;margin:16px 0">
            <img id="wa-qr" alt="QR Code do WhatsApp" style="max-width:280px;border:1px solid #ddd;border-radius:8px">
            <p class="hint-text">Abra o WhatsApp do celular que você vai usar → Configurações → Aparelhos conectados → Conectar um aparelho, e escaneie. O código se renova automaticamente.</p>
        </div>

        <div id="wa-connected-wrap" hidden>
            <p class="form-msg form-msg-ok">Conectado! Suas conversas já estão sendo sincronizadas.</p>
            <a href="/painel/whatsapp/conversas" class="btn btn-primary">Abrir minhas conversas</a>
            <form method="post" action="/painel/whatsapp/desconectar" class="inline-form" style="margin-top:10px;" onsubmit="return confirm('Desconectar seu WhatsApp? Vai precisar escanear o QR Code de novo pra reconectar.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline">Desconectar</button>
            </form>
        </div>

        <div id="wa-error-wrap" hidden>
            <p class="form-msg form-msg-erro">Não foi possível falar com o servidor do WhatsApp. Tente novamente em alguns instantes.</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($instance): ?>
<script>
(function () {
    var statusEl = document.getElementById('wa-status');
    var qrWrap = document.getElementById('wa-qr-wrap');
    var qrImg = document.getElementById('wa-qr');
    var connectedWrap = document.getElementById('wa-connected-wrap');
    var errorWrap = document.getElementById('wa-error-wrap');
    var stopped = false;

    function poll() {
        if (stopped) return;
        fetch('/painel/whatsapp/status')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                errorWrap.hidden = true;

                if (data.state === 'open') {
                    statusEl.textContent = 'Conectado';
                    qrWrap.hidden = true;
                    connectedWrap.hidden = false;
                    stopped = true;
                    return;
                }

                statusEl.textContent = data.state === 'connecting' ? 'Aguardando leitura do QR Code' : 'Desconectado';
                connectedWrap.hidden = true;
                if (data.qrcode_base64) {
                    qrImg.src = data.qrcode_base64;
                    qrWrap.hidden = false;
                }
                setTimeout(poll, 5000);
            })
            .catch(function () {
                errorWrap.hidden = false;
                setTimeout(poll, 8000);
            });
    }

    poll();
})();
</script>
<?php endif; ?>
