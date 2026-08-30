<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = [
    'em_andamento' => 'Em andamento',
    'atendido' => 'Atendido',
    'verificado' => 'Verificado',
    'cancelado' => 'Cancelado',
];
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Pedido #<?= (int) $order['id'] ?></h1>
    <?php if ($order['status'] === 'em_andamento'): ?>
        <a href="/painel/pedidos/<?= (int) $order['id'] ?>/editar" class="btn btn-outline">Editar</a>
    <?php endif; ?>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Só é possível editar pedidos em andamento.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($order['client_name']) ?></p>
    <p><strong>Vendedor:</strong> <?= View::e($order['seller_name'] ?: 'Sem vendedor') ?></p>
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y', strtotime($order['order_date']))) ?></p>
    <p><strong>Situação:</strong> <span class="status-badge status-<?= $order['status'] === 'verificado' ? 'active' : ($order['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$order['status']] ?? $order['status'] ?></span></p>
    <?php if ($order['notes']): ?><p><strong>Obs.:</strong> <?= nl2br(View::e($order['notes'])) ?></p><?php endif; ?>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= View::e($item['product_name']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td>R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td><strong>R$ <?= number_format((float) $order['total_value'], 2, ',', '.') ?></strong></td></tr>
        </tfoot>
    </table>
</div>

<?php if ($order['status'] !== 'cancelado' && $order['status'] !== 'verificado'): ?>
<div class="order-actions">
    <?php if ($order['status'] === 'em_andamento'): ?>
        <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="status" value="atendido">
            <button type="submit" class="btn btn-outline">Marcar como Atendido</button>
        </form>
    <?php endif; ?>
    <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="verificado">
        <button type="submit" class="btn btn-primary">Marcar como Verificado</button>
    </form>
    <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="cancelado">
        <button type="submit" class="btn btn-danger">Cancelar Pedido</button>
    </form>
</div>
<p class="hint-text">"Verificado" confirma o recebimento: gera a comissão do vendedor e o lançamento em Contas a Receber automaticamente.</p>
<?php endif; ?>
