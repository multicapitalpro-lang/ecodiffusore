<?php
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\View;
$statusLabels = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'convertido' => 'Convertido'];
$errors = $errors ?? [];
$values = $values ?? [];
$items = $items ?? [];
$isVendedor = ($user['role_slug'] ?? '') === 'vendedor';
$isViewOnly = in_array($user['role_slug'] ?? '', Roles::NATIONAL_SUPPORT, true);
$preselectClientId = (int) ($_GET['cliente_id'] ?? 0);
$openModal = (isset($_GET['novo']) || $errors) && !$isViewOnly;
?>
<div class="page-header">
    <h1>Orçamentos</h1>
    <div class="page-header-actions">
        <a href="/painel/orcamentos/kanban" class="btn btn-outline">Ver como Kanban</a>
        <?php if (!$isViewOnly): ?>
            <button type="button" class="btn btn-primary" data-modal-open="modal-quote">+ Gerar Orçamento</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Cliente</th><th>Origem</th><th>Vendedor</th><th>Gerado em</th><th>Válido até</th><th>Total</th><th>Situação</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($quotes as $q): ?>
                <tr>
                    <td>#<?= (int) $q['id'] ?></td>
                    <td><?= View::e($q['client_name']) ?></td>
                    <td><?= !empty($q['lead_id']) ? '🌐 Site' : 'Interno' ?></td>
                    <td><?= View::e($q['seller_name'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($q['created_at']))) ?></td>
                    <td><?= $q['valid_until'] ? View::e(date('d/m/Y', strtotime($q['valid_until']))) : '—' ?></td>
                    <td>R$ <?= number_format((float) $q['total_value'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $q['status'] === 'convertido' || $q['status'] === 'aprovado' ? 'active' : ($q['status'] === 'recusado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$q['status']] ?? $q['status'] ?></span></td>
                    <td><a href="/painel/orcamentos/<?= (int) $q['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$quotes): ?>
                <tr><td colspan="9">Nenhum orçamento gerado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<dialog class="modal" id="modal-quote" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Gerar Orçamento</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/orcamentos" method="post" class="panel-form panel-form-wide ajax-form" id="order-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<?php $redirectTo = '/painel/orcamentos'; include __DIR__ . '/../_client_quick_modal.php'; ?>
