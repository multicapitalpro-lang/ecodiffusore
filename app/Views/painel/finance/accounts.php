<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'conciliado' => 'Conciliado'];
?>
<h1>Caixas e Bancos</h1>

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

<div class="two-col">
    <div>
        <h3 class="section-title">Nova conta</h3>
        <form action="/painel/financeiro/caixas-bancos/contas" method="post" class="panel-form">
            <?= Csrf::field() ?>
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
    </div>

    <div>
        <h3 class="section-title">Novo lançamento</h3>
        <form action="/painel/financeiro/lancamentos" method="post" class="panel-form">
            <?= Csrf::field() ?>
            <label for="account_id">Conta</label>
            <select id="account_id" name="account_id" required>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>"><?= View::e($acc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="type2">Tipo</label>
            <select id="type2" name="type">
                <option value="entrada">Entrada</option>
                <option value="saida">Saída</option>
            </select>
            <label for="category">Categoria</label>
            <input type="text" id="category" name="category" placeholder="Ex: Aluguel, Fornecedor, Venda" required>
            <label for="description">Descrição</label>
            <input type="text" id="description" name="description">
            <label for="amount">Valor (R$)</label>
            <input type="number" step="0.01" id="amount" name="amount" required>
            <label for="due_date">Data</label>
            <input type="date" id="due_date" name="due_date" value="<?= date('Y-m-d') ?>" required>
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="pendente">Pendente</option>
                <option value="pago">Pago</option>
            </select>
            <button type="submit" class="btn btn-primary">Lançar</button>
        </form>
    </div>
</div>

<h3 class="section-title">Movimentações</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Data</th><th>Conta</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Situação</th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['account_name']) ?></td>
                    <td><?= $t['type'] === 'entrada' ? 'Entrada' : 'Saída' ?></td>
                    <td><?= View::e($t['category']) ?></td>
                    <td><?= View::e($t['description'] ?: '—') ?></td>
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
