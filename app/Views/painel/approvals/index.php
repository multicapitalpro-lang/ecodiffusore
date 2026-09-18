<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Approval;
use App\Models\PricingTier;
$sucesso = isset($_GET['sucesso']);
$requesterLabels = ['vendedor' => 'Vendedor', 'gestor' => 'Gestor', 'licenciado' => 'Licenciado'];
$mine = $mine ?? [];
$decided = $decided ?? [];
?>
<div class="page-header">
    <h1>Liberação de Preço</h1>
</div>
<p class="section-sub">Pedidos e orçamentos com preço abaixo do padrão (R$ <?= number_format(PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?>) aguardando sua aprovação.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Decisão registrada com sucesso.</p>
<?php endif; ?>

<?php if (!$pending): ?>
    <p class="hint-text">Nenhuma liberação pendente pra você decidir no momento.</p>
<?php endif; ?>

<?php foreach ($pending as $a): ?>
    <div class="buy-checkout-box" style="max-width:none;margin-bottom:18px;">
        <div class="form-grid-2">
            <div>
                <p><strong>Tipo:</strong> <?= $a['approvable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $a['approvable_id'] ?></p>
                <p><strong>Solicitado por:</strong> <?= View::e($requesterLabels[$a['requester_role']] ?? ($a['requester_role'] ?? '—')) ?> — <?= View::e($a['seller_name']) ?></p>
                <p><strong>Cliente:</strong> <?= View::e($a['client_name']) ?></p>
                <?php if (!empty($a['level1_approved_by'])): ?>
                    <p class="hint-text" style="margin:2px 0 0;">Já aprovado pelo Gestor/Licenciado — falta a liberação final.</p>
                <?php endif; ?>
            </div>
            <div>
                <p><strong>Preço padrão:</strong> R$ <?= number_format(PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?></p>
                <p><strong>Preço solicitado:</strong> <span style="color:#c53030;font-weight:700;">R$ <?= number_format((float) $a['requested_price'], 2, ',', '.') ?></span></p>
                <p><strong>Pedido em:</strong> <?= View::e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></p>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
            <a href="<?= View::e($a['url']) ?>" class="btn btn-outline">Ver <?= $a['approvable_type'] === 'order' ? 'pedido' : 'orçamento' ?></a>
            <form action="/painel/aprovacoes/<?= (int) $a['id'] ?>/decidir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="aprovado">
                <button type="submit" class="btn btn-primary">Aprovar preço</button>
            </form>
            <form action="/painel/aprovacoes/<?= (int) $a['id'] ?>/decidir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="recusado">
                <button type="submit" class="btn btn-danger">Recusar</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="page-header" style="margin-top:32px;">
    <h2>Minhas solicitações</h2>
</div>
<p class="section-sub">Liberações de preço que você pediu — acompanhe o status em tempo real, mesmo depois de decidido.</p>

<?php if (!$mine): ?>
    <p class="hint-text">Você ainda não pediu nenhuma liberação de preço.</p>
<?php endif; ?>

<?php foreach ($mine as $a):
    $label = Approval::statusLabel($a);
    $badgeColor = $a['status'] === 'aprovado' ? '#2f855a' : ($a['status'] === 'recusado' ? '#c53030' : '#b7791f');
?>
    <div class="buy-checkout-box" style="max-width:none;margin-bottom:14px;">
        <div class="form-grid-2">
            <div>
                <p><strong>Tipo:</strong> <?= $a['approvable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $a['approvable_id'] ?></p>
                <p><strong>Cliente:</strong> <?= View::e($a['client_name']) ?></p>
                <p><strong>Pedido em:</strong> <?= View::e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></p>
            </div>
            <div>
                <p><strong>Preço solicitado:</strong> R$ <?= number_format((float) $a['requested_price'], 2, ',', '.') ?></p>
                <p><strong>Status:</strong> <span style="color:<?= $badgeColor ?>;font-weight:700;"><?= View::e($label) ?></span></p>
            </div>
        </div>
        <div style="margin-top:10px;">
            <a href="<?= View::e($a['url']) ?>" class="btn btn-outline">Ver <?= $a['approvable_type'] === 'order' ? 'pedido' : 'orçamento' ?></a>
        </div>
    </div>
<?php endforeach; ?>

<div class="page-header" style="margin-top:32px;">
    <h2>Decisões que você tomou</h2>
</div>
<p class="section-sub">Liberações de preço em que você decidiu algo (primeira etapa ou etapa final) — acompanhe se já foi concluída ou ainda depende de mais alguém.</p>

<?php if (!$decided): ?>
    <p class="hint-text">Você ainda não decidiu nenhuma liberação de preço.</p>
<?php endif; ?>

<?php foreach ($decided as $a):
    $label = Approval::statusLabel($a);
    $badgeColor = $a['status'] === 'aprovado' ? '#2f855a' : ($a['status'] === 'recusado' ? '#c53030' : '#b7791f');
    $suaParte = (int) ($a['level1_approved_by'] ?? 0) === (int) $user['id'] && $a['status'] === 'pendente'
        ? 'Você aprovou o nível 1 — agora falta a decisão final de outra pessoa.'
        : null;
?>
    <div class="buy-checkout-box" style="max-width:none;margin-bottom:14px;">
        <div class="form-grid-2">
            <div>
                <p><strong>Tipo:</strong> <?= $a['approvable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $a['approvable_id'] ?></p>
                <p><strong>Vendedor:</strong> <?= View::e($a['seller_name']) ?></p>
                <p><strong>Cliente:</strong> <?= View::e($a['client_name']) ?></p>
                <?php if ($suaParte): ?>
                    <p class="hint-text" style="margin:2px 0 0;"><?= View::e($suaParte) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p><strong>Preço solicitado:</strong> R$ <?= number_format((float) $a['requested_price'], 2, ',', '.') ?></p>
                <p><strong>Status atual:</strong> <span style="color:<?= $badgeColor ?>;font-weight:700;"><?= View::e($label) ?></span></p>
            </div>
        </div>
        <div style="margin-top:10px;">
            <a href="<?= View::e($a['url']) ?>" class="btn btn-outline">Ver <?= $a['approvable_type'] === 'order' ? 'pedido' : 'orçamento' ?></a>
        </div>
    </div>
<?php endforeach; ?>
