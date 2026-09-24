<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\WhatsAppEventTemplate;
$desconectado = isset($_GET['desconectado']);
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
?>
<div class="page-header">
    <h1>WhatsApp</h1>
</div>

<?php if ($desconectado): ?>
    <p class="form-msg form-msg-ok">Aparelho desconectado.</p>
<?php endif; ?>
<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<?php if ($user['role_slug'] === 'admin'): ?>
<div class="settings-card" style="max-width:480px">
    <h3 class="section-title">Conexão do aparelho</h3>
    <p class="hint-text">Esse é o número principal usado no site (o mesmo que envia os avisos automáticos do sistema). Conecte aqui o WhatsApp/WhatsApp Business desse número — assim que conectado, o <strong>menu de atendimento automático</strong> passa a responder sozinho quem mandar mensagem pra ele.</p>
    <p>Status: <strong id="wa-status">Verificando...</strong></p>

    <div id="wa-qr-wrap" hidden style="text-align:center;margin:16px 0">
        <img id="wa-qr" alt="QR Code do WhatsApp" style="max-width:280px;border:1px solid #ddd;border-radius:8px">
        <p class="hint-text">Abra o WhatsApp do aparelho que vai usar → Configurações → Aparelhos conectados → Conectar um aparelho, e escaneie. O código se renova automaticamente.</p>
    </div>

    <div id="wa-connected-wrap" hidden>
        <p class="form-msg form-msg-ok">Aparelho conectado e pronto pra enviar mensagens.</p>

        <form method="post" action="/painel/configuracoes/whatsapp/bot" class="inline-form" style="margin-bottom:10px">
            <?= Csrf::field() ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400">
                <input type="checkbox" name="enabled" value="1" id="wa-bot-toggle" onchange="this.form.submit()">
                Menu automático ativo (responde quem manda mensagem com o menu de opções)
            </label>
        </form>

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
    var botToggle = document.getElementById('wa-bot-toggle');

    function poll() {
        fetch('/painel/configuracoes/whatsapp/status')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                errorWrap.hidden = true;

                if (data.state === 'open') {
                    statusEl.textContent = 'Conectado';
                    qrWrap.hidden = true;
                    connectedWrap.hidden = false;
                    botToggle.checked = !!data.bot_enabled;
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
<?php endif; ?>

<h3 class="section-title" style="margin-top:32px">Textos das mensagens automáticas</h3>
<p class="hint-text">Use os placeholders indicados entre chaves — eles são substituídos pelos dados reais na hora do envio.</p>

<?php foreach (WhatsAppEventTemplate::KEYS as $key): ?>
    <?php
    $tpl = $templates[$key] ?? ['text_self' => '', 'text_network' => ''];
    $vars = WhatsAppEventTemplate::VARIABLES[$key];
    $selfOnly = in_array($key, WhatsAppEventTemplate::SELF_ONLY, true);
    $networkOnly = in_array($key, WhatsAppEventTemplate::NETWORK_ONLY, true);
    $err = $errors[$key] ?? [];
    ?>
    <details class="settings-card" style="max-width:640px;margin-bottom:12px">
        <summary style="cursor:pointer;font-weight:600"><?= View::e(WhatsAppEventTemplate::LABELS[$key]) ?></summary>
        <form method="post" action="/painel/configuracoes/whatsapp/evento/<?= $key ?>" style="margin-top:16px">
            <?= Csrf::field() ?>
            <p class="hint-text">Variáveis disponíveis: <?php foreach ($vars as $v): ?><code>{<?= $v ?>}</code> <?php endforeach; ?></p>

            <?php if (!$networkOnly): ?>
                <label>Mensagem pro: <?= View::e(WhatsAppEventTemplate::SELF_LABELS[$key] ?? 'Destinatário direto') ?></label>
                <textarea name="text_self" rows="4" style="width:100%"><?= View::e($tpl['text_self'] ?? '') ?></textarea>
                <?php if (!empty($err['text_self'])): ?><p class="form-msg form-msg-error"><?= View::e($err['text_self']) ?></p><?php endif; ?>
            <?php endif; ?>

            <?php if (!$selfOnly): ?>
                <label style="margin-top:12px;display:block">Mensagem pro: <?= View::e(WhatsAppEventTemplate::NETWORK_LABELS[$key] ?? 'Resto da rede') ?></label>
                <textarea name="text_network" rows="4" style="width:100%"><?= View::e($tpl['text_network'] ?? '') ?></textarea>
                <?php if (!empty($err['text_network'])): ?><p class="form-msg form-msg-error"><?= View::e($err['text_network']) ?></p><?php endif; ?>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
        </form>
    </details>
<?php endforeach; ?>
