<?php
use App\Core\View;
/** @var array $items lista de anexos desta transacao (pode ser vazia) */
if (!$items): ?>
    —
<?php else: foreach ($items as $file): ?>
    <a href="/painel/financeiro/anexos/<?= (int) $file['id'] ?>" target="_blank" rel="noopener" class="attachment-link" title="<?= View::e($file['original_name']) ?>">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 6.5 8 12a2 2 0 1 0 2.8 2.8l5.7-5.7a3.6 3.6 0 1 0-5-5L6 9.6"/></svg>
        <?= View::e(mb_strimwidth($file['original_name'], 0, 16, '…')) ?>
    </a><br>
<?php endforeach; endif; ?>
