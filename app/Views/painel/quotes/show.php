<?php
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\View;
$statusLabels = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'convertido' => 'Convertido'];
$sucesso = isset($_GET['sucesso']);
$isViewOnly = in_array($user['role_slug'] ?? '', Roles::NATIONAL_SUPPORT, true);
$isModal = $isModal ?? false;
$situation = $quote['payment_situation'] ?? ['label' => '—', 'badge' => 'novo'];
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Orçamento #<?= (int) $quote['id'] ?></h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <div class="page-header">
        <h1>Orçamento #<?= (int) $quote['id'] ?></h1>
        <?php if ($quote['status'] === 'aberto' && !$isViewOnly): ?>
            <a href="/painel/orcamentos/<?= (int) $quote['id'] ?>/editar" class="btn btn-outline">Editar</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($quote['client_name']) ?></p>
    <?php $phone = $quote['client_whatsapp'] ?? $quote['lead_whatsapp'] ?? null; ?>
    <?php if ($phone): ?>
        <p><strong>Telefone:</strong> <a href="https://wa.me/55<?= preg_replace('/\D/', '', $phone) ?>" target="_blank" rel="noopener">💬 <?= View::e($phone) ?></a></p>
    <?php endif; ?>
    <p><strong>Cidade:</strong> <?= View::e($quote['client_city'] ?? $quote['lead_city'] ?? '—') ?></p>
    <p><strong>Vendedor:</strong> <?= View::e($quote['seller_name'] ?: 'Sem vendedor') ?></p>
    <p><strong>Situação do pagamento:</strong> <span class="status-badge status-<?= View::e($situation['badge']) ?>"><?= View::e($situation['label']) ?></span></p>
    <p><strong>Gerado em:</strong> <?= View::e(date('d/m/Y', strtotime($quote['created_at']))) ?> às <?= View::e(date('H:i', strtotime($quote['created_at']))) ?></p>
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y', strtotime($quote['quote_date']))) ?></p>
    <p><strong>Válido até:</strong> <?= $quote['valid_until'] ? View::e(date('d/m/Y', strtotime($quote['valid_until']))) : '—' ?></p>
    <p><strong>Situação:</strong> <span class="status-badge status-<?= $quote['status'] === 'convertido' || $quote['status'] === 'aprovado' ? 'active' : ($quote['status'] === 'recusado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$quote['status']] ?? $quote['status'] ?></span></p>
    <?php if ($quote['converted_order_id']): ?>
        <p><strong>Convertido em:</strong> <a href="/painel/pedidos/<?= (int) $quote['converted_order_id'] ?>">Pedido #<?= (int) $quote['converted_order_id'] ?></a></p>
    <?php endif; ?>
    <?php if ($quote['notes']): ?><p><strong>Obs.:</strong> <?= nl2br(View::e($quote['notes'])) ?></p><?php endif; ?>
</div>

<?php if (!empty($quote['lead_id'])): ?>
<div class="order-summary">
    <h3 style="margin-top:0;"><?= $quote['lead_source'] === 'proposta_facil' ? '⚡ Origem: Proposta Fácil (painel)' : '🌐 Origem: orçamento gerado pelo site' ?></h3>
    <p><strong>Cidade informada:</strong> <?= View::e($quote['lead_city'] ?: '—') ?></p>
    <p><strong>WhatsApp informado:</strong> <?= View::e($quote['lead_whatsapp'] ?: '—') ?></p>
    <?php if ($quote['vehicle_plate']): ?>
        <p><strong>Veículo:</strong>
            <?= View::e($quote['vehicle_brand'] ?: '—') ?>
            <?= $quote['vehicle_model'] ? '· ' . View::e($quote['vehicle_model']) : '' ?>
            · <?= View::e($quote['vehicle_year'] ?: '—') ?>
            · placa <?= View::e($quote['vehicle_plate']) ?>
            <?= $quote['vehicle_power'] ? '· ' . View::e($quote['vehicle_power']) : '' ?>
        </p>
        <p><strong>Motor:</strong> <?= $quote['vehicle_ecu_status'] === 'original' ? 'Original de fábrica' : 'Reprogramado (chip)' ?>
            <?= $quote['vehicle_reprogrammed_power'] ? ' — reprogramado pra ' . View::e($quote['vehicle_reprogrammed_power']) : '' ?>
        </p>
        <p><strong>Sistema de ARLA:</strong> <?= $quote['vehicle_has_arla'] === 'sim' ? 'Sim' : ($quote['vehicle_has_arla'] === 'nao' ? 'Não' : '—') ?></p>
    <?php endif; ?>
    <a href="/painel/leads" class="link-small">Ver no Kanban de Leads →</a>
</div>
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
            <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td><strong>R$ <?= number_format((float) $quote['total_value'], 2, ',', '.') ?></strong></td></tr>
        </tfoot>
    </table>
</div>

<?php
$allowGenerateCharge = in_array($quote['status'], ['aberto', 'aprovado'], true) && !$isViewOnly;
if ($payments || $allowGenerateCharge):
    $chargeAction = "/painel/orcamentos/{$quote['id']}/cobranca";
    $basePrice = (float) $quote['total_value'];
    include __DIR__ . '/../_payments_section.php';
endif;
?>

<?php if ($isViewOnly): ?>
<?php elseif ($quote['status'] === 'aberto'): ?>
<div class="order-actions">
    <form action="/painel/orcamentos/<?= (int) $quote['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="aprovado">
        <button type="submit" class="btn btn-primary">Marcar como Aprovado</button>
    </form>
    <form action="/painel/orcamentos/<?= (int) $quote['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="recusado">
        <button type="submit" class="btn btn-danger">Marcar como Recusado</button>
    </form>
</div>
<?php elseif ($quote['status'] === 'aprovado' && !$approval): ?>
<div class="order-actions">
    <form action="/painel/orcamentos/<?= (int) $quote['id'] ?>/converter" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary">Converter em Pedido</button>
    </form>
</div>
<p class="hint-text">Isso cria um Pedido de Venda novo com os mesmos itens deste orçamento.</p>
<?php endif; ?>
<?php if ($isModal): ?></div><?php endif; ?>
