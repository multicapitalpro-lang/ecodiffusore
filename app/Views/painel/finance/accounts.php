<?php
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'conciliado' => 'Conciliado'];
$openModal = !empty($errors);
?>
<div class="page-header">
    <h1>Caixas e Bancos</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-transaction">+ Incluir Lançamento</button>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Verifique os dados informados.</p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($accounts as $acc): ?>
        <div class="dash-card">
            <span><?= View::e($acc['name']) ?> (<?= $acc['type'] === 'caixa' ? 'Caixa' : 'Banco' ?>)</span>
            <strong>R$ <?= number_format((float) $acc['balance'], 2, ',', '.') ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<details class="inline-details">
    <summary>+ Nova conta financeira</summary>
    <form action="/painel/financeiro/caixas-bancos/contas" method="post" class="panel-form">
        <?= \App\Core\Csrf::field() ?>
        <label for="name">Nome</label>
        <input type="text" id="name" name="name" required>
        <label for="type">Tipo</label>
        <select id="type" name="type">
            <option value="caixa">Caixa</option>
            <option value="banco">Banco</option>
        </select>
        <label for="initial_balance">Saldo inicial (R$)</label>
        <input type="number" step="0.01" id="initial_balance" name="initial_balance" value="0">
        <button type="submit" class="btn btn-primary">Adicionar conta</button>
    </form>
</details>

<h3 class="section-title">Movimentações</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Data</th><th>Categoria</th><th>Histórico</th><th>Cliente/Fornecedor</th><th>Conta</th><th>Valor</th><th>Situação</th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['category_name'] ?: '—') ?></td>
                    <td><?= View::e($t['description'] ?: '—') ?></td>
                    <td><?= View::e($t['client_name'] ?: '—') ?></td>
                    <td><?= View::e($t['account_name']) ?></td>
                    <td class="<?= $t['type'] === 'entrada' ? 'text-green' : 'text-red' ?>">
                        <?= $t['type'] === 'entrada' ? '+' : '-' ?> R$ <?= number_format((float) $t['amount'], 2, ',', '.') ?>
                    </td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="7">Nenhuma movimentação registrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<dialog class="modal modal-drawer" id="modal-transaction" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Lançamento caixa</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/financeiro/lancamentos" method="post" class="panel-form ajax-form" enctype="multipart/form-data">
            <?= \App\Core\Csrf::field() ?>
            <?php include __DIR__ . '/_transaction_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<?php $redirectTo = '/painel/financeiro/caixas-bancos'; include __DIR__ . '/../_client_quick_modal.php'; ?>
