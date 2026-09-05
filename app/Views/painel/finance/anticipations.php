<?php
use App\Core\Csrf;
use App\Core\View;

$statusLabels = [
    'PENDING' => ['Solicitada', 'novo'],
    'SCHEDULED' => ['Agendada', 'active'],
    'DONE' => ['Concluída', 'active'],
    'CREDITED' => ['Creditada', 'active'],
    'DECLINED' => ['Recusada', 'inactive'],
    'DENIED' => ['Negada', 'inactive'],
    'CANCELLED' => ['Cancelada', 'inactive'],
];

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

$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Antecipações (Asaas)</h1>
    <form method="post" action="/painel/financeiro/antecipacoes/sincronizar" class="inline-form">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary">🔄 Atualizar do Asaas</button>
    </form>
</div>
<p class="hint-text" style="margin-top:0;">Espelho local das antecipações reais da conta Asaas — clique em "Atualizar do Asaas" pra buscar o que há de novo (não busca automaticamente a cada carregamento de página).</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Sincronizado com sucesso<?= isset($_GET['importadas']) ? ' — ' . (int) $_GET['importadas'] . ' antecipação(ões) no total' : '' ?>.</p>
<?php elseif ($erro === 'asaas'): ?>
    <p class="form-msg form-msg-erro">Não foi possível conectar com a Asaas agora. Tente de novo em instantes.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir a ação.</p>
<?php endif; ?>

<?php if ($limits): ?>
<div class="cards-grid">
    <div class="dash-card">
        <span>Disponível pra antecipar (cartão)</span>
        <strong>R$ <?= number_format((float) ($limits['creditCard']['available'] ?? 0), 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Limite total (cartão)</span>
        <strong>R$ <?= number_format((float) ($limits['creditCard']['total'] ?? 0), 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Disponível pra antecipar (boleto)</span>
        <strong>R$ <?= number_format((float) ($limits['bankSlip']['available'] ?? 0), 2, ',', '.') ?></strong>
    </div>
</div>
<p class="hint-text">Limites atualizados em <?= $limitsUpdatedAt ? View::e(date('d/m/Y H:i', strtotime($limitsUpdatedAt))) : '—' ?>.</p>
<?php else: ?>
    <p class="form-msg form-msg-erro">Ainda não sincronizado — clique em "Atualizar do Asaas" pra buscar os dados pela primeira vez.</p>
<?php endif; ?>

<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($from) ?>">
    <input type="date" name="to" value="<?= View::e($to) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php foreach ($presets as $label => $range): ?>
        <a class="link-small" href="?from=<?= $range['from'] ?>&to=<?= $range['to'] ?>"><?= $label ?></a>
    <?php endforeach; ?>
</form>

<div class="cards-grid">
    <div class="dash-card">
        <span>Valor bruto antecipado</span>
        <strong>R$ <?= number_format($totals['value_effective'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Valor líquido recebido</span>
        <strong>R$ <?= number_format($totals['net_value_effective'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card dash-card-danger">
        <span>Taxa paga na antecipação</span>
        <strong>R$ <?= number_format($totals['fee_effective'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Antecipações efetivadas</span>
        <strong><?= (int) $totals['count_effective'] ?></strong>
    </div>
</div>

<h3 class="section-title">Antecipações no período</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>Solicitada em</th><th>Cliente</th><th>Situação</th><th>Valor</th><th>Taxa</th><th>Líquido</th><th>Dias antecipados</th><th>Vencimento original</th></tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php $status = $statusLabels[strtoupper($r['status'])] ?? [$r['status'], 'novo']; ?>
                <tr>
                    <td><?= $r['request_date'] ? View::e(date('d/m/Y', strtotime($r['request_date']))) : '—' ?></td>
                    <td>
                        <?= $r['client_name'] ? View::e($r['client_name']) : '—' ?>
                        <?php if ($r['payable_type'] && $r['payable_id']): ?>
                            <br><small class="hint-text"><?= $r['payable_type'] === 'order' ? 'Pedido' : 'Orçamento' ?> #<?= (int) $r['payable_id'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge status-<?= $status[1] ?>"><?= View::e($status[0]) ?></span></td>
                    <td>R$ <?= number_format((float) $r['value'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $r['fee'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $r['net_value'], 2, ',', '.') ?></td>
                    <td><?= $r['anticipation_days'] !== null ? (int) $r['anticipation_days'] . ' dias' : '—' ?></td>
                    <td><?= $r['due_date'] ? View::e(date('d/m/Y', strtotime($r['due_date']))) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8">Nenhuma antecipação nesse período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
