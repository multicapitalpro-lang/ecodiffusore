<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Pedidos pra Despachar</h1>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-error">Não foi possível atualizar.</p>
<?php endif; ?>

<?php foreach ($orders as $o): ?>
    <div class="dash-card" style="margin-bottom:16px;text-align:left;padding:16px 20px">
        <strong>Pedido #<?= (int) $o['id'] ?></strong> — <?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?>
        <p style="margin:8px 0 4px"><strong>Produto:</strong> <?= View::e($o['produtos'] ?: '—') ?></p>
        <p style="margin:0 0 4px"><strong>Destinatário:</strong> <?= View::e($o['client_name']) ?><?= $o['client_document'] ? ' — ' . View::e($o['client_document']) : '' ?></p>
        <p style="margin:0 0 12px">
            <strong>Endereço:</strong>
            <?= View::e($o['street'] ?: '—') ?><?= $o['number'] ? ', ' . View::e($o['number']) : '' ?><?= $o['complement'] ? ' - ' . View::e($o['complement']) : '' ?>
            — <?= View::e($o['neighborhood'] ?: '—') ?>, <?= View::e($o['city'] ?: '—') ?>/<?= View::e($o['state'] ?: '—') ?> — CEP <?= View::e($o['zip_code'] ?: '—') ?>
            <?php if (!empty($o['client_whatsapp'])): ?> — WhatsApp <?= View::e($o['client_whatsapp']) ?><?php endif; ?>
        </p>

        <form method="post" action="/painel/fabrica/<?= (int) $o['id'] ?>/entrega" class="panel-form">
            <?= Csrf::field() ?>
            <div class="form-grid-2">
                <div>
                    <label>Transportadora</label>
                    <input type="text" name="tracking_carrier" value="<?= View::e($o['tracking_carrier'] ?? '') ?>" placeholder="Ex: Correios, Jadlog">
                </div>
                <div>
                    <label>Código de rastreio</label>
                    <input type="text" name="tracking_code" value="<?= View::e($o['tracking_code'] ?? '') ?>">
                </div>
            </div>
            <label>Previsão de entrega</label>
            <input type="date" name="prazo_entrega" value="<?= View::e($o['prazo_entrega'] ?? '') ?>">
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
        </form>
    </div>
<?php endforeach; ?>
<?php if (!$orders): ?>
    <p class="hint-text">Nenhum pedido pago aguardando despacho no momento.</p>
<?php endif; ?>
