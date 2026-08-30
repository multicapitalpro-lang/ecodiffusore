<?php
use App\Core\View;
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago'];
?>
<h1>Comissões</h1>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Pedido</th><th>Vendedor</th><th>Cliente</th><th>Data</th><th>%</th><th>Comissão</th><th>Situação</th></tr></thead>
        <tbody>
            <?php foreach ($commissions as $c): ?>
                <tr>
                    <td>#<?= (int) $c['order_id'] ?></td>
                    <td><?= View::e($c['seller_name']) ?></td>
                    <td><?= View::e($c['client_name']) ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($c['order_date']))) ?></td>
                    <td><?= number_format((float) $c['percentage'], 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format((float) $c['amount'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $c['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$c['status']] ?? $c['status'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$commissions): ?>
                <tr><td colspan="7">Nenhuma comissão gerada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
