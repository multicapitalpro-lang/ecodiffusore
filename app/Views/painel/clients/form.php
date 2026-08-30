<?php
use App\Core\Csrf;
use App\Core\View;
$values = $editing;
?>
<h1>Editar cliente</h1>

<form action="/painel/clientes/<?= (int) $editing['id'] ?>" method="post" class="panel-form">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="/painel/clientes" class="btn btn-outline">Cancelar</a>
</form>
