<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\FinancialTransaction;
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'conciliado' => 'Conciliado'];
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
$values = $values ?? [];
$filters = $filters ?? [];
$openModal = isset($_GET['novo']) || $errors;
$personLabel = $type === 'entrada' ? 'Cliente' : 'Fornecedor';
$today = date('Y-m-d');
?>
<div class="page-header">
    <h1><?= View::e($title) ?></h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-payable">+ Incluir Conta</button>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso.</p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Em aberto</span>
        <strong><?= $summary['open_count'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Valor em aberto</span>
        <strong>R$ <?= number_format($summary['open_total'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card <?= $summary['overdue_count'] > 0 ? 'dash-card-danger' : '' ?>">
        <span>Vencidas</span>
        <strong><?= $summary['overdue_count'] ?></strong>
        <small><?php if ($summary['overdue_count'] > 0): ?>R$ <?= number_format($summary['overdue_total'], 2, ',', '.') ?><?php endif; ?></small>
    </div>
    <div class="dash-card">
        <span>Total pago</span>
        <strong>R$ <?= number_format($summary['paid_total'], 2, ',', '.') ?></strong>
    </div>
</div>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($filters['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= View::e($filters['to'] ?? '') ?>">
    <select name="status">
        <option value="">Todas as situações</option>
        <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <select name="category_id">
        <option value="">Todas as categorias</option>
        <?php foreach ($categoryGroups as $group): ?>
            <optgroup label="<?= View::e($group['parent']['name']) ?>">
                <?php foreach ($group['children'] as $child): ?>
                    <option value="<?= (int) $child['id'] ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $child['id'] ? 'selected' : '' ?>><?= View::e($child['name']) ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <select name="client_id">
        <option value="">Todos (<?= mb_strtolower($personLabel) ?>)</option>
        <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php if ($filters): ?><a href="<?= $type === 'entrada' ? '/painel/financeiro/contas-a-receber' : '/painel/financeiro/contas-a-pagar' ?>" class="link-small">Limpar filtros</a><?php endif; ?>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th><?= $personLabel ?></th><th>Vencimento</th><th>Categoria</th><th>Valor</th><th>Situação</th><th>Anexos</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e($t['client_name'] ?: '—') ?></td>
                    <td class="<?= $t['status'] === 'pendente' && $t['due_date'] < $today ? 'text-red' : '' ?>"><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['category_name'] ?: '—') ?></td>
                    <td>
                        R$ <?= number_format(FinancialTransaction::totalValue($t), 2, ',', '.') ?>
                        <?php if (!empty($t['recurrence_frequency'])): ?><span class="tag-default" title="Lançamento recorrente">🔁</span><?php endif; ?>
                    </td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                    <td><?php $items = $attachmentsByTransaction[$t['id']] ?? []; include __DIR__ . '/_attachments_cell.php'; ?></td>
                    <td class="table-actions">
                        <?php if ($t['status'] === 'pendente'): ?>
                            <form action="/painel/financeiro/contas/<?= (int) $t['id'] ?>/baixar" method="post" class="inline-form">
                                <?= Csrf::field() ?>
                                <button type="submit" class="link-button">Dar baixa</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($t['order_id'])): ?>
                            <button type="button" class="link-small" data-view-order="<?= (int) $t['order_id'] ?>">Ver pedido</button>
                        <?php endif; ?>
                        <button type="button" class="link-small" data-edit-transaction="<?= (int) $t['id'] ?>">Editar</button>
                        <?php if (empty($t['order_id'])): ?>
                            <form action="/painel/financeiro/contas/<?= (int) $t['id'] ?>/excluir" method="post" class="inline-form">
                                <?= Csrf::field() ?>
                                <button type="submit" class="link-button icon-button-danger" data-confirm="Excluir esse lançamento? Essa ação não pode ser desfeita.">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="7">Nenhum lançamento encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<dialog class="modal modal-drawer" id="modal-payable" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Conta a <?= $type === 'entrada' ? 'receber' : 'pagar' ?></h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/financeiro/contas" method="post" class="panel-form ajax-form" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_payable_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" name="salvar_e_dar_baixa" value="0" class="btn btn-outline">Salvar</button>
                <button type="submit" name="salvar_e_dar_baixa" value="1" class="btn btn-primary">Salvar e Dar Baixa</button>
            </div>
        </form>
    </div>
</dialog>

<?php include __DIR__ . '/_edit_transaction_modal.php'; ?>

<?php $redirectTo = $type === 'entrada' ? '/painel/financeiro/contas-a-receber' : '/painel/financeiro/contas-a-pagar'; include __DIR__ . '/../_client_quick_modal.php'; ?>
