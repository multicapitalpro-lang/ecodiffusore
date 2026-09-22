<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Approval;
use App\Models\PricingTier;
$sucesso = isset($_GET['sucesso']);
$requesterLabels = ['vendedor' => 'Vendedor', 'gestor' => 'Gestor', 'licenciado' => 'Licenciado'];
$mine = $mine ?? [];
$decided = $decided ?? [];

$statusBadgeClass = function (array $a): string {
    if ($a['status'] === 'aprovado') {
        return 'status-active';
    }
    if ($a['status'] === 'recusado') {
        return 'status-recusado';
    }
    return 'status-aguardando-assinatura';
};
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
<?php else: ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Solicitado por</th>
                    <th>Cliente</th>
                    <th>Motivo do vendedor</th>
                    <th>Preço padrão</th>
                    <th>Preço solicitado</th>
                    <th>Pedido em</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $a): ?>
                    <tr>
                        <td><?= $a['approvable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $a['approvable_id'] ?></td>
                        <td>
                            <?= View::e($requesterLabels[$a['requester_role']] ?? ($a['requester_role'] ?? '—')) ?> — <?= View::e($a['seller_name']) ?>
                            <?php if (!empty($a['level1_approved_by'])): ?>
                                <br><small class="hint-text">Já aprovado pelo Gestor/Licenciado — falta a liberação final.</small>
                            <?php endif; ?>
                        </td>
                        <td><?= View::e($a['client_name']) ?></td>
                        <td style="max-width:220px;"><?= !empty($a['justification']) ? nl2br(View::e($a['justification'])) : '<span class="hint-text">— não informado —</span>' ?></td>
                        <td>R$ <?= number_format(PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?></td>
                        <td><strong style="color:#c53030;">R$ <?= number_format((float) $a['requested_price'], 2, ',', '.') ?></strong></td>
                        <td><?= View::e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                        <td>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="<?= View::e($a['url']) ?>" class="link-button">Ver</a>
                                <form action="/painel/aprovacoes/<?= (int) $a['id'] ?>/decidir" method="post" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="decision" value="aprovado">
                                    <button type="submit" class="btn btn-primary" style="padding:4px 10px;font-size:.8rem;">Aprovar</button>
                                </form>
                                <form action="/painel/aprovacoes/<?= (int) $a['id'] ?>/decidir" method="post" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="decision" value="recusado">
                                    <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:.8rem;">Recusar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="page-header" style="margin-top:32px;">
    <h2>Minhas solicitações</h2>
</div>
<p class="section-sub">Liberações de preço que você pediu — acompanhe o status em tempo real, mesmo depois de decidido.</p>

<?php if (!$mine): ?>
    <p class="hint-text">Você ainda não pediu nenhuma liberação de preço.</p>
<?php else: ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Cliente</th>
                    <th>Preço solicitado</th>
                    <th>Status</th>
                    <th>Pedido em</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mine as $a): ?>
                    <tr>
                        <td><?= $a['approvable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $a['approvable_id'] ?></td>
                        <td><?= View::e($a['client_name']) ?></td>
                        <td>R$ <?= number_format((float) $a['requested_price'], 2, ',', '.') ?></td>
                        <td><span class="status-badge <?= $statusBadgeClass($a) ?>"><?= View::e(Approval::statusLabel($a)) ?></span></td>
                        <td><?= View::e(date('d/m/Y H:i', strtotime($a['created_at']))) ?></td>
                        <td><a href="<?= View::e($a['url']) ?>" class="link-button">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="page-header" style="margin-top:32px;">
    <h2>Decisões que você tomou</h2>
</div>
<p class="section-sub">Liberações de preço em que você decidiu algo (primeira etapa ou etapa final) — acompanhe se já foi concluída ou ainda depende de mais alguém.</p>

<?php if (!$decided): ?>
    <p class="hint-text">Você ainda não decidiu nenhuma liberação de preço.</p>
<?php else: ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Vendedor</th>
                    <th>Cliente</th>
                    <th>Preço solicitado</th>
                    <th>Status atual</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($decided as $a):
                    $suaParte = (int) ($a['level1_approved_by'] ?? 0) === (int) $user['id'] && $a['status'] === 'pendente'
                        ? 'Você aprovou o nível 1 — falta a decisão final de outra pessoa.'
                        : null;
                ?>
                    <tr>
                        <td><?= $a['approvable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $a['approvable_id'] ?></td>
                        <td><?= View::e($a['seller_name']) ?></td>
                        <td><?= View::e($a['client_name']) ?></td>
                        <td>R$ <?= number_format((float) $a['requested_price'], 2, ',', '.') ?></td>
                        <td>
                            <span class="status-badge <?= $statusBadgeClass($a) ?>"><?= View::e(Approval::statusLabel($a)) ?></span>
                            <?php if ($suaParte): ?>
                                <br><small class="hint-text"><?= View::e($suaParte) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?= View::e($a['url']) ?>" class="link-button">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
