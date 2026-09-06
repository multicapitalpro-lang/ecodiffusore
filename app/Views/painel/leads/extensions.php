<?php
use App\Core\View;
/** @var array $requests */
?>
<h1>Extensões de Prazo de Lead</h1>
<p class="hint-text" style="margin-top:0;">Histórico de justificativas dadas pelo Vendedor pra manter um Lead por mais tempo, antes de completar os 30 dias sem conversão. A extensão vale na hora (não precisa de aprovação) — essa tela é só pra acompanhamento.</p>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>Data do pedido</th><th>Lead</th><th>Solicitado por</th><th>Justificativa</th><th>Prazo anterior</th><th>Novo prazo</th><th>Anexo</th></tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
                    <td>
                        <?= View::e($r['lead_name']) ?>
                        <br><small class="hint-text"><?= View::e($r['lead_city'] ?: '—') ?> · 💬 <?= View::e($r['lead_whatsapp']) ?></small>
                    </td>
                    <td><?= View::e($r['requested_by_name'] ?: '—') ?></td>
                    <td style="max-width:320px;white-space:pre-wrap;"><?= View::e($r['justification']) ?></td>
                    <td><?= $r['previous_expires_at'] ? View::e(date('d/m/Y', strtotime($r['previous_expires_at']))) : '—' ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($r['new_expires_at']))) ?></td>
                    <td>
                        <?php if ($r['attachment_path']): ?>
                            <a href="/painel/leads/extensoes/<?= (int) $r['id'] ?>/anexo" target="_blank" rel="noopener">Ver anexo</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="7">Nenhuma extensão de prazo registrada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
