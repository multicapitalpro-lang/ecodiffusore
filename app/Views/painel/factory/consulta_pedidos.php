<?php
use App\Core\View;
$statusLabels = [
    'em_andamento' => 'Em andamento',
    'atendido' => 'Atendido',
    'verificado' => 'Pago',
    'cancelado' => 'Cancelado',
];
?>
<div class="page-header">
    <h1>Consultar Pedidos</h1>
</div>
<p class="hint-text" style="margin-top:0;">Pra responder um cliente que liga perguntando do pedido dele — nome, modelo(s), cidade e prazo de entrega vendido. Sem valores nem dados de comissão.</p>

<form class="filter-bar" onsubmit="return false;">
    <input type="text" id="pedidos-search" placeholder="Buscar por cliente, cidade, produto ou nº do pedido...">
</form>

<div class="table-scroll">
    <table class="data-table" id="pedidos-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Cidade/UF</th>
                <th>Modelo(s)</th>
                <th>Prazo de entrega vendido</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr data-search="<?= View::e(mb_strtolower('#' . $o['id'] . ' ' . $o['client_name'] . ' ' . $o['client_city'] . ' ' . $o['product_names'])) ?>">
                    <td>#<?= (int) $o['id'] ?><br><small class="hint-text"><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></small></td>
                    <td><?= View::e($o['client_name']) ?></td>
                    <td><?= $o['client_city'] ? View::e($o['client_city']) . ($o['client_state'] ? '/' . View::e($o['client_state']) : '') : '—' ?></td>
                    <td><?= View::e($o['product_names'] ?: '—') ?></td>
                    <td><?= $o['prazo_entrega'] ? View::e(date('d/m/Y', strtotime($o['prazo_entrega']))) : '—' ?></td>
                    <td><span class="status-badge status-<?= $o['status'] === 'verificado' ? 'active' : 'novo' ?>"><?= $statusLabels[$o['status']] ?? $o['status'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="6">Nenhum pedido encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
    var searchInput = document.getElementById('pedidos-search');
    var rows = Array.from(document.querySelectorAll('#pedidos-table tbody tr[data-search]'));
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
        var term = searchInput.value.trim().toLowerCase();
        rows.forEach(function (row) {
            row.hidden = term !== '' && row.getAttribute('data-search').indexOf(term) === -1;
        });
    });
})();
</script>
