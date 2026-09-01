<?php
use App\Core\Csrf;
$values = $editing;
$isVendedor = ($user['role_slug'] ?? '') === 'vendedor';
$preselectClientId = 0;
?>
<h1>Editar Orçamento #<?= (int) $editing['id'] ?></h1>

<form action="/painel/orcamentos/<?= (int) $editing['id'] ?>" method="post" class="panel-form panel-form-wide" id="order-form">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
    <a href="/painel/orcamentos" class="btn btn-outline">Cancelar</a>
</form>
