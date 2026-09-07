<?php
use App\Core\Csrf;
use App\Core\View;
$desconectado = isset($_GET['desconectado']);
?>
<div class="page-header">
    <h1>WhatsApp</h1>
</div>

<?php if ($desconectado): ?>
    <p class="form-msg form-msg-ok">Aparelho desconectado.</p>
<?php endif; ?>

<div class="settings-card" style="max-width:480px">
    <p>Status: <strong id="wa-status">Verificando...</strong></p>

    <div id="wa-qr-wrap" hidden style="text-align:center;margin:16px 0">
        <img id="wa-qr" alt="QR Code do WhatsApp" style="max-width:280px;border:1px solid #ddd;border-radius:8px">
        <p class="hint-text">Abra o WhatsApp do aparelho que vai usar → Configurações → Aparelhos conectados → Conectar um aparelho, e escaneie. O código se renova automaticamente.</p>
    </div>

    <div id="wa-connected-wrap" hidden>
        <p class="form-msg form-msg-ok">Aparelho conectado e pronto pra enviar mensagens.</p>
        <form method="post" action="/painel/configuracoes/whatsapp/desconectar" class="inline-form" onsubmit="return confirm('Desconectar o aparelho atual? Vai precisar escanear o QR Code de novo.');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-outline">Desconectar</button>
        </form>
    </div>

    <div id="wa-error-wrap" hidden>
        <p class="form-msg form-msg-error">Não foi possível falar com o servidor do WhatsApp. Tente novamente em alguns instantes.</p>
    </div>
</div>

<script>
(function () {
    var statusEl = document.getElementById('wa-status');
    var qrWrap = document.getElementById('wa-qr-wrap');
    var qrImg = document.getElementById('wa-qr');
    var connectedWrap = document.getElementById('wa-connected-wrap');
    var errorWrap = document.getElementById('wa-error-wrap');

    function poll() {
        fetch('/painel/configuracoes/whatsapp/status')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                errorWrap.hidden = true;

                if (data.state === 'open') {
                    statusEl.textContent = 'Conectado';
                    qrWrap.hidden = true;
                    connectedWrap.hidden = false;
                } else {
                    statusEl.textContent = data.state === 'connecting' ? 'Aguardando leitura do QR Code' : 'Desconectado';
                    connectedWrap.hidden = true;
                    if (data.qrcode_base64) {
                        qrImg.src = data.qrcode_base64;
                        qrWrap.hidden = false;
                    }
                }
            })
            .catch(function () {
                statusEl.textContent = 'Erro';
                errorWrap.hidden = false;
            });
    }

    poll();
    setInterval(poll, 5000);
})();
</script>
