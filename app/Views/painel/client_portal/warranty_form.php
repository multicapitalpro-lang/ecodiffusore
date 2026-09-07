<?php
use App\Core\Csrf;
use App\Core\View;
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Solicitar Garantia — Pedido #<?= (int) $order['id'] ?></h1>
</div>

<?php if ($erro === '1'): ?>
    <p class="form-msg form-msg-error">Descreva o problema antes de enviar.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-error">Não foi possível enviar o anexo (verifique o tipo e o tamanho — máximo 5MB, PDF/JPG/PNG/WEBP).</p>
<?php endif; ?>

<form method="post" action="/painel/minhas-garantias" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

    <label for="description">Descreva o problema</label>
    <textarea id="description" name="description" rows="5" required></textarea>

    <label for="attachment">Anexo (foto ou documento, opcional)</label>
    <input type="file" id="attachment" name="attachment" accept="application/pdf,image/jpeg,image/png,image/webp">

    <button type="submit" class="btn btn-primary" style="margin-top:16px">Enviar solicitação</button>
</form>
