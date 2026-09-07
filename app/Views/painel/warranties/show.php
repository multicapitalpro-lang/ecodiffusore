<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'aprovada' => 'Aprovada', 'rejeitada' => 'Rejeitada', 'concluida' => 'Concluída'];
$statusBadge = ['aberta' => 'novo', 'em_analise' => 'contatado', 'aprovada' => 'active', 'rejeitada' => 'inactive', 'concluida' => 'active'];
$sucesso = isset($_GET['sucesso']);
?>
<div class="page-header">
    <h1>Garantia — Pedido #<?= (int) $warranty['order_id'] ?></h1>
    <a href="/painel/garantias" class="btn btn-outline">← Garantias</a>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($warranty['client_name']) ?></p>
    <p><strong>Aberta em:</strong> <?= View::e(date('d/m/Y', strtotime($warranty['created_at']))) ?></p>
    <p><strong>Status:</strong> <span class="status-badge status-<?= $statusBadge[$warranty['status']] ?? 'novo' ?>"><?= $statusLabels[$warranty['status']] ?? $warranty['status'] ?></span></p>
    <p><a href="/painel/pedidos/<?= (int) $warranty['order_id'] ?>" class="link-small">Ver pedido</a></p>
    <p><strong>Descrição:</strong></p>
    <p><?= nl2br(View::e($warranty['description'])) ?></p>
    <?php if (!empty($warranty['attachment_path'])): ?>
        <p><a href="/painel/garantias/<?= (int) $warranty['id'] ?>/anexo" target="_blank" rel="noopener" class="link-small">📎 Ver anexo enviado</a></p>
    <?php endif; ?>
    <?php if (!empty($warranty['resolution_note'])): ?>
        <p><strong>Última nota de resolução:</strong></p>
        <p><?= nl2br(View::e($warranty['resolution_note'])) ?></p>
        <p class="hint-text">Por <?= View::e($warranty['resolved_by_name'] ?? '—') ?> em <?= $warranty['resolved_at'] ? View::e(date('d/m/Y H:i', strtotime($warranty['resolved_at']))) : '—' ?></p>
    <?php endif; ?>
</div>

<?php if (!$isViewOnly): ?>
<h3 class="section-title">Atualizar status</h3>
<form method="post" action="/painel/garantias/<?= (int) $warranty['id'] ?>/status">
    <?= Csrf::field() ?>
    <label for="status">Novo status</label>
    <select id="status" name="status">
        <option value="em_analise" <?= $warranty['status'] === 'em_analise' ? 'selected' : '' ?>>Em análise</option>
        <option value="aprovada" <?= $warranty['status'] === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
        <option value="rejeitada" <?= $warranty['status'] === 'rejeitada' ? 'selected' : '' ?>>Rejeitada</option>
        <option value="concluida" <?= $warranty['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option>
    </select>

    <label for="resolution_note">Nota de resolução (visível pro cliente)</label>
    <textarea id="resolution_note" name="resolution_note" rows="4"></textarea>

    <button type="submit" class="btn btn-primary" style="margin-top:16px">Salvar</button>
</form>
<?php endif; ?>
