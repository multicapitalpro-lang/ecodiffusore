<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Goal;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Metas</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-goal">+ Nova meta</button>
</div>

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
            <small class="hint-inline"><?= $g['seller_name'] ? View::e($g['seller_name']) : 'Geral' ?> · até <?= View::e(date('d/m/Y', strtotime($g['end_date']))) ?></small>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $p['pct'] ?>%"></div></div>
            <strong><?= $p['pct'] ?>%</strong>
            <span class="hint-inline">R$ <?= number_format($p['achieved'], 2, ',', '.') ?> de R$ <?= number_format($p['target'], 2, ',', '.') ?></span>
            <form action="/painel/metas/<?= (int) $g['id'] ?>/excluir" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="link-button">Excluir</button>
            </form>
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
        <thead><tr><th>Meta</th><th>Vendedor</th><th>Período</th><th>Alcançado</th><th>Meta</th><th>%</th></tr></thead>
        <tbody>
            <?php foreach ($inactive as $g): $p = Goal::progress($g); ?>
                <tr>
                    <td><?= View::e($g['name']) ?></td>
                    <td><?= $g['seller_name'] ? View::e($g['seller_name']) : 'Geral' ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($g['start_date']))) ?> – <?= View::e(date('d/m/Y', strtotime($g['end_date']))) ?></td>
                    <td>R$ <?= number_format($p['achieved'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format($p['target'], 2, ',', '.') ?></td>
                    <td><?= $p['pct'] ?>%</td>
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

            <label for="goal-seller">Segmentação</label>
            <select id="goal-seller" name="seller_id">
                <option value="">Geral (todos)</option>
                <?php foreach ($sellers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= View::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="goal-value">Valor da meta (R$)</label>
            <input type="number" step="0.01" id="goal-value" name="target_value" placeholder="Ex: 50000,00" required>

            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
