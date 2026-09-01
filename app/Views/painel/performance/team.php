<?php
use App\Core\View;

$renderPerson = function (array $person, string $roleLabel, string $icon) use (&$totals) {
    $t = $totals[(int) $person['id']] ?? null;
    ?>
    <div class="team-node">
        <div class="team-node-name"><?= $icon ?> <?= View::e($person['name']) ?> <span class="hint-text">(<?= $roleLabel ?>)</span></div>
        <div class="team-node-meta">
            <span><?= View::e($person['commission_pct'] !== null ? number_format((float) $person['commission_pct'], 2, ',', '.') . '% do pool' : 'sem % definido') ?></span>
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
<p class="section-sub">Hierarquia: Licenciado (dono da região) → Gestor → Vendedor. Pra editar um percentual, abra o usuário em Usuários.</p>

<?php if (!$roots): ?>
    <p>Nenhuma estrutura cadastrada ainda.</p>
<?php endif; ?>

<?php foreach ($roots as $licenciado): ?>
    <?php $rootIsGestor = $licenciado['role_slug'] === 'gestor'; ?>
    <div class="team-branch">
        <div class="team-node team-node-gestor">
            <div class="team-node-name"><?= $rootIsGestor ? '🧭' : '🏢' ?> <?= View::e($licenciado['name']) ?> <span class="hint-text">(<?= $rootIsGestor ? 'Gestor' : 'Licenciado' ?>)</span></div>
            <div class="team-node-meta">
                <span><?= View::e($licenciado['commission_pct'] !== null ? number_format((float) $licenciado['commission_pct'], 2, ',', '.') . ($rootIsGestor ? '% do pool' : '% (contratual)') : 'sem % definido') ?></span>
                <?php $t = $totals[(int) $licenciado['id']] ?? null; ?>
                <?php if ($t): ?><span>R$ <?= number_format((float) $t['total'], 2, ',', '.') ?> gerado</span><?php endif; ?>
            </div>
        </div>

        <div class="team-children">
            <?php $filhos = $byManager[(int) $licenciado['id']] ?? []; ?>
            <?php if (!$filhos): ?>
                <p class="hint-text">Nenhum gestor ou vendedor reportando pra este licenciado ainda.</p>
            <?php endif; ?>

            <?php foreach ($filhos as $filho): ?>
                <?php if ($filho['role_slug'] === 'gestor'): ?>
                    <div class="team-branch">
                        <div class="team-node team-node-supervisor">
                            <div class="team-node-name">🧭 <?= View::e($filho['name']) ?> <span class="hint-text">(Gestor)</span></div>
                            <div class="team-node-meta">
                                <span><?= View::e($filho['commission_pct'] !== null ? number_format((float) $filho['commission_pct'], 2, ',', '.') . '% do pool' : 'sem % definido') ?></span>
                                <?php $t = $totals[(int) $filho['id']] ?? null; ?>
                                <?php if ($t): ?><span>R$ <?= number_format((float) $t['total'], 2, ',', '.') ?> gerado</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="team-children">
                            <?php $vendedores = $byManager[(int) $filho['id']] ?? []; ?>
                            <?php if (!$vendedores): ?>
                                <p class="hint-text">Nenhum vendedor reportando pra este gestor ainda.</p>
                            <?php endif; ?>
                            <?php foreach ($vendedores as $v): ?>
                                <?php $renderPerson($v, 'Vendedor', '🧑‍💼'); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $renderPerson($filho, 'Vendedor', '🧑‍💼'); ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
