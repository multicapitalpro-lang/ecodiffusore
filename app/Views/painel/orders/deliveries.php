<?php
use App\Core\View;
?>
<div class="page-header">
    <h1>Acompanhar Entregas</h1>
</div>

<p class="hint-text">Pedidos pagos e o status de entrega informado pela fábrica.</p>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Produto</th><th>Data do pedido</th><th>Transportadora</th><th>Código</th><th>Previsão de entrega</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <?php $atrasado = !empty($o['prazo_entrega']) && strtotime($o['prazo_entrega']) < strtotime(date('Y-m-d')); ?>
                <tr>
                    <td>#<?= (int) $o['id'] ?></td>
                    <td><?= View::e($o['client_name']) ?></td>
                    <td><?= View::e($o['product_names'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                    <td><?= View::e($o['tracking_carrier'] ?: '—') ?></td>
                    <td><?= View::e($o['tracking_code'] ?: '—') ?></td>
                    <td<?= $atrasado ? ' style="color:#c53030;font-weight:600"' : '' ?>><?= !empty($o['prazo_entrega']) ? View::e(date('d/m/Y', strtotime($o['prazo_entrega']))) : '—' ?></td>
                    <td><a href="/painel/pedidos/<?= (int) $o['id'] ?>">Ver pedido</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="8">Nenhum pedido pago no seu escopo.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
