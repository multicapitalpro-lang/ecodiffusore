<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array|null $activeChat */
/** @var array $messages */
/** @var array $user */
/** @var array $tags */

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

/** Rotulo do separador de data entre mensagens -- mesmo criterio do proprio WhatsApp: Hoje/Ontem,
 *  dia da semana por extenso ate 6 dias atras, senao data cheia. */
$weekdaysPt = [0 => 'domingo', 1 => 'segunda-feira', 2 => 'terça-feira', 3 => 'quarta-feira', 4 => 'quinta-feira', 5 => 'sexta-feira', 6 => 'sábado'];
$dateSepLabel = function (string $day) use ($weekdaysPt) {
    $ts = strtotime($day);
    $today = date('Y-m-d');
    if ($day === $today) {
        return 'Hoje';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Ontem';
    }
    $diffDays = (int) ((strtotime($today) - $ts) / 86400);
    if ($diffDays > 0 && $diffDays < 7) {
        return ucfirst($weekdaysPt[(int) date('w', $ts)]);
    }
    return date('d/m/Y', $ts);
};

/** Legenda real de foto/video -- extractBody() (WhatsAppSync) guarda o corpo com um icone de
 *  prefixo (usado no preview da lista de conversas); aqui, dentro da bolha que ja mostra a midia de
 *  verdade, esse prefixo e' descartado e o "sem legenda" (ex: "[imagem]") fica em branco. */
$captionFor = function (?string $body) {
    if (!$body) {
        return null;
    }
    $stripped = preg_replace('/^\S+\s+/u', '', $body, 1);
    $placeholders = ['[imagem]', '[vídeo]', '[áudio]', '[documento]', '[figurinha]'];
    return in_array($stripped, $placeholders, true) ? null : $stripped;
};

$formatSize = function (?int $bytes) {
    if (!$bytes) {
        return '';
    }
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }
    return number_format($bytes / 1024, 0, ',', '.') . ' KB';
};

$mediaExtLabel = function (?string $mimetype) {
    $map = [
        'application/pdf' => 'PDF', 'application/msword' => 'DOC',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
        'application/vnd.ms-excel' => 'XLS',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
        'text/plain' => 'TXT',
    ];
    return $map[$mimetype ?? ''] ?? 'ARQ';
};
?>
<?php if (!$activeChat): ?>
    <div class="wa-thread-empty">
        <svg viewBox="0 0 60 60" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.2" style="opacity:.35;margin-bottom:10px;"><circle cx="30" cy="30" r="26"/><path d="M20 26h20M20 34h13"/></svg>
        <p>Selecione uma conversa à esquerda pra começar.</p>
    </div>
<?php else: ?>
    <?php $name = $activeChat['name'] ?: $activeChat['remote_jid']; ?>
    <div class="wa-thread-header">
        <div class="wa-thread-header-main">
            <a href="/painel/whatsapp/conversas" class="wa-thread-back" data-wa-back aria-label="Voltar">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12.5 4 6 10l6.5 6"/></svg>
            </a>
            <?php if ($activeChat['is_group']): ?>
                <span class="wa-avatar wa-thread-avatar wa-avatar-group">👥</span>
            <?php else: ?>
                <span class="wa-avatar wa-thread-avatar" style="background:<?= $avatarColor($activeChat['remote_jid']) ?>">
                    <?= View::e($initialsOf($name)) ?>
                    <?php if (!empty($activeChat['profile_pic_url'])): ?><img src="<?= View::e($activeChat['profile_pic_url']) ?>" alt="" class="wa-avatar-photo" onerror="this.remove()"><?php endif; ?>
                </span>
            <?php endif; ?>
            <span class="wa-thread-identity">
                <strong><?= View::e($name) ?></strong>
                <small><?= $activeChat['lead_name'] ? '🔗 ' . View::e($activeChat['lead_name']) . ' — ' . View::e($activeChat['lead_status']) : 'Sem lead vinculado' ?></small>
            </span>
        </div>
        <div class="wa-thread-toolbar">
            <form method="post" action="/painel/whatsapp/conversas/<?= (int) $activeChat['id'] ?>/lead" class="inline-form">
                <?= Csrf::field() ?>
                <select name="lead_id" onchange="this.form.submit()">
                    <option value="">— Vincular a um lead —</option>
                    <?php foreach (($myLeads ?? []) as $l): ?>
                        <option value="<?= (int) $l['id'] ?>" <?= (int) ($activeChat['lead_id'] ?? 0) === (int) $l['id'] ? 'selected' : '' ?>><?= View::e($l['name']) ?> (<?= View::e($l['status']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (empty($activeChat['lead_id'])): ?>
                <button type="button" class="wa-new-lead-btn" data-wa-new-lead data-wa-lead-name="<?= View::e($name) ?>">+ Criar lead</button>
            <?php endif; ?>
            <span class="wa-thread-tags">
                <?php $chatTagIds = array_column($activeChat['tags'] ?? [], 'id'); ?>
                <?php foreach ($tags as $t): ?>
                    <?php $applied = in_array($t['id'], $chatTagIds, true); ?>
                    <form method="post" action="/painel/whatsapp/conversas/<?= (int) $activeChat['id'] ?>/<?= $applied ? 'tags/' . (int) $t['id'] . '/remover' : 'tags' ?>" class="inline-form">
                        <?= Csrf::field() ?>
                        <?php if (!$applied): ?><input type="hidden" name="tag_id" value="<?= (int) $t['id'] ?>"><?php endif; ?>
                        <button type="submit" class="wa-tag wa-tag-toggle <?= $applied ? 'is-applied' : '' ?>" style="<?= $applied ? 'background:' . View::e($t['color']) : 'border-color:' . View::e($t['color']) . ';color:' . View::e($t['color']) ?>">
                            <?= View::e($t['name']) ?><?= $applied ? ' ×' : '' ?>
                        </button>
                    </form>
                <?php endforeach; ?>
                <button type="button" class="wa-tag wa-tag-new" data-wa-new-tag title="Nova tag">+ tag</button>
            </span>
        </div>
    </div>

    <div class="wa-messages" data-wa-messages>
        <?php $lastDay = null; ?>
        <?php foreach ($messages as $m): ?>
            <?php
            $day = date('Y-m-d', strtotime($m['sent_at']));
            if ($day !== $lastDay) {
                echo '<div class="wa-date-sep"><span>' . View::e($dateSepLabel($day)) . '</span></div>';
                $lastDay = $day;
            }
            $mediaUrl = "/painel/whatsapp/conversas/{$activeChat['id']}/midia/{$m['id']}";
            // Midia sincronizada antes da Fase 34 nao tem o key da Evolution guardado -- sem ele
            // (e sem ja estar em cache local) nao tem como buscar o arquivo, e tentar mesmo assim so
            // renderizava um icone de imagem quebrada. WhatsAppInboxController::show() ja tenta
            // recuperar automaticamente (backfill) na proxima vez que a conversa e' aberta; ate la,
            // mostra um aviso em vez do <img>/<video>/<audio> fadado a falhar.
            $mediaAvailable = !empty($m['wa_key_json']) || !empty($m['media_path']);
            ?>
            <?php $canDelete = $m['direction'] === 'out' && (int) $m['is_deleted'] !== 1 && !empty($m['wa_message_id']); ?>
            <div class="wa-message wa-message-<?= $m['direction'] ?>" data-wa-message-id="<?= (int) $m['id'] ?>">
                <div class="wa-message-bubble <?= $m['message_type'] === 'stickerMessage' ? 'wa-bubble-sticker' : '' ?> <?= in_array($m['message_type'], ['imageMessage', 'videoMessage'], true) ? 'wa-bubble-media' : '' ?>">
                    <?php if ($canDelete): ?>
                        <button type="button" class="wa-msg-menu-btn" data-wa-msg-menu-toggle aria-label="Opções da mensagem">
                            <svg viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="16" cy="10" r="1.6"/></svg>
                        </button>
                        <div class="wa-msg-menu" data-wa-msg-menu hidden>
                            <button type="button" data-wa-msg-delete>🗑️ Apagar para todos</button>
                        </div>
                    <?php endif; ?>
                    <?php if ((int) $m['is_deleted'] === 1): ?>
                        <span class="wa-deleted">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="m6.5 6.5 7 7M13.5 6.5l-7 7"/></svg>
                            Mensagem apagada
                        </span>
                    <?php elseif (!$mediaAvailable && in_array($m['message_type'], ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage', 'stickerMessage'], true)): ?>
                        <span class="wa-media-unavailable">
                            <?= ['imageMessage' => '📷', 'videoMessage' => '🎥', 'audioMessage' => '🎵', 'documentMessage' => '📄', 'stickerMessage' => '🩹'][$m['message_type']] ?>
                            Mídia antiga, indisponível pra recarregar
                        </span>
                    <?php elseif ($m['message_type'] === 'imageMessage'): ?>
                        <img class="wa-media-image" loading="lazy" src="<?= View::e($mediaUrl) ?>" alt="Imagem">
                        <?php if ($captionFor($m['body'])): ?><div class="wa-media-caption"><?= nl2br(View::e($captionFor($m['body']))) ?></div><?php endif; ?>
                    <?php elseif ($m['message_type'] === 'stickerMessage'): ?>
                        <img class="wa-media-sticker" loading="lazy" src="<?= View::e($mediaUrl) ?>" alt="Figurinha">
                    <?php elseif ($m['message_type'] === 'videoMessage'): ?>
                        <video class="wa-media-video" controls preload="none" src="<?= View::e($mediaUrl) ?>"></video>
                        <?php if ($captionFor($m['body'])): ?><div class="wa-media-caption"><?= nl2br(View::e($captionFor($m['body']))) ?></div><?php endif; ?>
                    <?php elseif ($m['message_type'] === 'audioMessage'): ?>
                        <audio class="wa-media-audio" controls preload="none" src="<?= View::e($mediaUrl) ?>"></audio>
                    <?php elseif ($m['message_type'] === 'documentMessage'): ?>
                        <a class="wa-media-doc" href="<?= View::e($mediaUrl) ?>">
                            <span class="wa-media-doc-icon"><?= View::e($mediaExtLabel($m['media_mimetype'])) ?></span>
                            <span class="wa-media-doc-info">
                                <span class="wa-media-doc-name"><?= View::e($m['media_filename'] ?: 'Documento') ?></span>
                                <span class="wa-media-doc-size"><?= View::e($formatSize($m['media_size_bytes'] !== null ? (int) $m['media_size_bytes'] : null)) ?></span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M10 3v11M6 10l4 4 4-4M4 16.5h12"/></svg>
                        </a>
                    <?php else: ?>
                        <?= nl2br(View::e($m['body'] ?: '[mensagem sem texto]')) ?>
                    <?php endif; ?>
                    <span class="wa-message-time"><?= date('H:i', strtotime($m['sent_at'])) ?><?= $m['direction'] === 'out' && (int) $m['is_deleted'] !== 1 ? ' <svg class="wa-tick" viewBox="0 0 16 11" width="14" height="10" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6l3 3 7-8"/></svg>' : '' ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
            <p class="hint-text" style="padding:16px;">Nenhuma mensagem ainda.</p>
        <?php endif; ?>
    </div>

    <div class="wa-compose" data-wa-compose>
        <div class="wa-recording-bar" data-wa-recording hidden>
            <button type="button" class="wa-icon-btn wa-recording-cancel" data-wa-record-cancel aria-label="Cancelar gravação">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 5h10v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5Z"/><path d="M3 5h14M8 5V3.5A1.5 1.5 0 0 1 9.5 2h1A1.5 1.5 0 0 1 12 3.5V5"/></svg>
            </button>
            <span class="wa-recording-dot"></span>
            <span class="wa-recording-time" data-wa-record-time>0:00</span>
            <span class="wa-recording-hint">Gravando áudio...</span>
            <button type="button" class="wa-send-btn" data-wa-record-send aria-label="Enviar áudio">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2.5 2.5 17 10 2.5 17.5 5 10.8 12 10 5 9.2 2.5 2.5Z"/></svg>
            </button>
        </div>

        <form action="/painel/whatsapp/conversas/<?= (int) $activeChat['id'] ?>/enviar" method="post" class="wa-send-form" data-wa-send-form data-chat-id="<?= (int) $activeChat['id'] ?>">
            <?= Csrf::field() ?>
            <div class="wa-emoji-popover" data-wa-emoji-popover hidden></div>
            <button type="button" class="wa-icon-btn wa-compose-icon" data-wa-emoji-toggle aria-label="Emoji" title="Emoji">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M7.2 8.2h.01M12.8 8.2h.01M6.8 12a4 4 0 0 0 6.4 0"/></svg>
            </button>

            <div class="wa-attach-menu" data-wa-attach-menu hidden>
                <button type="button" data-wa-attach-kind="document">
                    <span class="wa-attach-menu-icon" style="background:#7f66ff">📄</span> Documento
                </button>
                <button type="button" data-wa-attach-kind="media">
                    <span class="wa-attach-menu-icon" style="background:#bf59cf">🖼️</span> Fotos e vídeos
                </button>
            </div>
            <button type="button" class="wa-icon-btn wa-compose-icon" data-wa-attach-toggle aria-label="Anexar" title="Anexar">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 6.5 8 12a2.5 2.5 0 1 0 3.5 3.5L17 10a4.5 4.5 0 1 0-6.5-6.5L4 10a1 1 0 0 0 1.5 1.5l6-6"/></svg>
            </button>
            <input type="file" data-wa-file-document hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,audio/*">
            <input type="file" data-wa-file-media hidden accept="image/*,video/*">

            <input type="text" name="text" placeholder="Digite uma mensagem..." autocomplete="off" data-wa-text-input>

            <button type="button" class="wa-send-btn" data-wa-send-or-mic aria-label="Enviar">
                <svg class="wa-icon-send" viewBox="0 0 20 20" fill="currentColor"><path d="M2.5 2.5 17 10 2.5 17.5 5 10.8 12 10 5 9.2 2.5 2.5Z"/></svg>
                <svg class="wa-icon-mic" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3a2.5 2.5 0 0 0-2.5 2.5v4a2.5 2.5 0 0 0 5 0v-4A2.5 2.5 0 0 0 10 3Z"/><path d="M5.5 9v.5a4.5 4.5 0 0 0 9 0V9M10 14v3"/></svg>
            </button>
        </form>
    </div>
<?php endif; ?>
