<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>📋 Aprovar Documentos</h1>
</div>
<p class="section-sub">Pedidos pagos, com CNH/documento do veículo/fotos/telemetria já enviados pelo cliente — confira os dados antes de liberar pra fábrica. Só depois dessa aprovação o pedido entra na fila de despacho.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Documentos aprovados — pedido liberado pra fábrica.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível aprovar esse pedido.</p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($orders as $o): ?>
        <div class="dash-card" style="text-align:left;">
            <span>Pedido #<?= (int) $o['id'] ?> · <?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></span>
            <strong style="font-size:1rem;"><?= View::e($o['client_name']) ?></strong>
            <span class="hint-inline">Vendedor: <?= View::e($o['seller_name'] ?: 'Sem vendedor') ?></span>
            <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
                <button type="button" class="link-button" data-view-order="<?= (int) $o['id'] ?>">Ver documentos</button>
                <form action="/painel/pedidos/<?= (int) $o['id'] ?>/aprovar-documentos" method="post" class="inline-form">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">✅ Aprovar e liberar pra fábrica</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$orders): ?>
        <p class="hint-text">Nenhum pedido esperando aprovação de documentos no momento.</p>
    <?php endif; ?>
</div>

<dialog class="modal" id="modal-order-detail">
    <div id="modal-order-detail-content"></div>
</dialog>
