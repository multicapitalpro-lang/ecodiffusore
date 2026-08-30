<?php
use App\Core\Csrf;
$values = $editing;
$isLicenciado = ($user['role_slug'] ?? '') === 'licenciado';
$preselectClientId = 0;
?>
<h1>Editar Pedido #<?= (int) $editing['id'] ?></h1>

<form action="/painel/pedidos/<?= (int) $editing['id'] ?>" method="post" class="panel-form panel-form-wide" id="order-form">
    <?= Csrf::field() ?>
    <?php include __DIR__ . '/_fields.php'; ?>
    <button type="submit" class="btn btn-primary">Salvar Pedido</button>
    <a href="/painel/pedidos" class="btn btn-outline">Cancelar</a>
</form>
