<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago'];
$roleLabels = ['licenciado' => 'Licenciado', 'gestor' => 'Gestor', 'vendedor' => 'Vendedor', 'gerente' => 'Gerente', 'supervisor' => 'Supervisor', 'influenciador' => 'Influenciador'];
$sucesso = isset($_GET['sucesso']);
$period = $period ?? [];
$showDirection = in_array($user['role_slug'], ['licenciado', 'gestor', 'vendedor'], true);
$myId = (int) $user['id'];

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');
$yearStart = date('Y-01-01');
$presets = [
    'Hoje' => ['from' => $today, 'to' => $today],
    'Esta semana' => ['from' => $weekStart, 'to' => $today],
    'Este mês' => ['from' => $monthStart, 'to' => $today],
    'Este ano' => ['from' => $yearStart, 'to' => $today],
];
?>
<div class="page-header">
    <h1>Comissões</h1>
    <a href="/painel/financeiro/comissoes/exportar?<?= http_build_query($period) ?>" class="btn btn-outline">Exportar CSV</a>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($period['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= View::e($period['to'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php foreach ($presets as $label => $range): ?>
        <a class="link-small" href="?from=<?= $range['from'] ?>&to=<?= $range['to'] ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <?php if ($period): ?><a class="link-small" href="/painel/financeiro/comissoes">Tudo</a><?php endif; ?>
</form>

<div class="cards-grid">
    <div class="dash-card">
        <span>Quantidade</span>
        <strong><?= $summary['count'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Total gerado</span>
        <strong>R$ <?= number_format($summary['total'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Pago</span>
        <strong>R$ <?= number_format($summary['pago'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Pendente</span>
        <strong>R$ <?= number_format($summary['pendente'], 2, ',', '.') ?></strong>
    </div>
</div>

<?php if (in_array($user['role_slug'], ['licenciado', 'gestor', 'vendedor'], true)): ?>
    <?php
    $aReceber = array_filter($commissions, fn ($c) => (int) $c['beneficiary_id'] === $myId);
    $aPagar = array_filter($commissions, fn ($c) => (int) $c['beneficiary_id'] !== $myId);
    $sumAmount = function (array $rows, ?string $status = null): float {
        return array_sum(array_map(fn ($c) => (float) $c['amount'], array_filter($rows, fn ($c) => $status === null || $c['status'] === $status)));
    };
    ?>
    <h3 class="section-title" style="margin-top:0;">Contas a Pagar e a Receber (comissão)</h3>
    <div class="cards-grid" style="margin-bottom:4px;">
        <div class="dash-card" style="border-top-color:#1a7a4c;">
            <span>💰 A receber — sua comissão</span>
            <strong>R$ <?= number_format($sumAmount($aReceber), 2, ',', '.') ?></strong>
            <span class="hint-inline">Pendente: R$ <?= number_format($sumAmount($aReceber, 'pendente'), 2, ',', '.') ?></span>
        </div>
        <?php if ($aPagar): ?>
            <div class="dash-card dash-card-warning">
                <span>💸 A pagar — sua equipe</span>
                <strong>R$ <?= number_format($sumAmount($aPagar), 2, ',', '.') ?></strong>
                <span class="hint-inline">Pendente: R$ <?= number_format($sumAmount($aPagar, 'pendente'), 2, ',', '.') ?></span>
            </div>
        <?php endif; ?>
    </div>
    <p class="hint-text" style="margin-bottom:20px;">"A receber" é sua comissão, paga pela Ecodiffusore. <?= $aPagar ? '"A pagar" é a comissão do seu Gestor/Vendedor, que sai do seu pool.' : '' ?> Já refletido nos lançamentos abaixo.</p>
<?php endif; ?>

<?php if (!empty($pricingTiersRef)): ?>
    <h3 class="section-title">Faixas de preço negociável</h3>
    <p class="hint-text" style="margin-top:0;">Quanto sua equipe negociar por unidade define sua comissão nessa venda.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Faixa de preço</th><th>Sua comissão</th></tr></thead>
            <tbody>
                <?php foreach ($pricingTiersRef as $t): ?>
                    <tr>
                        <td>R$ <?= number_format((float) $t['min_price'], 2, ',', '.') ?><?= $t['max_price'] !== null ? ' a R$ ' . number_format((float) $t['max_price'], 2, ',', '.') : ' acima' ?></td>
                        <td><?= number_format((float) $t['licenciado_commission_pct'], 2, ',', '.') ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (!empty($vendorOwnTiers)): ?>
    <h3 class="section-title">Sua comissão por faixa de preço</h3>
    <p class="hint-text" style="margin-top:0;">Quanto você negociar por unidade define sua comissão nessa venda — combinado com seu Licenciado.</p>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Faixa de preço</th><th>Sua comissão</th></tr></thead>
            <tbody>
                <?php foreach ($vendorOwnTiers as $t): ?>
                    <tr>
                        <td>R$ <?= number_format((float) $t['min_price'], 2, ',', '.') ?><?= $t['max_price'] !== null ? ' a R$ ' . number_format((float) $t['max_price'], 2, ',', '.') : ' acima' ?></td>
                        <td>
                            <?php if ($t['value'] === null): ?>
                                <span class="hint-text">Consulte seu Licenciado</span>
                            <?php elseif ($vendorCommissionType === 'fixo'): ?>
                                R$ <?= number_format((float) $t['value'], 2, ',', '.') ?> por unidade
                            <?php else: ?>
                                <?= number_format((float) $t['value'], 2, ',', '.') ?>% da venda
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (count($byRole) > 1): ?>
<h3 class="section-title">Resumo por papel</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Papel</th><th>Qtd.</th><th>Total gerado</th><th>Pago</th><th>Pendente</th></tr></thead>
        <tbody>
            <?php foreach ($byRole as $r): ?>
                <tr>
                    <td><?= View::e($roleLabels[$r['role_slug']] ?? $r['role_slug']) ?></td>
                    <td><?= (int) $r['count_total'] ?></td>
                    <td>R$ <?= number_format((float) $r['total'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $r['total_pago'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $r['total_pendente'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (count($bySeller) > 1 || !$canManageAny): ?>
<h3 class="section-title">Resumo por beneficiário</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Beneficiário</th><th>Papel</th><th>Qtd.</th><th>Total gerado</th><th>Pago</th><th>Pendente</th></tr></thead>
        <tbody>
            <?php foreach ($bySeller as $s): ?>
                <tr>
                    <td><?= View::e($s['name']) ?></td>
                    <td><?= View::e($roleLabels[$s['role_slug']] ?? $s['role_slug']) ?></td>
                    <td><?= (int) $s['count_total'] ?></td>
                    <td>R$ <?= number_format((float) $s['total'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $s['total_pago'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $s['total_pendente'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h3 class="section-title">Lançamentos</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><?php if ($showDirection): ?><th></th><?php endif; ?><th>Pedido</th><th>Beneficiário</th><th>Papel</th><th>Cliente</th><th>Data</th><th>%</th><th>Comissão</th><th>Situação</th><?php if ($canManageAny): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
            <?php foreach ($commissions as $c): ?>
                <tr>
                    <?php if ($showDirection): ?>
                        <td><?= (int) $c['beneficiary_id'] === $myId ? '<span class="hint-inline" style="color:#1a7a4c;">💰 Receber</span>' : '<span class="hint-inline" style="color:#b3790f;">💸 Pagar</span>' ?></td>
                    <?php endif; ?>
                    <td>#<?= (int) $c['order_id'] ?></td>
                    <td>
                        <?= View::e($c['beneficiary_name']) ?>
                        <?php if ((int) $c['beneficiary_id'] !== (int) $c['seller_id']): ?>
                            <br><small class="hint-text">pedido de <?= View::e($c['seller_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= View::e($roleLabels[$c['role_slug']] ?? $c['role_slug']) ?></td>
                    <td><?= View::e($c['client_name']) ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($c['order_date']))) ?></td>
                    <td><?= number_format((float) $c['percentage'], 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format((float) $c['amount'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $c['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$c['status']] ?? $c['status'] ?></span></td>
                    <?php if ($canManageAny): ?>
                        <td>
                            <?php if ($c['status'] === 'pendente' && $c['can_manage']): ?>
                                <form action="/painel/financeiro/comissoes/<?= (int) $c['id'] ?>/baixar" method="post" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button">Dar baixa</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$commissions): ?>
                <tr><td colspan="<?= ($canManageAny ? 9 : 8) + ($showDirection ? 1 : 0) ?>">Nenhuma comissão gerada nesse período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
