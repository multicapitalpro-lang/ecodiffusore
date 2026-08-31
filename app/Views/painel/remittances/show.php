<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['aberta' => 'Aberta', 'enviada' => 'Enviada', 'retornada' => 'Retornada'];
$sucesso = isset($_GET['sucesso']);
$total = array_sum(array_column($items, 'amount'));
?>
<h1>Remessa #<?= (int) $remittance['id'] ?></h1>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Tipo:</strong> <?= $remittance['type'] === 'pagar' ? 'A Pagar' : 'A Receber' ?></p>
    <p><strong>Conta:</strong> <?= View::e($remittance['account_name']) ?></p>
    <p><strong>Situação:</strong> <span class="status-badge status-<?= $remittance['status'] === 'aberta' ? 'contatado' : ($remittance['status'] === 'retornada' ? 'active' : 'novo') ?>"><?= $statusLabels[$remittance['status']] ?? $remittance['status'] ?></span></p>
    <?php if ($remittance['sent_at']): ?><p><strong>Enviada em:</strong> <?= View::e(date('d/m/Y H:i', strtotime($remittance['sent_at']))) ?></p><?php endif; ?>
    <?php if ($remittance['returned_at']): ?><p><strong>Retorno registrado em:</strong> <?= View::e(date('d/m/Y H:i', strtotime($remittance['returned_at']))) ?></p><?php endif; ?>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th><?= $remittance['type'] === 'pagar' ? 'Fornecedor' : 'Cliente' ?></th><th>Vencimento</th><th>Valor</th><th>Situação</th></tr></thead>
        <tbody>
            <?php foreach ($items as $t): ?>
                <tr>
                    <td><?= View::e($t['client_name'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td>R$ <?= number_format((float) $t['amount'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $t['status'] === 'pendente' ? 'Pendente' : 'Pago' ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="2" style="text-align:right"><strong>Total</strong></td><td colspan="2"><strong>R$ <?= number_format($total, 2, ',', '.') ?></strong></td></tr>
        </tfoot>
    </table>
</div>

<div class="order-actions">
    <?php if ($remittance['status'] === 'aberta'): ?>
        <form action="/painel/financeiro/remessas/<?= (int) $remittance['id'] ?>/enviar" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary">Marcar como Enviada</button>
        </form>
    <?php elseif ($remittance['status'] === 'enviada'): ?>
        <form action="/painel/financeiro/remessas/<?= (int) $remittance['id'] ?>/retorno" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary">Registrar Retorno (dar baixa em todos)</button>
        </form>
    <?php endif; ?>
</div>
