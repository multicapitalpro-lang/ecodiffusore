<?php
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\View;
$statusLabels = [
    'em_andamento' => 'Em andamento',
    'atendido' => 'Atendido',
    'verificado' => 'Verificado',
    'cancelado' => 'Cancelado',
];
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
$isViewOnly = in_array($user['role_slug'] ?? '', Roles::NATIONAL_SUPPORT, true);
$isModal = $isModal ?? false;
$situation = $order['payment_situation'] ?? ['label' => '—', 'badge' => 'novo'];
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Pedido #<?= (int) $order['id'] ?></h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <div class="page-header">
        <h1>Pedido #<?= (int) $order['id'] ?></h1>
        <?php if ($order['status'] === 'em_andamento' && !$isViewOnly): ?>
            <a href="/painel/pedidos/<?= (int) $order['id'] ?>/editar" class="btn btn-outline">Editar</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['acesso_criado'])): ?>
    <p class="form-msg form-msg-ok">Pedido registrado! Conta do portal criada automaticamente pro cliente — já mandamos as credenciais por e-mail/WhatsApp. Senha temporária (caso precise repassar): <strong><?= View::e($_GET['temp'] ?? '') ?></strong></p>
<?php elseif ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Só é possível editar pedidos em andamento.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($order['client_name']) ?></p>
    <?php if (!empty($order['client_whatsapp'])): ?>
        <p><strong>Telefone:</strong> <a href="https://wa.me/55<?= preg_replace('/\D/', '', $order['client_whatsapp']) ?>" target="_blank" rel="noopener">💬 <?= View::e($order['client_whatsapp']) ?></a></p>
    <?php endif; ?>
    <p><strong>Cidade:</strong> <?= View::e($order['client_city'] ?: '—') ?><?= $order['client_state'] ? '/' . View::e($order['client_state']) : '' ?></p>
    <p><strong>Vendedor:</strong> <?= View::e($order['seller_name'] ?: 'Sem vendedor') ?></p>
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y', strtotime($order['order_date']))) ?></p>
    <p><strong>Situação do pedido:</strong> <span class="status-badge status-<?= $order['status'] === 'verificado' ? 'active' : ($order['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$order['status']] ?? $order['status'] ?></span></p>
    <p><strong>Situação do pagamento:</strong> <span class="status-badge status-<?= View::e($situation['badge']) ?>"><?= View::e($situation['label']) ?></span></p>
    <?php if (!empty($order['vehicle_type']) || !empty($order['vehicle_plate'])): ?>
        <p><strong>Veículo:</strong> <?= View::e($order['vehicle_type'] ?: '—') ?><?= $order['vehicle_plate'] ? ' · Placa ' . View::e($order['vehicle_plate']) : '' ?></p>
    <?php endif; ?>
    <?php if (!empty($order['vehicle_document_path'])): ?>
        <p><a href="/painel/pedidos/<?= (int) $order['id'] ?>/documento-veiculo" target="_blank" rel="noopener" class="link-small">📄 Ver documento do veículo</a></p>
    <?php endif; ?>
    <?php if ($order['notes']): ?><p><strong>Obs.:</strong> <?= nl2br(View::e($order['notes'])) ?></p><?php endif; ?>
</div>

<?php if (!$isViewOnly): ?>
<form action="/painel/pedidos/<?= (int) $order['id'] ?>/rastreio" method="post" class="inline-form" style="margin-bottom:16px">
    <?= Csrf::field() ?>
    <label>Transportadora <input type="text" name="tracking_carrier" value="<?= View::e($order['tracking_carrier'] ?? '') ?>" placeholder="Ex: Correios, Jadlog"></label>
    <label>Código de rastreio <input type="text" name="tracking_code" value="<?= View::e($order['tracking_code'] ?? '') ?>"></label>
    <label>Previsão de entrega <input type="date" name="prazo_entrega" value="<?= View::e($order['prazo_entrega'] ?? '') ?>"></label>
    <button type="submit" class="btn btn-outline">Salvar rastreio</button>
</form>
<?php endif; ?>

<?php include __DIR__ . '/../_approval_banner.php'; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= View::e($item['product_name']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td>R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td><strong>R$ <?= number_format((float) $order['total_value'], 2, ',', '.') ?></strong></td></tr>
        </tfoot>
    </table>
</div>

<?php
$allowGenerateCharge = $order['status'] !== 'cancelado' && $order['status'] !== 'verificado' && !$isViewOnly;
if ($payments || $allowGenerateCharge):
    $chargeAction = "/painel/pedidos/{$order['id']}/cobranca";
    $basePrice = (float) $order['total_value'];
    include __DIR__ . '/../_payments_section.php';
endif;
?>

<?php if ($payments && $situation['slug'] === 'pago' && !$isViewOnly): ?>
    <form action="/painel/pedidos/<?= (int) $order['id'] ?>/reembolsar" method="post" class="inline-form" onsubmit="return confirm('Marcar o pagamento deste pedido como reembolsado?');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-outline">Marcar pagamento como Reembolsado</button>
    </form>
<?php endif; ?>

<?php if ($order['status'] !== 'cancelado' && $order['status'] !== 'verificado' && !$isViewOnly): ?>
<div class="order-actions">
    <?php if ($order['status'] === 'em_andamento'): ?>
        <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="status" value="atendido">
            <button type="submit" class="btn btn-outline">Marcar como Atendido</button>
        </form>
    <?php endif; ?>
    <?php if (!$approval): ?>
        <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="status" value="verificado">
            <button type="submit" class="btn btn-primary">Marcar como Verificado</button>
        </form>
    <?php endif; ?>
    <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="cancelado">
        <button type="submit" class="btn btn-danger">Cancelar Pedido</button>
    </form>
</div>
<p class="hint-text">"Verificado" confirma o recebimento: gera a comissão do vendedor e o lançamento em Contas a Receber automaticamente.</p>
<?php endif; ?>
<?php if ($isModal): ?></div><?php endif; ?>
