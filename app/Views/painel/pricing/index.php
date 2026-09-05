<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
$errors = $errors ?? [];
$values = $values ?? [];
$openModal = isset($_GET['novo']) || $errors;
?>
<div class="page-header">
    <h1>Tabela de preços</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-tier">+ Nova faixa</button>
</div>

<p class="hint-text" style="margin-top:0;">Preço unitário e % de comissão do Licenciado variam pela quantidade total de placas no mesmo pedido — a faixa aplicada é sempre a de maior "a partir de" que ainda seja menor ou igual à quantidade comprada.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Faixa salva com sucesso.</p>
<?php elseif ($erro === 'vinculo'): ?>
    <p class="form-msg form-msg-erro">Essa faixa não pode ser excluída porque já tem comissões vinculadas a ela.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir a ação.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>A partir de</th><th>Preço unitário</th><th>Comissão Licenciado</th><th>Custo</th><th>Imposto</th><th></th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($tiers as $t): ?>
                <tr>
                    <td><?= (int) $t['min_qty'] ?> placa<?= (int) $t['min_qty'] > 1 ? 's' : '' ?></td>
                    <td>R$ <?= number_format((float) $t['unit_price'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) $t['licenciado_commission_pct'], 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format((float) $t['cost_price'], 2, ',', '.') ?></td>
                    <td><?= number_format((float) $t['tax_pct'], 2, ',', '.') ?>%</td>
                    <td><a href="/painel/tabela-precos/<?= (int) $t['id'] ?>/editar">Editar</a></td>
                    <td>
                        <form method="post" action="/painel/tabela-precos/<?= (int) $t['id'] ?>/excluir" style="display:inline;">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn-remove-row" data-confirm="Excluir esta faixa de preço?" aria-label="Excluir">&times;</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<dialog class="modal" id="modal-tier" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Nova faixa de preço</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/tabela-precos" method="post" class="panel-form ajax-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar faixa</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
