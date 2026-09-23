<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Referral;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$myId = (int) $user['id'];
$statusBadge = [
    'indicado' => '',
    'em_contato' => '',
    'cadastrado' => 'dash-card-warning',
    'ativo' => '',
    'descartado' => 'dash-card-soon',
];
?>
<div class="page-header">
    <h1>🎁 Indicações Premiadas</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-referral">+ Nova indicação</button>
</div>
<p class="section-sub">Indique alguém que poderia virar Licenciado, Gestor ou Vendedor. Quando essa pessoa for cadastrada e virar ativa de verdade, você é avisado e pode registrar sua premiação.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Verifique os dados da indicação.</p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($referrals as $r): ?>
        <div class="dash-card <?= $statusBadge[$r['status']] ?? '' ?>" style="text-align:left;">
            <span><?= View::e($r['name']) ?> · <?= Referral::TARGET_ROLE_LABELS[$r['target_role']] ?? $r['target_role'] ?></span>
            <strong style="font-size:1.1rem;"><?= Referral::STATUS_LABELS[$r['status']] ?? $r['status'] ?></strong>
            <?php if ($r['whatsapp']): ?><span class="hint-inline">📱 <?= View::e($r['whatsapp']) ?></span><?php endif; ?>
            <?php if ($r['notes']): ?><span class="hint-inline"><?= View::e($r['notes']) ?></span><?php endif; ?>
            <?php if ($r['converted_user_name']): ?><span class="hint-inline">Vinculado a: <?= View::e($r['converted_user_name']) ?></span><?php endif; ?>

            <?php if (in_array($r['status'], ['indicado', 'em_contato'], true)): ?>
                <?php if ($r['status'] === 'indicado'): ?>
                    <form action="/painel/indicacoes/<?= (int) $r['id'] ?>/contato" method="post" class="inline-form" style="margin-top:6px;">
                        <?= Csrf::field() ?>
                        <button type="submit" class="link-button">Marcar "em contato"</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($linkable[$r['id']])): ?>
                    <form action="/painel/indicacoes/<?= (int) $r['id'] ?>/vincular" method="post" class="inline-form" style="margin-top:6px;display:flex;gap:6px;">
                        <?= Csrf::field() ?>
                        <select name="user_id" required style="flex:1;">
                            <option value="">Vincular a quem já foi cadastrado...</option>
                            <?php foreach ($linkable[$r['id']] as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= View::e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline">Vincular</button>
                    </form>
                <?php else: ?>
                    <p class="hint-text" style="margin-top:6px;">Ninguém da sua rede com esse papel disponível pra vincular ainda — crie o cadastro em Usuários primeiro.</p>
                <?php endif; ?>

                <form action="/painel/indicacoes/<?= (int) $r['id'] ?>/descartar" method="post" class="inline-form">
                    <?= Csrf::field() ?>
                    <button type="submit" class="link-button" data-confirm="Descartar essa indicação?">Descartar</button>
                </form>
            <?php endif; ?>

            <?php if ($r['status'] === 'cadastrado'): ?>
                <p class="hint-text" style="margin-top:6px;">Aguardando <?= $r['target_role'] === 'licenciado' ? 'aprovação do contrato' : 'a primeira venda' ?> pra liberar o prêmio.</p>
            <?php endif; ?>

            <?php if ($r['status'] === 'ativo'): ?>
                <?php if (!$r['reward_amount'] && !$r['reward_description']): ?>
                    <form action="/painel/indicacoes/<?= (int) $r['id'] ?>/premio" method="post" class="inline-form" style="margin-top:6px;display:flex;flex-direction:column;gap:6px;">
                        <?= Csrf::field() ?>
                        <input type="text" name="reward_description" placeholder="Descrição do prêmio (opcional)">
                        <input type="number" step="0.01" name="reward_amount" placeholder="Valor em R$">
                        <button type="submit" class="btn btn-outline">Registrar prêmio</button>
                    </form>
                <?php else: ?>
                    <span class="hint-inline">🏆 <?= View::e(implode(' · ', array_filter([$r['reward_description'], $r['reward_amount'] ? 'R$ ' . number_format((float) $r['reward_amount'], 2, ',', '.') : null]))) ?><?= $r['reward_paid'] ? ' (pago)' : '' ?></span>
                    <?php if (!$r['reward_paid']): ?>
                        <form action="/painel/indicacoes/<?= (int) $r['id'] ?>/premio-pago" method="post" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="link-button">Marcar prêmio pago</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$referrals): ?>
        <p class="hint-text">Nenhuma indicação registrada ainda.</p>
    <?php endif; ?>
</div>

<dialog class="modal" id="modal-referral">
    <div class="modal-header">
        <h2>Nova indicação</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/indicacoes" method="post" class="panel-form">
            <?= Csrf::field() ?>
            <label for="ref-name">Nome da pessoa indicada</label>
            <input type="text" id="ref-name" name="name" required>

            <label for="ref-whatsapp">WhatsApp (opcional)</label>
            <input type="text" id="ref-whatsapp" name="whatsapp" placeholder="Ex: 45999999999">

            <label for="ref-role">Poderia virar</label>
            <select id="ref-role" name="target_role">
                <option value="vendedor">Vendedor</option>
                <option value="gestor">Gestor</option>
                <option value="licenciado">Licenciado</option>
            </select>

            <label for="ref-notes">Observações (opcional)</label>
            <input type="text" id="ref-notes" name="notes" placeholder="Ex: já vende peças pra caminhão, tem contato com transportadoras...">

            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
