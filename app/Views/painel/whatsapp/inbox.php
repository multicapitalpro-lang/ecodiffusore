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
<?php if ($sucesso): ?><p class="form-msg form-msg-ok" style="margin:0 0 10px;">Sincronizado com sucesso.</p><?php endif; ?>
<?php if ($erro === 'sync'): ?><p class="form-msg form-msg-erro" style="margin:0 0 10px;">Não foi possível sincronizar agora. Tente de novo em instantes.</p><?php endif; ?>

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
        <div class="wa-list-filters-extra">
            <select id="wa-tag-filter" title="Filtrar por tag">
                <option value="">Todas as tags</option>
                <?php foreach ($tags as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= View::e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="wa-lead-filter" title="Filtrar por lead">
                <option value="">Todos (com/sem lead)</option>
                <option value="with">Com lead vinculado</option>
                <option value="without">Sem lead vinculado</option>
            </select>
        </div>

        <div class="wa-chat-scroll" id="wa-chat-scroll">
            <?php foreach ($chats as $c): ?>
                <?php $name = $c['name'] ?: $c['remote_jid']; ?>
                <a href="/painel/whatsapp/conversas/<?= (int) $c['id'] ?>"
                   class="wa-chat-item <?= $activeChat && (int) $activeChat['id'] === (int) $c['id'] ? 'is-active' : '' ?>"
                   data-wa-chat-link data-chat-id="<?= (int) $c['id'] ?>"
                   data-wa-name="<?= View::e(mb_strtolower($name)) ?>" data-wa-unread="<?= (int) $c['unread_count'] ?>"
                   data-wa-tag-ids="<?= View::e(implode(',', array_column($c['tags'], 'id'))) ?>"
                   data-wa-has-lead="<?= !empty($c['lead_id']) ? '1' : '0' ?>">
                    <?php if ($c['is_group']): ?>
                        <span class="wa-avatar wa-avatar-group">👥</span>
                    <?php else: ?>
                        <span class="wa-avatar" style="background:<?= $avatarColor($c['remote_jid']) ?>">
                            <?= View::e($initialsOf($name)) ?>
                            <?php if (!empty($c['profile_pic_url'])): ?><img src="<?= View::e($c['profile_pic_url']) ?>" alt="" class="wa-avatar-photo" loading="lazy" onerror="this.remove()"><?php endif; ?>
                        </span>
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

<dialog class="modal" id="modal-wa-lead">
    <div class="modal-header">
        <h2>Criar lead a partir desta conversa</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form method="post" action="/painel/whatsapp/conversas/<?= $activeChat ? (int) $activeChat['id'] : '' ?>/lead-novo" class="panel-form" data-wa-lead-form>
            <?= Csrf::field() ?>
            <label for="wa-lead-name">Nome</label>
            <input type="text" id="wa-lead-name" name="name" required>
            <p class="hint-text" style="margin-top:0;">O lead já entra vinculado a esta conversa e atribuído a você.</p>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Criar lead</button>
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
    var mediaRecorder = null;
    var recordedChunks = [];
    var recordStartTime = null;
    var recordTimerInterval = null;

    var EMOJI_GROUPS = [
        { label: 'Sorrisos', items: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','☺️','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','🥳','😎','🤓','🧐'] },
        { label: 'Gestos', items: ['👍','👎','👌','✌️','🤞','🤟','🤘','👊','✊','👏','🙌','👐','🤝','🙏','💪','👋','🤙','☝️','✋','🖐️','👆','👇','👈','👉','🫡'] },
        { label: 'Corações', items: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','😻','🥹'] },
        { label: 'Objetos', items: ['🔥','✨','🎉','🎊','💯','⭐','🌟','💰','💵','📦','📱','💻','⏰','📅','✅','❌','⚠️','❗','❓','💡','🔧','📌','📍','🔔'] },
        { label: 'Comida', items: ['☕','🍕','🍔','🍟','🍰','🎂','🍺','🍷','🥤','🍎','🍌','🍉'] }
    ];

    function closePopovers() {
        document.querySelectorAll('[data-wa-emoji-popover], [data-wa-attach-menu], [data-wa-msg-menu]').forEach(function (el) { el.hidden = true; });
    }
    document.addEventListener('click', closePopovers);

    function buildEmojiPopover(popover) {
        if (popover.dataset.built) return;
        popover.dataset.built = '1';
        EMOJI_GROUPS.forEach(function (group) {
            var label = document.createElement('div');
            label.className = 'wa-emoji-group-label';
            label.textContent = group.label;
            popover.appendChild(label);

            var grid = document.createElement('div');
            grid.className = 'wa-emoji-grid';
            group.items.forEach(function (emoji) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = emoji;
                grid.appendChild(btn);
            });
            popover.appendChild(grid);
        });
    }

    function insertEmojiAtCursor(input, emoji) {
        var start = input.selectionStart != null ? input.selectionStart : input.value.length;
        var end = input.selectionEnd != null ? input.selectionEnd : input.value.length;
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        var pos = start + emoji.length;
        input.setSelectionRange(pos, pos);
        input.focus();
        updateSendButtonState(input);
    }

    function updateSendButtonState(input) {
        var btn = panel.querySelector('[data-wa-send-or-mic]');
        if (!input || !btn) return;
        btn.classList.toggle('has-text', input.value.trim() !== '');
    }

    function uploadFile(chatId, file, form, kind) {
        var compose = panel.querySelector('[data-wa-compose]');
        var uploadStatus = document.createElement('div');
        uploadStatus.className = 'wa-upload-preview';
        uploadStatus.innerHTML = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="10" r="7" stroke-dasharray="30" stroke-dashoffset="10"/></svg><span>Enviando ' + file.name + '...</span>';
        if (compose) compose.insertAdjacentElement('beforebegin', uploadStatus);

        var data = new FormData();
        var csrfInput = form.querySelector('[name=csrf_token]');
        data.append('csrf_token', csrfInput ? csrfInput.value : '');
        data.append('file', file);
        // O navegador so grava audio em container webm/ogg -- o finfo do servidor as vezes detecta
        // webm-so-com-audio como "video/webm" (o container webm serve tanto pra audio quanto pra
        // video, o magic-byte sozinho nao distingue), e mandava a gravacao como VIDEO pro WhatsApp.
        // Esse hint forca a classificacao certa so pra esse fluxo, sem depender do mimetype sniffado.
        if (kind) data.append('kind', kind);

        fetch('/painel/whatsapp/conversas/' + chatId + '/enviar-midia', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                uploadStatus.remove();
                if (result && result.ok === false) {
                    alert(result.error || 'Falha ao enviar arquivo.');
                    return;
                }
                updateChatListPreview(chatId, mediaPreviewLabel(file));
                loadChat(chatId, false);
            })
            .catch(function () {
                uploadStatus.remove();
                alert('Falha ao enviar arquivo. Confira sua conexão.');
            });
    }

    function updateRecordTimer() {
        var el = panel.querySelector('[data-wa-record-time]');
        if (!el || !recordStartTime) return;
        var secs = Math.floor((Date.now() - recordStartTime) / 1000);
        var m = Math.floor(secs / 60);
        var s = secs % 60;
        el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
    }

    function toggleRecordingUI(recording) {
        var formEl = panel.querySelector('.wa-send-form');
        var barEl = panel.querySelector('[data-wa-recording]');
        if (formEl) formEl.hidden = recording;
        if (barEl) barEl.hidden = !recording;
    }

    function startRecording(chatId) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
            alert('Seu navegador não suporta gravação de áudio.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            recordedChunks = [];
            var mimeType = '';
            if (window.MediaRecorder && MediaRecorder.isTypeSupported) {
                if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) mimeType = 'audio/ogg;codecs=opus';
                else if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) mimeType = 'audio/webm;codecs=opus';
            }
            mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType: mimeType }) : new MediaRecorder(stream);
            mediaRecorder._stream = stream;
            mediaRecorder.ondataavailable = function (e) { if (e.data && e.data.size > 0) recordedChunks.push(e.data); };
            mediaRecorder.start();

            recordStartTime = Date.now();
            updateRecordTimer();
            if (recordTimerInterval) clearInterval(recordTimerInterval);
            recordTimerInterval = setInterval(updateRecordTimer, 500);
            toggleRecordingUI(true);
        }).catch(function () {
            alert('Não foi possível acessar o microfone. Confira a permissão do navegador pra este site.');
        });
    }

    function stopStream() {
        if (mediaRecorder && mediaRecorder._stream) {
            mediaRecorder._stream.getTracks().forEach(function (t) { t.stop(); });
        }
    }

    function cancelRecording() {
        if (recordTimerInterval) { clearInterval(recordTimerInterval); recordTimerInterval = null; }
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.onstop = null;
            mediaRecorder.stop();
        }
        stopStream();
        recordedChunks = [];
        mediaRecorder = null;
        toggleRecordingUI(false);
    }

    function sendRecording(chatId, form) {
        if (!mediaRecorder) return;
        if (recordTimerInterval) { clearInterval(recordTimerInterval); recordTimerInterval = null; }
        var recorder = mediaRecorder;
        recorder.onstop = function () {
            var blob = new Blob(recordedChunks, { type: recorder.mimeType || 'audio/webm' });
            stopStream();
            mediaRecorder = null;
            toggleRecordingUI(false);
            if (blob.size === 0) return;
            var ext = blob.type.indexOf('ogg') !== -1 ? 'ogg' : 'webm';
            var file = new File([blob], 'audio.' + ext, { type: blob.type });
            uploadFile(chatId, file, form, 'audio');
        };
        recorder.stop();
    }

    function bindComposeExtras(form) {
        var chatId = form.dataset.chatId;
        var textInput = form.querySelector('[data-wa-text-input]');

        if (textInput) {
            textInput.addEventListener('input', function () { updateSendButtonState(textInput); });
            textInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (textInput.value.trim() !== '' && form.requestSubmit) form.requestSubmit();
                }
            });
            updateSendButtonState(textInput);
        }

        var sendOrMicBtn = form.querySelector('[data-wa-send-or-mic]');
        if (sendOrMicBtn) {
            sendOrMicBtn.addEventListener('click', function () {
                if (textInput && textInput.value.trim() !== '') {
                    if (form.requestSubmit) form.requestSubmit();
                } else {
                    startRecording(chatId);
                }
            });
        }

        var emojiToggle = form.querySelector('[data-wa-emoji-toggle]');
        var emojiPopover = form.querySelector('[data-wa-emoji-popover]');
        if (emojiToggle && emojiPopover) {
            buildEmojiPopover(emojiPopover);
            emojiToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasHidden = emojiPopover.hidden;
                closePopovers();
                emojiPopover.hidden = !wasHidden;
            });
            emojiPopover.addEventListener('click', function (e) {
                e.stopPropagation();
                var btn = e.target.closest('button');
                if (btn && textInput) insertEmojiAtCursor(textInput, btn.textContent);
            });
        }

        var attachToggle = form.querySelector('[data-wa-attach-toggle]');
        var attachMenu = form.querySelector('[data-wa-attach-menu]');
        var fileDocInput = form.querySelector('[data-wa-file-document]');
        var fileMediaInput = form.querySelector('[data-wa-file-media]');
        if (attachToggle && attachMenu) {
            attachToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasHidden = attachMenu.hidden;
                closePopovers();
                attachMenu.hidden = !wasHidden;
            });
            attachMenu.querySelectorAll('[data-wa-attach-kind]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    attachMenu.hidden = true;
                    if (btn.dataset.waAttachKind === 'document' && fileDocInput) fileDocInput.click();
                    if (btn.dataset.waAttachKind === 'media' && fileMediaInput) fileMediaInput.click();
                });
            });
        }
        [fileDocInput, fileMediaInput].forEach(function (inp) {
            if (!inp) return;
            inp.addEventListener('change', function () {
                if (inp.files && inp.files[0]) uploadFile(chatId, inp.files[0], form);
                inp.value = '';
            });
        });

        var recordCancelBtn = panel.querySelector('[data-wa-record-cancel]');
        var recordSendBtn = panel.querySelector('[data-wa-record-send]');
        if (recordCancelBtn) recordCancelBtn.addEventListener('click', cancelRecording);
        if (recordSendBtn) recordSendBtn.addEventListener('click', function () { sendRecording(chatId, form); });
    }

    function bindSendForm() {
        var form = panel.querySelector('[data-wa-send-form]');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = form.querySelector('[name=text]');
            var text = input.value.trim();
            if (!text) return;
            // FormData PRECISA ler o campo antes de limpar -- limpar antes fazia o form serializar
            // texto vazio, entao nada era mandado pro servidor (nem pro WhatsApp) mesmo a caixa
            // esvaziando na hora, dando a falsa impressao de que a mensagem foi enviada.
            var data = new FormData(form);
            input.value = '';
            updateSendButtonState(input);
            fetch(form.action, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result && result.ok === false) {
                        input.value = text;
                        updateSendButtonState(input);
                        alert(result.error || 'Falha ao enviar. Confira se o WhatsApp continua conectado.');
                        return;
                    }
                    updateChatListPreview(form.dataset.chatId, text);
                    loadChat(form.dataset.chatId, false);
                })
                .catch(function () { loadChat(form.dataset.chatId, false); });
        });

        bindComposeExtras(form);
        bindMessageMenus(form.dataset.chatId);

        var backLink = panel.querySelector('[data-wa-back]');
        if (backLink) {
            backLink.addEventListener('click', function (e) {
                e.preventDefault();
                inbox.classList.remove('has-active-chat');
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            });
        }

        var newLeadBtn = panel.querySelector('[data-wa-new-lead]');
        if (newLeadBtn) {
            newLeadBtn.addEventListener('click', function () {
                openLeadModal(form.dataset.chatId, newLeadBtn.dataset.waLeadName);
            });
        }

        var newTagBtn = panel.querySelector('[data-wa-new-tag]');
        if (newTagBtn) {
            newTagBtn.addEventListener('click', function () {
                openTagModal(form.dataset.chatId);
            });
        }
    }

    // O gatilho de "Nova tag" no cabecalho da lista (persistente, nao e' trocado a cada conversa)
    // ja abre o modal sozinho via o mecanismo generico [data-modal-open] do painel.js -- so precisa
    // corrigir o campo "back" pra conversa ATUAL antes de abrir (senao fica preso na conversa que
    // estava ativa no carregamento inicial da pagina, ja que o modal em si nunca e' re-renderizado).
    var headerTagBtn = document.querySelector('[data-modal-open="modal-wa-tag"]');
    if (headerTagBtn) {
        headerTagBtn.addEventListener('click', function () {
            var form = panel.querySelector('[data-wa-send-form]');
            if (form) setTagModalBack(form.dataset.chatId);
        });
    }

    function setTagModalBack(chatId) {
        var modal = document.getElementById('modal-wa-tag');
        var backInput = modal ? modal.querySelector('[name=back]') : null;
        if (backInput) backInput.value = '/painel/whatsapp/conversas' + (chatId ? '/' + chatId : '');
    }

    function openTagModal(chatId) {
        setTagModalBack(chatId);
        var modal = document.getElementById('modal-wa-tag');
        if (modal) { modal.showModal(); document.body.classList.add('modal-open'); }
    }

    function openLeadModal(chatId, defaultName) {
        var modal = document.getElementById('modal-wa-lead');
        if (!modal || !chatId) return;
        var formEl = modal.querySelector('[data-wa-lead-form]');
        var nameInput = modal.querySelector('[name=name]');
        if (formEl) formEl.action = '/painel/whatsapp/conversas/' + chatId + '/lead-novo';
        if (nameInput) nameInput.value = defaultName || '';
        modal.showModal();
        document.body.classList.add('modal-open');
    }

    // Atualiza a previa da conversa na lista da esquerda assim que uma mensagem e' enviada por
    // aqui -- sem isso, a lista ficava presa mostrando a ULTIMA MENSAGEM RECEBIDA ate a pagina
    // inteira recarregar (o servidor grava certinho, so o item da lista, ja renderizado, nunca
    // era atualizado no DOM). Tambem move a conversa pro topo, como o WhatsApp de verdade faz.
    function updateChatListPreview(chatId, previewText) {
        var link = document.querySelector('[data-wa-chat-link][data-chat-id="' + chatId + '"]');
        if (!link) return;
        var previewEl = link.querySelector('.wa-chat-preview');
        var timeEl = link.querySelector('.wa-chat-time');
        if (previewEl) previewEl.textContent = previewText;
        if (timeEl) timeEl.textContent = 'Agora';
        var scroll = document.getElementById('wa-chat-scroll');
        if (scroll && link.parentElement === scroll && scroll.firstElementChild !== link) {
            scroll.insertBefore(link, scroll.firstElementChild);
        }
    }

    function mediaPreviewLabel(file) {
        var mime = file.type || '';
        if (mime.indexOf('image/') === 0) return '📷 Foto';
        if (mime.indexOf('video/') === 0) return '🎥 Vídeo';
        if (mime.indexOf('audio/') === 0) return '🎵 Áudio';
        return '📄 ' + file.name;
    }

    function bindMessageMenus(chatId) {
        var list = panel.querySelector('[data-wa-messages]');
        if (!list) return;
        list.addEventListener('click', function (e) {
            var toggleBtn = e.target.closest('[data-wa-msg-menu-toggle]');
            if (toggleBtn) {
                e.stopPropagation();
                var menu = toggleBtn.parentElement.querySelector('[data-wa-msg-menu]');
                if (!menu) return;
                var wasHidden = menu.hidden;
                closePopovers();
                menu.hidden = !wasHidden;
                return;
            }
            var deleteBtn = e.target.closest('[data-wa-msg-delete]');
            if (deleteBtn) {
                e.stopPropagation();
                var msgEl = deleteBtn.closest('[data-wa-message-id]');
                if (!msgEl) return;
                if (!confirm('Apagar esta mensagem para todos? Essa ação não pode ser desfeita.')) return;
                deleteMessage(chatId, msgEl.dataset.waMessageId, msgEl);
            }
        });
    }

    function deleteMessage(chatId, msgDbId, msgEl) {
        var form = panel.querySelector('[data-wa-send-form]');
        var csrfInput = form ? form.querySelector('[name=csrf_token]') : null;
        var data = new FormData();
        data.append('csrf_token', csrfInput ? csrfInput.value : '');
        fetch('/painel/whatsapp/conversas/' + chatId + '/mensagens/' + msgDbId + '/apagar', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result && result.ok === false) {
                    alert(result.error || 'Não foi possível apagar.');
                    return;
                }
                loadChat(chatId, false);
            })
            .catch(function () { alert('Falha ao apagar. Confira sua conexão.'); });
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
                    // So conta bolhas de mensagem de verdade -- a partir da Fase 34 a lista tambem
                    // tem divs de separador de data entre elas, que nao contam como mensagem (senao
                    // a contagem nunca batia e recarregava a conversa inteira a cada 6s a toa).
                    var currentCount = list.querySelectorAll('.wa-message').length;
                    if (data.messages.length !== currentCount) {
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

    // Busca por nome + filtro "Não lidas"/tag/lead -- tudo client-side, a lista inteira ja esta no DOM.
    var searchInput = document.getElementById('wa-search-input');
    var filterPills = document.querySelectorAll('[data-wa-filter]');
    var tagFilterSelect = document.getElementById('wa-tag-filter');
    var leadFilterSelect = document.getElementById('wa-lead-filter');
    var activeFilter = 'all';

    function applyListFilters() {
        var term = (searchInput.value || '').trim().toLowerCase();
        var tagVal = tagFilterSelect ? tagFilterSelect.value : '';
        var leadVal = leadFilterSelect ? leadFilterSelect.value : '';
        var items = document.querySelectorAll('.wa-chat-item');
        var visibleCount = 0;
        items.forEach(function (item) {
            var matchesSearch = !term || item.dataset.waName.indexOf(term) !== -1;
            var matchesFilter = activeFilter === 'all' || parseInt(item.dataset.waUnread, 10) > 0;
            var matchesTag = !tagVal || (',' + (item.dataset.waTagIds || '') + ',').indexOf(',' + tagVal + ',') !== -1;
            var matchesLead = !leadVal || (leadVal === 'with' ? item.dataset.waHasLead === '1' : item.dataset.waHasLead === '0');
            var show = matchesSearch && matchesFilter && matchesTag && matchesLead;
            item.hidden = !show;
            if (show) visibleCount++;
        });
        var emptyMsg = document.getElementById('wa-empty-filter');
        if (emptyMsg) emptyMsg.hidden = visibleCount !== 0 || items.length === 0;
    }

    if (searchInput) searchInput.addEventListener('input', applyListFilters);
    if (tagFilterSelect) tagFilterSelect.addEventListener('change', applyListFilters);
    if (leadFilterSelect) leadFilterSelect.addEventListener('change', applyListFilters);
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
