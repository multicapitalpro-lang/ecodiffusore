<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array|null $activeChat */
/** @var array $messages */
/** @var array $user */
/** @var array $tags */
?>
<?php if (!$activeChat): ?>
    <div class="wa-thread-empty">
        <p>Selecione uma conversa à esquerda pra começar.</p>
    </div>
<?php else: ?>
    <div class="wa-thread-header">
        <a href="/painel/whatsapp/conversas" class="wa-thread-back" data-wa-back>← Voltar</a>
        <div>
            <strong><?= View::e($activeChat['name'] ?: $activeChat['remote_jid']) ?></strong>
        </div>
        <form method="post" action="/painel/whatsapp/conversas/<?= (int) $activeChat['id'] ?>/lead" class="inline-form">
            <?= Csrf::field() ?>
            <select name="lead_id" onchange="this.form.submit()" style="font-size:.8rem;padding:3px 6px;">
                <option value="">— Vincular a um lead —</option>
                <?php foreach (($myLeads ?? []) as $l): ?>
                    <option value="<?= (int) $l['id'] ?>" <?= (int) ($activeChat['lead_id'] ?? 0) === (int) $l['id'] ? 'selected' : '' ?>><?= View::e($l['name']) ?> (<?= View::e($l['status']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="wa-thread-tags">
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
        </div>
    </div>

    <div class="wa-messages" data-wa-messages>
        <?php foreach ($messages as $m): ?>
            <div class="wa-message wa-message-<?= $m['direction'] ?>">
                <div class="wa-message-bubble">
                    <?= nl2br(View::e($m['body'] ?: '[mensagem sem texto]')) ?>
                    <span class="wa-message-time"><?= date('d/m H:i', strtotime($m['sent_at'])) ?></span>
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
        <button type="submit" class="btn btn-primary">Enviar</button>
    </form>
<?php endif; ?>
