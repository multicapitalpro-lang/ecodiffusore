<?php
use App\Core\View;
use App\Models\WarrantyRequest;
$statusLabels = ['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'aprovada' => 'Aprovada', 'rejeitada' => 'Rejeitada', 'concluida' => 'Concluída'];
$statusBadge = ['aberta' => 'novo', 'em_analise' => 'contatado', 'aprovada' => 'active', 'rejeitada' => 'inactive', 'concluida' => 'active'];
?>
<div class="page-header">
    <h1>Garantia — Pedido #<?= (int) $warranty['order_id'] ?></h1>
    <a href="/painel/minhas-garantias" class="btn btn-outline">← Minhas garantias</a>
</div>

<div class="order-summary">
    <p><strong>Aberta em:</strong> <?= View::e(date('d/m/Y', strtotime($warranty['created_at']))) ?></p>
    <p><strong>Status:</strong> <span class="status-badge status-<?= $statusBadge[$warranty['status']] ?? 'novo' ?>"><?= $statusLabels[$warranty['status']] ?? $warranty['status'] ?></span></p>
    <p><strong>Descrição:</strong></p>
    <p><?= nl2br(View::e($warranty['description'])) ?></p>
    <?php if ($attachments): ?>
        <p><strong>Documentos enviados:</strong></p>
        <ul>
            <?php foreach ($attachments as $a): ?>
                <li><a href="/painel/minhas-garantias/<?= (int) $warranty['id'] ?>/anexo/<?= (int) $a['id'] ?>" target="_blank" rel="noopener">📎 <?= View::e(WarrantyRequest::ATTACHMENT_LABELS[$a['type']] ?? $a['type']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!empty($warranty['resolution_note'])): ?>
        <p><strong>Retorno da equipe:</strong></p>
        <p><?= nl2br(View::e($warranty['resolution_note'])) ?></p>
    <?php endif; ?>
</div>
