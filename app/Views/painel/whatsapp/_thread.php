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
                <span class="wa-avatar wa-thread-avatar" style="background:<?= $avatarColor($activeChat['remote_jid']) ?>"><?= View::e($initialsOf($name)) ?></span>
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
            </span>
        </div>
    </div>

    <div class="wa-messages" data-wa-messages>
        <?php foreach ($messages as $m): ?>
            <div class="wa-message wa-message-<?= $m['direction'] ?>">
                <div class="wa-message-bubble">
                    <?= nl2br(View::e($m['body'] ?: '[mensagem sem texto]')) ?>
                    <span class="wa-message-time"><?= date('H:i', strtotime($m['sent_at'])) ?><?= $m['direction'] === 'out' ? ' <svg class="wa-tick" viewBox="0 0 16 11" width="14" height="10" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6l3 3 7-8"/></svg>' : '' ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
            <p class="hint-text" style="padding:16px;">Nenhuma mensagem ainda.</p>
        <?php endif; ?>
    </div>

    <form action="/painel/whatsapp/conversas/<?= (int) $activeChat['id'] ?>/enviar" method="post" class="wa-send-form" data-wa-send-form data-chat-id="<?= (int) $activeChat['id'] ?>">
        <?= Csrf::field() ?>
        <input type="text" name="text" placeholder="Digite uma mensagem..." autocomplete="off" required>
        <button type="submit" class="wa-send-btn" aria-label="Enviar">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2.5 2.5 17 10 2.5 17.5 5 10.8 12 10 5 9.2 2.5 2.5Z"/></svg>
        </button>
    </form>
<?php endif; ?>
