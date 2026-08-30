<?php
use App\Core\Csrf;
$values = $editing;
?>
<h1>Editar produto</h1>

<form action="/painel/produtos/<?= (int) $editing['id'] ?>" method="post" class="panel-form">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="/painel/produtos" class="btn btn-outline">Cancelar</a>
</form>
