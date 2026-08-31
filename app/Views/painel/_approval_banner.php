<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array|null $approval */
/** @var array $user */
if (!$approval) {
    return;
}
$canDecide = in_array($user['role_slug'] ?? '', ['admin', 'gerente', 'supervisor'], true);
?>
<div class="approval-banner">
    <div>
        <strong>Aguardando aprovação de desconto</strong>
        <p>Desconto pedido: <strong><?= number_format((float) $approval['requested_discount_pct'], 2, ',', '.') ?>%</strong> — acima do limite configurado pra este vendedor. Não é possível avançar até aprovar ou recusar.</p>
    </div>
    <?php if ($canDecide): ?>
        <div class="approval-banner-actions">
            <form action="/painel/aprovacoes/<?= (int) $approval['id'] ?>/decidir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="aprovado">
                <button type="submit" class="btn btn-primary">Aprovar desconto</button>
            </form>
            <form action="/painel/aprovacoes/<?= (int) $approval['id'] ?>/decidir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <input type="hidden" name="decision" value="recusado">
                <button type="submit" class="btn btn-danger">Recusar</button>
            </form>
        </div>
    <?php endif; ?>
</div>
