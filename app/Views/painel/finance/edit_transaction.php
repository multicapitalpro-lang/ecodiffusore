<?php
use App\Core\Csrf;
use App\Core\View;
$isModal = $isModal ?? false;
$type = $transaction['type'];
$values = $transaction;
$isEdit = true;
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Editar lançamento</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <h1>Editar lançamento</h1>
<?php endif; ?>

<form action="/painel/financeiro/contas/<?= (int) $transaction['id'] ?>" method="post" class="panel-form<?= $isModal ? ' ajax-form' : '' ?>">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_payable_fields.php'; ?>
    <div class="modal-form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
    </div>
</form>

<?php if ($isModal): ?>
    </div>
<?php endif; ?>
