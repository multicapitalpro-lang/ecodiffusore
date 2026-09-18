<?php
use App\Core\Csrf;
use App\Core\Roles;
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
$stats = $stats ?? ['concluidos' => 0, 'pendentes' => 0, 'pagos' => 0, 'cancelados' => 0];
$isVendedor = ($user['role_slug'] ?? '') === 'vendedor';
$showLicenciadoColumn = $showLicenciadoColumn ?? false;
$isViewOnly = in_array($user['role_slug'] ?? '', Roles::NATIONAL_SUPPORT, true);
$preselectClientId = (int) ($_GET['cliente_id'] ?? 0);
$openModal = (isset($_GET['novo']) || $errors) && !$isViewOnly;
$situacaoPagamento = $situacaoPagamento ?? null;
?>
<div class="page-header">
    <h1>Pedidos de Venda</h1>
    <div class="page-header-actions">
        <a href="/painel/pedidos/exportar?<?= http_build_query($filters ?? []) ?>" class="btn btn-outline">Exportar CSV</a>
        <?php if (!$isViewOnly): ?>
            <button type="button" class="btn btn-primary" data-modal-open="modal-order">+ Incluir Pedido</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($situacaoPagamento === 'pendente'): ?>
    <p class="form-msg form-msg-erro">Mostrando só pedidos com pagamento pendente ou expirado. <a href="/painel/pedidos">Limpar filtro</a></p>
<?php elseif ($situacaoPagamento === 'pago'): ?>
    <p class="form-msg form-msg-ok">Mostrando só pedidos pagos. <a href="/painel/pedidos">Limpar filtro</a></p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Concluídos</span>
        <strong><?= (int) $stats['concluidos'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Pendentes de pagamento</span>
        <strong><?= (int) $stats['pendentes'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Pagos</span>
        <strong><?= (int) $stats['pagos'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Cancelados</span>
        <strong><?= (int) $stats['cancelados'] ?></strong>
    </div>
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
    <input type="text" name="city" placeholder="Cidade da compra" value="<?= View::e($filters['city'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>#</th><th>Cliente</th><th>Telefone</th><th>Produto</th><th>Veículo</th><th>Cidade</th><th>Vendedor</th><?php if ($showLicenciadoColumn): ?><th>Licenciado</th><?php endif; ?><th>Data</th><th>Total</th><th>Pagamento</th><th>Situação</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <?php $situation = $o['payment_situation'] ?? ['label' => '—', 'badge' => 'novo']; ?>
                <tr>
                    <td><button type="button" class="link-button" data-view-order="<?= (int) $o['id'] ?>">#<?= (int) $o['id'] ?></button></td>
                    <td><?= View::e($o['client_name']) ?></td>
                    <td>
                        <?php if (!empty($o['client_whatsapp'])): ?>
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $o['client_whatsapp']) ?>" target="_blank" rel="noopener" class="link-small">💬 <?= View::e($o['client_whatsapp']) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= View::e($o['product_names'] ?: '—') ?></td>
                    <td><?= View::e($o['vehicle_type'] ?: '—') ?><?= $o['vehicle_plate'] ? ' (' . View::e($o['vehicle_plate']) . ')' : '' ?></td>
                    <td><?= View::e($o['client_city'] ?: '—') ?></td>
                    <td><?= View::e($o['seller_name'] ?: '—') ?></td>
                    <?php if ($showLicenciadoColumn): ?><td><?= View::e($o['licenciado_name'] ?? '—') ?></td><?php endif; ?>
                    <td><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                    <td>R$ <?= number_format((float) $o['total_value'], 2, ',', '.') ?></td>
                    <td><?= View::e($o['payment_method'] ?: '—') ?></td>
                    <td>
                        <span class="status-badge status-<?= View::e($situation['badge']) ?>"><?= View::e($situation['label']) ?></span>
                        <?php if ($o['status'] === 'verificado' && !\App\Models\Order::hasRequiredDocuments($o)): ?>
                            <br><small class="hint-text" style="color:#b3790f;">⚠️ Cadastro do veículo pendente p/ fábrica</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="link-button" data-view-order="<?= (int) $o['id'] ?>">Ver</button>
                        <?php if ($o['status'] === 'em_andamento' && !$isViewOnly): ?>
                            · <button type="button" class="link-button" data-edit-order="<?= (int) $o['id'] ?>">Editar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="<?= $showLicenciadoColumn ? 13 : 12 ?>">Nenhum pedido encontrado.</td></tr>
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
        <form action="/painel/pedidos" method="post" enctype="multipart/form-data" class="panel-form panel-form-wide ajax-form" id="order-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar Pedido</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal" id="modal-order-detail">
    <div id="modal-order-detail-content">
        <div class="modal-header"><h2>Pedido</h2><button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>
        <div class="modal-body"><p class="hint-text">Carregando...</p></div>
    </div>
</dialog>

<?php $redirectTo = '/painel/pedidos'; include __DIR__ . '/../_client_quick_modal.php'; ?>
