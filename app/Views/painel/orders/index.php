<?php
use App\Core\View;
$statusLabels = [
    'em_andamento' => 'Em andamento',
    'atendido' => 'Atendido',
    'verificado' => 'Verificado',
    'cancelado' => 'Cancelado',
];
?>
<div class="page-header">
    <h1>Pedidos de Venda</h1>
    <a href="/painel/pedidos/novo" class="btn btn-primary">+ Incluir Pedido</a>
</div>

<form method="get" class="filter-bar">
    <select name="status">
        <option value="">Todas as situações</option>
        <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= View::e($filters['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= View::e($filters['to'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>#</th><th>Cliente</th><th>Vendedor</th><th>Data</th><th>Total</th><th>Situação</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int) $o['id'] ?></td>
                    <td><?= View::e($o['client_name']) ?></td>
                    <td><?= View::e($o['seller_name'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                    <td>R$ <?= number_format((float) $o['total_value'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $o['status'] === 'verificado' ? 'active' : ($o['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
                    <td><a href="/painel/pedidos/<?= (int) $o['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="7">Nenhum pedido encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
