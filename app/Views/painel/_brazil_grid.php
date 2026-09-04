<?php
use App\Core\BrazilStates;
use App\Core\View;
/** @var array $byState grupo por UF ['count'=>int, 'cities'=>string[]] */
/** @var bool $interactive se true, cada celula fica clicavel (data-uf) pro drill-down por estado */
$byState = $byState ?? [];
$interactive = $interactive ?? false;
$maxCount = $byState ? max(array_column($byState, 'count')) : 0;
?>
<div class="br-grid" id="<?= $interactive ? 'panorama-grid' : '' ?>">
    <?php foreach (BrazilStates::GRID as $uf => [$row, $col]): ?>
        <?php
        $data = $byState[$uf] ?? ['count' => 0, 'cities' => []];
        $intensity = $maxCount > 0 ? $data['count'] / $maxCount : 0;
        $title = BrazilStates::NAMES[$uf] . ($data['count'] > 0
            ? ' — ' . $data['count'] . ' licenciado(s): ' . implode(', ', $data['cities'])
            : ' — sem licenciado ainda') . ($interactive ? ' (clique pra ver detalhes)' : '');
        ?>
        <div class="br-cell <?= $data['count'] > 0 ? 'has-licenciado' : 'empty' ?><?= $interactive ? ' br-cell-clickable' : '' ?>"
             style="grid-row:<?= $row + 1 ?>;grid-column:<?= $col + 1 ?>;<?= $data['count'] > 0 ? 'opacity:' . max(.45, $intensity) . ';' : '' ?>"
             title="<?= View::e($title) ?>"
             <?= $interactive ? 'data-uf="' . $uf . '"' : '' ?>>
            <span class="br-cell-uf"><?= $uf ?></span>
            <?php if ($data['count'] > 0): ?><span class="br-cell-count"><?= (int) $data['count'] ?></span><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<p class="br-grid-legend"><span class="br-legend-dot has-licenciado"></span> Com licenciado &nbsp; <span class="br-legend-dot empty"></span> Sem licenciado ainda</p>
