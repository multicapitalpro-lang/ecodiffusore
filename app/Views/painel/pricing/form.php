<?php
use App\Core\Csrf;
$values = $editing;
?>
<h1>Editar faixa de preço</h1>

<form action="/painel/tabela-precos/<?= (int) $editing['id'] ?>" method="post" class="panel-form">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <button type="submit" class="btn btn-primary">Salvar</button>
    <a href="/painel/tabela-precos" class="btn btn-outline">Cancelar</a>
</form>
