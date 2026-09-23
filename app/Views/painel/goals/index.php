<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Goal;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$roleLabels = ['admin' => 'Admin', 'gerente' => 'Gerente', 'supervisor' => 'Supervisor', 'licenciado' => 'Licenciado', 'gestor' => 'Gestor', 'vendedor' => 'Vendedor'];
$myId = (int) $user['id'];
// Fase 82: meta por valor (R$) ou por quantidade (equipamentos/placas vendidas).
$fmtGoal = function (float $value, string $metricType): string {
    return $metricType === 'quantidade'
        ? number_format($value, 0, ',', '.') . ' un.'
        : 'R$ ' . number_format($value, 2, ',', '.');
};
?>
<div class="page-header">
    <h1>Metas</h1>
    <?php if ($targets): ?>
        <button type="button" class="btn btn-primary" data-modal-open="modal-goal">+ Nova meta</button>
    <?php endif; ?>
</div>
<p class="section-sub">Crie uma meta pra quem reporta pra você e, se quiser, uma premiação por bater ela.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Verifique os dados da meta.</p>
<?php endif; ?>

<h3 class="section-title" style="margin-top:0;">Metas em andamento</h3>
<div class="cards-grid">
    <?php foreach ($active as $g): $p = Goal::progress($g); ?>
        <div class="dash-card goal-card">
            <span><?= View::e($g['name']) ?></span>
            <small class="hint-inline">
                <?= $g['seller_name'] ? View::e($g['seller_name']) : 'Geral' ?> · até <?= View::e(date('d/m/Y', strtotime($g['end_date']))) ?>
                <?php if ((int) $g['created_by'] !== (int) ($g['seller_id'] ?? 0)): ?><br>criada por <?= View::e($g['creator_name'] ?? '—') ?><?php endif; ?>
            </small>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $p['pct'] ?>%"></div></div>
            <strong><?= $p['pct'] ?>%<?= $p['reached'] ? ' 🎉' : '' ?></strong>
            <span class="hint-inline"><?= $fmtGoal($p['achieved'], $g['metric_type'] ?? 'valor') ?> de <?= $fmtGoal($p['target'], $g['metric_type'] ?? 'valor') ?></span>
            <?php if ($g['reward_description'] || $g['reward_amount']): ?>
                <?php
                $rewardParts = array_filter([
                    $g['reward_description'] ?: null,
                    $g['reward_amount'] ? 'R$ ' . number_format((float) $g['reward_amount'], 2, ',', '.') : null,
                ]);
                ?>
                <span class="hint-inline">🏆 <?= View::e(implode(' · ', $rewardParts)) ?><?= $g['reward_paid'] ? ' (pago)' : '' ?></span>
            <?php endif; ?>
            <div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;">
                <?php if (!$p['reached']): ?>
                    <button type="button" class="link-button" data-view-goal-pace="<?= (int) $g['id'] ?>">📈 Ver ritmo</button>
                <?php endif; ?>
                <?php if ($p['reached'] && !$g['reward_paid'] && ($g['reward_description'] || $g['reward_amount']) && (int) $g['created_by'] === $myId): ?>
                    <form action="/painel/metas/<?= (int) $g['id'] ?>/premio-pago" method="post" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="link-button">Marcar prêmio pago</button>
                    </form>
                <?php endif; ?>
                <?php if ((int) $g['created_by'] === $myId || $user['role_slug'] === 'admin'): ?>
                    <form action="/painel/metas/<?= (int) $g['id'] ?>/excluir" method="post" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="link-button">Excluir</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$active): ?>
        <p class="hint-text">Nenhuma meta em andamento.</p>
    <?php endif; ?>
</div>

<?php if ($inactive): ?>
<h3 class="section-title">Metas encerradas</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Meta</th><th>Destinatário</th><th>Período</th><th>Alcançado</th><th>Meta</th><th>%</th><th>Prêmio</th></tr></thead>
        <tbody>
            <?php foreach ($inactive as $g): $p = Goal::progress($g); ?>
                <tr>
                    <td><?= View::e($g['name']) ?></td>
                    <td><?= $g['seller_name'] ? View::e($g['seller_name']) : 'Geral' ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($g['start_date']))) ?> – <?= View::e(date('d/m/Y', strtotime($g['end_date']))) ?></td>
                    <td><?= $fmtGoal($p['achieved'], $g['metric_type'] ?? 'valor') ?></td>
                    <td><?= $fmtGoal($p['target'], $g['metric_type'] ?? 'valor') ?></td>
                    <td><?= $p['pct'] ?>%<?= $p['reached'] ? ' 🎉' : '' ?></td>
                    <td><?= $p['reached'] && ($g['reward_description'] || $g['reward_amount']) ? ($g['reward_paid'] ? 'Pago' : 'A pagar') : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<dialog class="modal" id="modal-goal">
    <div class="modal-header">
        <h2>Nova meta</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/metas" method="post" class="panel-form">
            <?= Csrf::field() ?>
            <label for="goal-name">Nome da meta</label>
            <input type="text" id="goal-name" name="name" required>

            <div class="form-grid-2">
                <div>
                    <label for="goal-start">Data início</label>
                    <input type="date" id="goal-start" name="start_date" value="<?= date('Y-m-01') ?>" required>
                </div>
                <div>
                    <label for="goal-end">Data fim</label>
                    <input type="date" id="goal-end" name="end_date" value="<?= date('Y-m-t') ?>" required>
                </div>
            </div>

            <?php $vendedorTargets = array_filter($targets, fn ($t) => $t['role_slug'] === 'vendedor'); ?>
            <label for="goal-seller">Meta para</label>
            <select id="goal-seller" name="seller_id" required>
                <option value="">Selecione...</option>
                <?php if (count($vendedorTargets) > 1): ?>
                    <option value="team">— Toda a equipe (<?= count($vendedorTargets) ?> Vendedores) —</option>
                <?php endif; ?>
                <?php foreach ($targets as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $myId && count($targets) === 1 ? 'selected' : '' ?>>
                        <?= View::e($t['name']) ?> <?= (int) $t['id'] === $myId ? '(eu mesmo)' : '(' . ($roleLabels[$t['role_slug']] ?? $t['role_slug']) . ')' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="hint-text" style="margin-top:2px;">"Toda a equipe" cria a mesma meta, individualmente, pra cada Vendedor — não divide o valor entre eles.</p>

            <label for="goal-metric">Tipo de meta</label>
            <select id="goal-metric" name="metric_type">
                <option value="valor">Valor faturado (R$)</option>
                <option value="quantidade">Quantidade de equipamentos vendidos</option>
            </select>
            <p class="hint-text" style="margin-top:2px;">Quantidade conta as placas/equipamentos vendidos no período — não muda com desconto ou faixa de preço, fica mais exato pra bater meta de vendas.</p>

            <label for="goal-value" id="goal-value-label">Valor da meta (R$)</label>
            <input type="number" step="0.01" id="goal-value" name="target_value" placeholder="Ex: 50000,00" required>

            <label for="goal-reward-desc">Premiação (opcional)</label>
            <input type="text" id="goal-reward-desc" name="reward_description" placeholder="Ex: Dia de folga, bônus de viagem...">

            <label for="goal-reward-amount">Valor do prêmio em R$ (opcional)</label>
            <input type="number" step="0.01" id="goal-reward-amount" name="reward_amount" placeholder="Ex: 500,00">

            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal" id="modal-goal-pace">
    <div id="modal-goal-pace-content"></div>
</dialog>

<script>
(function () {
    document.querySelectorAll('[data-view-goal-pace]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-view-goal-pace');
            openFragmentModal('modal-goal-pace', 'modal-goal-pace-content', '/painel/metas/' + id + '/ritmo?fragment=1', 'Ritmo da meta');
        });
    });
})();
(function () {
    var metricSelect = document.getElementById('goal-metric');
    var valueLabel = document.getElementById('goal-value-label');
    var valueInput = document.getElementById('goal-value');
    if (!metricSelect || !valueLabel || !valueInput) return;

    function sync() {
        if (metricSelect.value === 'quantidade') {
            valueLabel.textContent = 'Quantidade de equipamentos';
            valueInput.step = '1';
            valueInput.placeholder = 'Ex: 20';
        } else {
            valueLabel.textContent = 'Valor da meta (R$)';
            valueInput.step = '0.01';
            valueInput.placeholder = 'Ex: 50000,00';
        }
    }
    metricSelect.addEventListener('change', sync);
    sync();
})();
</script>
