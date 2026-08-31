<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = [
    'em_andamento' => 'Em andamento',
    'atendido' => 'Atendido',
    'verificado' => 'Verificado',
    'cancelado' => 'Cancelado',
];
$errors = $errors ?? [];
$values = $values ?? [];
$items = $items ?? [];
$isLicenciado = ($user['role_slug'] ?? '') === 'licenciado';
$preselectClientId = (int) ($_GET['cliente_id'] ?? 0);
$openModal = isset($_GET['novo']) || $errors;
?>
<div class="page-header">
    <h1>Pedidos de Venda</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-order">+ Incluir Pedido</button>
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

<dialog class="modal" id="modal-order" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Incluir Pedido</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/pedidos" method="post" class="panel-form panel-form-wide ajax-form" id="order-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar Pedido</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<?php $redirectTo = '/painel/pedidos'; include __DIR__ . '/../_client_quick_modal.php'; ?>
