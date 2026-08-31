<?php
use App\Core\Csrf;
use App\Core\View;
$erro = isset($_GET['erro']);
?>
<h1>Nova Remessa — <?= $type === 'pagar' ? 'A Pagar' : 'A Receber' ?></h1>

<?php if ($erro): ?>
    <p class="form-msg form-msg-erro">Selecione a conta e pelo menos um título.</p>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="hidden" name="type" value="<?= View::e($type) ?>">
    <select name="account_id" onchange="this.form.submit()">
        <?php foreach ($accounts as $acc): ?>
            <option value="<?= (int) $acc['id'] ?>" <?= $accountId === (int) $acc['id'] ? 'selected' : '' ?>><?= View::e($acc['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<form action="/painel/financeiro/remessas" method="post" class="panel-form panel-form-wide">
    <?= Csrf::field() ?>
    <input type="hidden" name="type" value="<?= View::e($type) ?>">
    <input type="hidden" name="account_id" value="<?= $accountId ?>">

    <label for="payment_method">Forma de pagamento</label>
    <select id="payment_method" name="payment_method">
        <option value="">Todas</option>
        <option value="boleto">Boleto</option>
        <option value="pix">Pix</option>
        <option value="transferencia">Transferência</option>
    </select>

    <h3 class="section-title">Títulos em aberto nessa conta</h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th><input type="checkbox" onclick="this.closest('table').querySelectorAll('.item-check').forEach(c=>c.checked=this.checked)"></th>
                    <th><?= $type === 'pagar' ? 'Fornecedor' : 'Cliente' ?></th>
                    <th>Vencimento</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><input type="checkbox" class="item-check" name="transaction_ids[]" value="<?= (int) $t['id'] ?>"></td>
                        <td><?= View::e($t['client_name'] ?: '—') ?></td>
                        <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                        <td>R$ <?= number_format((float) $t['amount'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$transactions): ?>
                    <tr><td colspan="4">Nenhum título pendente nessa conta.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <button type="submit" class="btn btn-primary">Criar Remessa</button>
    <a href="/painel/financeiro/remessas" class="btn btn-outline">Cancelar</a>
</form>
