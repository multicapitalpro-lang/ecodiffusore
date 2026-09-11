<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;

/** Paleta fixa (estilo "avatar por iniciais" de app de mensageria) -- a cor e' escolhida por hash
 *  do JID, entao o mesmo contato sempre cai na mesma cor entre carregamentos, sem precisar guardar
 *  nada no banco (nao temos foto de perfil real ainda, ver EvolutionApiClient::fetchChats()). */
$avatarPalette = ['#e17076', '#eda86c', '#a695e7', '#7bc862', '#6ec9cb', '#65aadd', '#ee7aae', '#f2789f', '#8bc255'];
$avatarColor = function (string $seed) use ($avatarPalette) {
    return $avatarPalette[crc32($seed) % count($avatarPalette)];
};
$initialsOf = function (string $name) {
    $name = trim($name);
    if ($name === '') {
        return '#';
    }
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
};
$waTime = function (?string $dt) {
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    $day = date('Y-m-d', $ts);
    if ($day === date('Y-m-d')) {
        return date('H:i', $ts);
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ontem';
    }
    return date('d/m/Y', $ts);
};
?>
<div class="page-header">
    <h1>💬 Meu WhatsApp</h1>
</div>

<?php if ($sucesso): ?><p class="form-msg form-msg-ok">Sincronizado com sucesso.</p><?php endif; ?>
<?php if ($erro === 'sync'): ?><p class="form-msg form-msg-erro">Não foi possível sincronizar agora. Tente de novo em instantes.</p><?php endif; ?>

<div class="wa-inbox">
    <div class="wa-chat-list">
        <div class="wa-list-header">
            <h2>Conversas</h2>
            <div class="wa-list-header-actions">
                <button type="button" class="wa-icon-btn" data-modal-open="modal-wa-tag" title="Nova tag" aria-label="Nova tag">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H4a1 1 0 0 0-1 1v5l8.6 8.6a1 1 0 0 0 1.4 0l4.6-4.6a1 1 0 0 0 0-1.4L9 3Z"/><circle cx="6.5" cy="6.5" r="1"/></svg>
                </button>
                <form method="post" action="/painel/whatsapp/sincronizar" class="inline-form">
                    <?= Csrf::field() ?>
                    <button type="submit" class="wa-icon-btn" title="Sincronizar" aria-label="Sincronizar">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6.5 6.5 0 0 0-11.2-3.6M4 4v3.5H7.5M4 12a6.5 6.5 0 0 0 11.2 3.6M16 16v-3.5h-3.5"/></svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="wa-list-search">
            <div class="wa-list-search-inner">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="9" cy="9" r="6"/><path d="m17 17-4-4"/></svg>
                <input type="text" id="wa-search-input" placeholder="Pesquisar conversa...">
            </div>
        </div>

        <div class="wa-list-filters">
            <button type="button" class="wa-filter-pill is-active" data-wa-filter="all">Tudo</button>
            <button type="button" class="wa-filter-pill" data-wa-filter="unread">Não lidas</button>
        </div>

        <div class="wa-chat-scroll" id="wa-chat-scroll">
            <?php foreach ($chats as $c): ?>
                <?php $name = $c['name'] ?: $c['remote_jid']; ?>
                <a href="/painel/whatsapp/conversas/<?= (int) $c['id'] ?>"
                   class="wa-chat-item <?= $activeChat && (int) $activeChat['id'] === (int) $c['id'] ? 'is-active' : '' ?>"
                   data-wa-chat-link data-chat-id="<?= (int) $c['id'] ?>"
                   data-wa-name="<?= View::e(mb_strtolower($name)) ?>" data-wa-unread="<?= (int) $c['unread_count'] ?>">
                    <?php if ($c['is_group']): ?>
                        <span class="wa-avatar wa-avatar-group">👥</span>
                    <?php else: ?>
                        <span class="wa-avatar" style="background:<?= $avatarColor($c['remote_jid']) ?>"><?= View::e($initialsOf($name)) ?></span>
                    <?php endif; ?>
                    <span class="wa-chat-info">
                        <span class="wa-chat-row-top">
                            <span class="wa-chat-name"><?= View::e($name) ?></span>
                            <span class="wa-chat-time"><?= $waTime($c['last_message_at']) ?></span>
                        </span>
                        <span class="wa-chat-row-bottom">
                            <span class="wa-chat-preview"><?= View::e(mb_strimwidth((string) $c['last_message_preview'], 0, 48, '…')) ?></span>
                            <?php if ($c['unread_count'] > 0): ?><span class="wa-chat-unread"><?= (int) $c['unread_count'] ?></span><?php endif; ?>
                        </span>
                        <?php if ($c['tags']): ?>
                            <span class="wa-chat-tags">
                                <?php foreach ($c['tags'] as $t): ?>
                                    <span class="wa-tag" style="background:<?= View::e($t['color']) ?>"><?= View::e($t['name']) ?></span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
            <?php if (!$chats): ?>
                <p class="hint-text" style="padding:16px;">Nenhuma conversa ainda. Clique em 🔄 pra puxar suas conversas do WhatsApp.</p>
            <?php endif; ?>
            <p class="hint-text wa-empty-filter" id="wa-empty-filter" hidden style="padding:16px;">Nenhuma conversa encontrada.</p>
        </div>
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

    // Busca por nome + filtro "Não lidas" -- tudo client-side, a lista inteira ja esta no DOM.
    var searchInput = document.getElementById('wa-search-input');
    var filterPills = document.querySelectorAll('[data-wa-filter]');
    var activeFilter = 'all';

    function applyListFilters() {
        var term = (searchInput.value || '').trim().toLowerCase();
        var items = document.querySelectorAll('.wa-chat-item');
        var visibleCount = 0;
        items.forEach(function (item) {
            var matchesSearch = !term || item.dataset.waName.indexOf(term) !== -1;
            var matchesFilter = activeFilter === 'all' || parseInt(item.dataset.waUnread, 10) > 0;
            var show = matchesSearch && matchesFilter;
            item.hidden = !show;
            if (show) visibleCount++;
        });
        var emptyMsg = document.getElementById('wa-empty-filter');
        if (emptyMsg) emptyMsg.hidden = visibleCount !== 0 || items.length === 0;
    }

    if (searchInput) searchInput.addEventListener('input', applyListFilters);
    filterPills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            filterPills.forEach(function (p) { p.classList.remove('is-active'); });
            pill.classList.add('is-active');
            activeFilter = pill.dataset.waFilter;
            applyListFilters();
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
