<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\PricingTier;
$sucesso = isset($_GET['sucesso']);
$requesterLabels = ['vendedor' => 'Vendedor', 'gestor' => 'Gestor', 'licenciado' => 'Licenciado'];
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
