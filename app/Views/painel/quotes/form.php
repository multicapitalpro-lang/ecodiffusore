<?php
use App\Core\Csrf;
$values = $editing;
$isVendedor = ($user['role_slug'] ?? '') === 'vendedor';
$preselectClientId = 0;
$isModal = $isModal ?? false;
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Editar Orçamento #<?= (int) $editing['id'] ?></h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <h1>Editar Orçamento #<?= (int) $editing['id'] ?></h1>
<?php endif; ?>

<form action="/painel/orcamentos/<?= (int) $editing['id'] ?>" method="post" class="panel-form panel-form-wide<?= $isModal ? ' ajax-form' : '' ?>" id="order-form">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <div class="<?= $isModal ? 'modal-form-actions' : '' ?>">
        <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
        <?php if ($isModal): ?>
            <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
        <?php else: ?>
            <a href="/painel/orcamentos" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
    </div>
</form>
<?php if ($isModal): ?></div><?php endif; ?>
