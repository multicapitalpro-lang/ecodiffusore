<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\FinancialTransaction;
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'conciliado' => 'Conciliado'];
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
$values = $values ?? [];
$openModal = isset($_GET['novo']) || $errors;
$personLabel = $type === 'entrada' ? 'Cliente' : 'Fornecedor';
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
        <span>Quantidade em aberto</span>
        <strong><?= count(array_filter($transactions, fn ($t) => $t['status'] === 'pendente')) ?></strong>
    </div>
    <div class="dash-card">
        <span>Valor total em aberto</span>
        <strong>R$ <?= number_format($openTotal, 2, ',', '.') ?></strong>
    </div>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th><?= $personLabel ?></th><th>Vencimento</th><th>Categoria</th><th>Valor</th><th>Situação</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e($t['client_name'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['category_name'] ?: '—') ?></td>
                    <td>R$ <?= number_format(FinancialTransaction::totalValue($t), 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                    <td>
                        <?php if ($t['status'] === 'pendente'): ?>
                            <form action="/painel/financeiro/contas/<?= (int) $t['id'] ?>/baixar" method="post" class="inline-form">
                                <?= Csrf::field() ?>
                                <button type="submit" class="link-button">Dar baixa</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="6">Nenhum lançamento encontrado.</td></tr>
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

<?php $redirectTo = $type === 'entrada' ? '/painel/financeiro/contas-a-receber' : '/painel/financeiro/contas-a-pagar'; include __DIR__ . '/../_client_quick_modal.php'; ?>
