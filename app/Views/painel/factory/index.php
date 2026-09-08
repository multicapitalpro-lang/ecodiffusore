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

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Produto</th>
                <th>Destinatário</th>
                <th>Endereço</th>
                <th>Transportadora</th>
                <th>Código</th>
                <th>Previsão</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <?php $formId = 'entrega-' . (int) $o['id']; ?>
                <tr>
                    <td>#<?= (int) $o['id'] ?><br><small class="hint-text"><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></small></td>
                    <td><?= View::e($o['produtos'] ?: '—') ?></td>
                    <td>
                        <?= View::e($o['client_name']) ?><br>
                        <small class="hint-text"><?= View::e($o['client_document'] ?: '—') ?><?php if (!empty($o['client_whatsapp'])): ?> · <?= View::e($o['client_whatsapp']) ?><?php endif; ?></small>
                    </td>
                    <td style="min-width:220px">
                        <?= View::e($o['street'] ?: '—') ?><?= $o['number'] ? ', ' . View::e($o['number']) : '' ?><?= $o['complement'] ? ' - ' . View::e($o['complement']) : '' ?><br>
                        <small class="hint-text"><?= View::e($o['neighborhood'] ?: '—') ?>, <?= View::e($o['city'] ?: '—') ?>/<?= View::e($o['state'] ?: '—') ?> · CEP <?= View::e($o['zip_code'] ?: '—') ?></small>
                    </td>
                    <td><input form="<?= $formId ?>" type="text" name="tracking_carrier" value="<?= View::e($o['tracking_carrier'] ?? '') ?>" placeholder="Correios, Jadlog..." style="width:120px"></td>
                    <td><input form="<?= $formId ?>" type="text" name="tracking_code" value="<?= View::e($o['tracking_code'] ?? '') ?>" style="width:120px"></td>
                    <td><input form="<?= $formId ?>" type="date" name="prazo_entrega" value="<?= View::e($o['prazo_entrega'] ?? '') ?>" style="width:140px"></td>
                    <td><button form="<?= $formId ?>" type="submit" class="btn btn-outline">Salvar</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="8">Nenhum pedido pago aguardando despacho no momento.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($orders as $o): ?>
    <form id="entrega-<?= (int) $o['id'] ?>" method="post" action="/painel/fabrica/<?= (int) $o['id'] ?>/entrega" hidden><?= Csrf::field() ?></form>
<?php endforeach; ?>
