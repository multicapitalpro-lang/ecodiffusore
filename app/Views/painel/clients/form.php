<?php
use App\Core\Csrf;
use App\Core\View;
$values = $editing;
$isModal = $isModal ?? false;
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Editar cliente</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <h1>Editar cliente</h1>
<?php endif; ?>

<form action="/painel/clientes/<?= (int) $editing['id'] ?>" method="post" class="panel-form<?= $isModal ? ' ajax-form' : '' ?>">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <div class="<?= $isModal ? 'modal-form-actions' : '' ?>">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <?php if ($isModal): ?>
            <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
        <?php else: ?>
            <a href="/painel/clientes" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
    </div>
</form>
<?php if ($isModal): ?></div><?php endif; ?>
