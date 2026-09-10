<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>💬 Meu WhatsApp</h1>
    <div class="page-header-actions">
        <button type="button" class="btn btn-outline" data-modal-open="modal-wa-tag">+ Nova tag</button>
        <form method="post" action="/painel/whatsapp/sincronizar" class="inline-form">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-outline">🔄 Sincronizar</button>
        </form>
    </div>
</div>

<?php if ($sucesso): ?><p class="form-msg form-msg-ok">Sincronizado com sucesso.</p><?php endif; ?>
<?php if ($erro === 'sync'): ?><p class="form-msg form-msg-erro">Não foi possível sincronizar agora. Tente de novo em instantes.</p><?php endif; ?>

<div class="wa-inbox">
    <div class="wa-chat-list">
        <?php foreach ($chats as $c): ?>
            <a href="/painel/whatsapp/conversas/<?= (int) $c['id'] ?>" class="wa-chat-item <?= $activeChat && (int) $activeChat['id'] === (int) $c['id'] ? 'is-active' : '' ?>" data-wa-chat-link data-chat-id="<?= (int) $c['id'] ?>">
                <span class="wa-chat-avatar"><?= $c['is_group'] ? '👥' : '👤' ?></span>
                <span class="wa-chat-info">
                    <span class="wa-chat-name"><?= View::e($c['name'] ?: $c['remote_jid']) ?></span>
                    <span class="wa-chat-preview"><?= View::e(mb_strimwidth((string) $c['last_message_preview'], 0, 48, '…')) ?></span>
                    <?php if ($c['tags']): ?>
                        <span class="wa-chat-tags">
                            <?php foreach ($c['tags'] as $t): ?>
                                <span class="wa-tag" style="background:<?= View::e($t['color']) ?>"><?= View::e($t['name']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </span>
                <?php if ($c['unread_count'] > 0): ?><span class="wa-chat-unread"><?= (int) $c['unread_count'] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$chats): ?>
            <p class="hint-text" style="padding:16px;">Nenhuma conversa ainda. Clique em "🔄 Sincronizar" pra puxar suas conversas do WhatsApp.</p>
        <?php endif; ?>
    </div>

    <div class="wa-chat-thread" id="wa-thread-panel">
        <?php include __DIR__ . '/_thread.php'; ?>
    </div>
</div>

<dialog class="modal" id="modal-wa-tag">
    <div class="modal-header">
        <h2>Nova tag</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form method="post" action="/painel/whatsapp/tags" class="panel-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="back" value="/painel/whatsapp/conversas<?= $activeChat ? '/' . (int) $activeChat['id'] : '' ?>">
            <label for="wa-tag-name">Nome</label>
            <input type="text" id="wa-tag-name" name="name" required placeholder="Ex: Quente, Negociando, Fechado...">
            <label for="wa-tag-color">Cor</label>
            <input type="color" id="wa-tag-color" name="color" value="#8dc63f">
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Criar tag</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<script>
(function () {
    var inbox = document.querySelector('.wa-inbox');
    var panel = document.getElementById('wa-thread-panel');
    var pollTimer = null;

    function bindSendForm() {
        var form = panel.querySelector('[data-wa-send-form]');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = form.querySelector('[name=text]');
            var text = input.value.trim();
            if (!text) return;
            input.value = '';
            var data = new FormData(form);
            fetch(form.action, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function () { loadChat(form.dataset.chatId, false); });
        });

        var backLink = panel.querySelector('[data-wa-back]');
        if (backLink) {
            backLink.addEventListener('click', function (e) {
                e.preventDefault();
                inbox.classList.remove('has-active-chat');
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            });
        }
    }

    function scrollToBottom() {
        var list = panel.querySelector('[data-wa-messages]');
        if (list) list.scrollTop = list.scrollHeight;
    }

    function loadChat(chatId, pushState) {
        fetch('/painel/whatsapp/conversas/' + chatId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                panel.innerHTML = html;
                inbox.classList.add('has-active-chat');
                bindSendForm();
                scrollToBottom();
                document.querySelectorAll('[data-wa-chat-link]').forEach(function (a) {
                    a.classList.toggle('is-active', a.dataset.chatId === String(chatId));
                });
                if (pushState) {
                    history.pushState({ chatId: chatId }, '', '/painel/whatsapp/conversas/' + chatId);
                }
                startPolling(chatId);
            });
    }

    function startPolling(chatId) {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function () {
            fetch('/painel/whatsapp/conversas/' + chatId + '/poll')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = panel.querySelector('[data-wa-messages]');
                    if (!list || !data.messages) return;
                    var atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
                    if (data.messages.length !== list.children.length) {
                        loadChat(chatId, false);
                    }
                });
        }, 6000);
    }

    document.querySelectorAll('[data-wa-chat-link]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            loadChat(a.dataset.chatId, true);
        });
    });

    bindSendForm();
    scrollToBottom();
    var activeLink = document.querySelector('[data-wa-chat-link].is-active');
    if (activeLink) {
        inbox.classList.add('has-active-chat');
        startPolling(activeLink.dataset.chatId);
    }
})();
</script>
