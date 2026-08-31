<?php
use App\Core\View;
$actionLabels = [
    'usuario_commission_pct_alterado' => 'Comissão alterada',
    'usuario_discount_limit_pct_alterado' => 'Limite de desconto alterado',
    'usuario_manager_id_alterado' => 'Hierarquia alterada',
    'pedido_verificado' => 'Pedido verificado',
    'orcamento_aprovado' => 'Orçamento aprovado',
    'orcamento_recusado' => 'Orçamento recusado',
    'orcamento_convertido' => 'Orçamento convertido em pedido',
    'aprovacao_desconto_aprovado' => 'Desconto aprovado',
    'aprovacao_desconto_recusado' => 'Desconto recusado',
];
?>
<h1>Auditoria</h1>
<p class="section-sub">Últimas 200 ações registradas no sistema.</p>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Data</th><th>Quem</th><th>Ação</th><th>Entidade</th><th>Antes</th><th>Depois</th></tr></thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></td>
                    <td><?= View::e($log['user_name'] ?? 'Sistema') ?></td>
                    <td><?= View::e($actionLabels[$log['action']] ?? $log['action']) ?></td>
                    <td><?= $log['entity_type'] ? View::e($log['entity_type']) . ' #' . (int) $log['entity_id'] : '—' ?></td>
                    <td><small><?= View::e($log['before_value'] ?: '—') ?></small></td>
                    <td><small><?= View::e($log['after_value'] ?: '—') ?></small></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="6">Nenhuma ação registrada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
