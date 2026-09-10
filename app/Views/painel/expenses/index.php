<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
$errors = $errors ?? [];
$values = $values ?? [];
$filters = $filters ?? [];
$openModal = isset($_GET['novo']) || $errors;
?>
<div class="page-header">
    <h1>Meus Custos</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-expense">+ Novo custo</button>
</div>

<p class="hint-text" style="margin-top:0;">Cadastre os custos completos da sua operação (aluguel, veículo, combustível, salários, marketing, o que fizer sentido pra você) — categoria livre, do seu jeito.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir.</p>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($filters['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= View::e($filters['to'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php if ($filters): ?><a class="link-small" href="/painel/meus-custos">Tudo</a><?php endif; ?>
</form>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total no período</span>
        <strong>R$ <?= number_format($totals['total'], 2, ',', '.') ?></strong>
    </div>
</div>

<?php if ($totals['by_category']): ?>
    <h3 class="section-title">Por categoria</h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Categoria</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach ($totals['by_category'] as $category => $amount): ?>
                    <tr>
                        <td><?= View::e($category) ?></td>
                        <td>R$ <?= number_format($amount, 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h3 class="section-title">Lançamentos</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Data</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($expenses as $e): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($e['expense_date'])) ?></td>
                    <td><?= View::e($e['category']) ?></td>
                    <td><?= View::e($e['description'] ?: '—') ?></td>
                    <td>R$ <?= number_format((float) $e['amount'], 2, ',', '.') ?></td>
                    <td>
                        <form method="post" action="/painel/meus-custos/<?= (int) $e['id'] ?>/excluir" style="display:inline;">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn-remove-row" data-confirm="Excluir este custo?" aria-label="Excluir">&times;</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$expenses): ?>
                <tr><td colspan="5">Nenhum custo cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<dialog class="modal" id="modal-expense" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Novo custo</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/meus-custos" method="post" class="panel-form">
            <?= Csrf::field() ?>

            <label for="category">Categoria</label>
            <input type="text" id="category" name="category" list="expense-categories" value="<?= View::e((string) ($values['category'] ?? '')) ?>" placeholder="Ex: Aluguel, Combustível, Marketing..." required>
            <datalist id="expense-categories">
                <?php foreach ($categories as $c): ?><option value="<?= View::e($c) ?>"><?php endforeach; ?>
            </datalist>
            <p class="field-error" data-error-for="category"><?= View::e($errors['category'] ?? '') ?></p>

            <label for="description">Descrição (opcional)</label>
            <input type="text" id="description" name="description" value="<?= View::e((string) ($values['description'] ?? '')) ?>">

            <div class="form-grid-2">
                <div>
                    <label for="amount">Valor (R$)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?= View::e((string) ($values['amount'] ?? '')) ?>" required>
                    <p class="field-error" data-error-for="amount"><?= View::e($errors['amount'] ?? '') ?></p>
                </div>
                <div>
                    <label for="expense_date">Data</label>
                    <input type="date" id="expense_date" name="expense_date" value="<?= View::e((string) ($values['expense_date'] ?? date('Y-m-d'))) ?>" required>
                    <p class="field-error" data-error-for="expense_date"><?= View::e($errors['expense_date'] ?? '') ?></p>
                </div>
            </div>

            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
