<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Approval;
use App\Models\PricingTier;
/** @var array|null $approval */
/** @var array $user */
if (!$approval) {
    return;
}
$canDecide = Approval::canDecide($approval, $user);
$requesterRole = $approval['requester_role'] ?? null;
$requesterLabels = ['vendedor' => 'Vendedor', 'gestor' => 'Gestor', 'licenciado' => 'Licenciado'];
?>
<div class="approval-banner">
    <div>
        <strong>Aguardando aprovação de preço</strong>
        <?php if (!empty($approval['requested_price'])): ?>
            <p>
                Preço solicitado: <strong>R$ <?= number_format((float) $approval['requested_price'], 2, ',', '.') ?></strong>
                — abaixo do padrão de R$ <?= number_format(PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?>
                <?= $requesterRole ? ' pedido pelo(a) ' . View::e($requesterLabels[$requesterRole] ?? $requesterRole) : '' ?>.
                Não é possível avançar até aprovar ou recusar.
            </p>
        <?php else: ?>
            <p>Desconto pedido: <strong><?= number_format((float) $approval['requested_discount_pct'], 2, ',', '.') ?>%</strong> — acima do limite configurado. Não é possível avançar até aprovar ou recusar.</p>
        <?php endif; ?>
    </div>
    <?php if ($canDecide): ?>
        <div class="approval-banner-actions">
            <form action="/painel/aprovacoes/<?= (int) $approval['id'] ?>/decidir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="aprovado">
                <button type="submit" class="btn btn-primary">Aprovar preço</button>
            </form>
            <form action="/painel/aprovacoes/<?= (int) $approval['id'] ?>/decidir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="recusado">
                <button type="submit" class="btn btn-danger">Recusar</button>
            </form>
        </div>
    <?php endif; ?>
</div>
