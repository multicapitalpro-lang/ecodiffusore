<?php
use App\Core\Csrf;
use App\Core\View;

$hasLinks = $impact['orders'] || $impact['quotes'] || (int) $impact['financial_transactions']['qty'] > 0 || $impact['warranty_requests_count'] > 0;
?>
<div class="modal-header">
    <h2>Excluir <?= View::e($client['name']) ?></h2>
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
</div>
<div class="modal-body">
    <?php if (!$hasLinks): ?>
        <p>Esse cliente não tem pedido, orçamento ou lançamento financeiro vinculado.</p>
    <?php else: ?>
        <p class="form-msg form-msg-erro" style="margin-bottom:16px;">
            Esse cliente tem registros vinculados. Excluir vai apagar <strong>tudo isso junto</strong> -- não pode ser desfeito.
        </p>

        <?php if ($impact['orders']): ?>
            <h3 style="font-size:.9rem;margin:0 0 8px;">Pedidos (<?= count($impact['orders']) ?>)</h3>
            <table class="data-table data-table-compact" style="margin-bottom:16px;">
                <thead><tr><th>#</th><th>Data</th><th>Status</th><th>Valor</th></tr></thead>
                <tbody>
                    <?php foreach ($impact['orders'] as $o): ?>
                        <tr>
                            <td>#<?= (int) $o['id'] ?></td>
                            <td><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                            <td><?= View::e($o['status']) ?></td>
                            <td>R$ <?= View::e(number_format((float) $o['total_value'], 2, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($impact['quotes']): ?>
            <h3 style="font-size:.9rem;margin:0 0 8px;">Orçamentos (<?= count($impact['quotes']) ?>)</h3>
            <table class="data-table data-table-compact" style="margin-bottom:16px;">
                <thead><tr><th>#</th><th>Data</th><th>Status</th><th>Valor</th></tr></thead>
                <tbody>
                    <?php foreach ($impact['quotes'] as $q): ?>
                        <tr>
                            <td>#<?= (int) $q['id'] ?></td>
                            <td><?= View::e(date('d/m/Y', strtotime($q['quote_date']))) ?></td>
                            <td><?= View::e($q['status']) ?></td>
                            <td>R$ <?= View::e(number_format((float) $q['total_value'], 2, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ((int) $impact['financial_transactions']['qty'] > 0): ?>
            <p><strong><?= (int) $impact['financial_transactions']['qty'] ?></strong> lançamento(s) financeiro(s) vinculado(s) diretamente a este cliente, somando R$ <?= View::e(number_format((float) $impact['financial_transactions']['total'], 2, ',', '.')) ?>.</p>
        <?php endif; ?>

        <?php if ($impact['warranty_requests_count'] > 0): ?>
            <p><strong><?= (int) $impact['warranty_requests_count'] ?></strong> registro(s) de pós-venda de instalação vinculado(s).</p>
        <?php endif; ?>
    <?php endif; ?>

    <form action="/painel/clientes/<?= (int) $client['id'] ?>/excluir-confirmado" method="post" class="ajax-form" style="margin-top:16px;">
        <?= Csrf::field() ?>
        <div data-error-for="geral" class="field-error"></div>
        <?php if ($hasLinks): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                <input type="checkbox" required>
                Entendo que isso vai excluir também os pedidos/orçamentos vinculados e não pode ser desfeito.
            </label>
        <?php endif; ?>
        <div class="modal-form-actions">
            <button type="submit" class="btn btn-danger">Excluir <?= $hasLinks ? 'tudo' : 'cliente' ?></button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
        </div>
    </form>
</div>
