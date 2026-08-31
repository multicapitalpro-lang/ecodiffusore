<?php
use App\Core\View;

$renderPerson = function (array $person) use (&$totals) {
    $t = $totals[(int) $person['id']] ?? null;
    ?>
    <div class="team-node">
        <div class="team-node-name"><?= View::e($person['name']) ?></div>
        <div class="team-node-meta">
            <span><?= View::e($person['commission_pct'] !== null ? number_format((float) $person['commission_pct'], 2, ',', '.') . '%' : 'sem % definido') ?></span>
            <?php if ($t): ?>
                <span>R$ <?= number_format((float) $t['total'], 2, ',', '.') ?> gerado</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
};
?>
<div class="page-header">
    <h1>Estrutura de Equipe</h1>
    <a href="/painel/usuarios/novo" class="btn btn-outline">+ Novo usuário</a>
</div>
<p class="section-sub">Hierarquia completa e comissão de cada pessoa. Para editar um percentual, abra o usuário em Usuários.</p>

<?php if (!$gerentes && !$semGerente): ?>
    <p>Nenhum gerente ou supervisor cadastrado ainda.</p>
<?php endif; ?>

<?php foreach ($gerentes as $gerente): ?>
    <div class="team-branch">
        <div class="team-node team-node-gerente">
            <div class="team-node-name">👑 <?= View::e($gerente['name']) ?> <span class="hint-text">(Gerente)</span></div>
            <div class="team-node-meta">
                <span><?= View::e($gerente['commission_pct'] !== null ? number_format((float) $gerente['commission_pct'], 2, ',', '.') . '%' : 'sem % definido') ?></span>
                <?php $t = $totals[(int) $gerente['id']] ?? null; ?>
                <?php if ($t): ?><span>R$ <?= number_format((float) $t['total'], 2, ',', '.') ?> gerado</span><?php endif; ?>
            </div>
        </div>

        <div class="team-children">
            <?php $supervisores = $byManager[(int) $gerente['id']] ?? []; ?>
            <?php if (!$supervisores): ?>
                <p class="hint-text">Nenhum supervisor reportando pra este gerente ainda.</p>
            <?php endif; ?>
            <?php foreach ($supervisores as $sup): ?>
                <div class="team-branch">
                    <div class="team-node team-node-supervisor">
                        <div class="team-node-name">🧭 <?= View::e($sup['name']) ?> <span class="hint-text">(Supervisor)</span></div>
                        <div class="team-node-meta">
                            <span><?= View::e($sup['commission_pct'] !== null ? number_format((float) $sup['commission_pct'], 2, ',', '.') . '%' : 'sem % definido') ?></span>
                            <?php $t = $totals[(int) $sup['id']] ?? null; ?>
                            <?php if ($t): ?><span>R$ <?= number_format((float) $t['total'], 2, ',', '.') ?> gerado</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="team-children">
                        <?php $licenciados = $byManager[(int) $sup['id']] ?? []; ?>
                        <?php if (!$licenciados): ?>
                            <p class="hint-text">Nenhum licenciado reportando pra este supervisor ainda.</p>
                        <?php endif; ?>
                        <?php foreach ($licenciados as $lic): ?>
                            <?php $renderPerson($lic); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($semGerente): ?>
    <h3 class="section-title">Supervisores sem gerente definido</h3>
    <?php foreach ($semGerente as $sup): ?>
        <div class="team-branch">
            <div class="team-node team-node-supervisor">
                <div class="team-node-name">🧭 <?= View::e($sup['name']) ?> <span class="hint-text">(Supervisor)</span></div>
                <div class="team-node-meta">
                    <span><?= View::e($sup['commission_pct'] !== null ? number_format((float) $sup['commission_pct'], 2, ',', '.') . '%' : 'sem % definido') ?></span>
                </div>
            </div>
            <div class="team-children">
                <?php $licenciados = $byManager[(int) $sup['id']] ?? []; ?>
                <?php foreach ($licenciados as $lic): ?>
                    <?php $renderPerson($lic); ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php $orfaos = $byManager[0] ?? []; $orfaos = array_values(array_filter($orfaos, fn ($u) => $u['role_slug'] === 'licenciado')); ?>
<?php if ($orfaos): ?>
    <h3 class="section-title">Licenciados sem supervisor definido</h3>
    <div class="team-children">
        <?php foreach ($orfaos as $lic): ?>
            <?php $renderPerson($lic); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
