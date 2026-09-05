<?php
use App\Core\BrazilMapData;
use App\Core\BrazilStates;
use App\Core\View;
/** @var array $byState grupo por UF ['count'=>int, 'cities'=>string[]] */
/** @var bool $interactive se true, cada estado fica clicavel (data-uf) pro drill-down por estado */
$byState = $byState ?? [];
$interactive = $interactive ?? false;
$maxCount = $byState ? max(array_column($byState, 'count')) : 0;
?>
<div class="br-map-wrap">
    <svg viewBox="<?= BrazilMapData::NATIONAL_VIEWBOX ?>" xmlns="http://www.w3.org/2000/svg" class="br-map-svg" id="<?= $interactive ? 'panorama-grid' : '' ?>">
        <?php foreach (BrazilMapData::NATIONAL_PATHS as $uf => $d): ?>
            <?php
            $data = $byState[$uf] ?? ['count' => 0, 'cities' => []];
            $intensity = $maxCount > 0 ? $data['count'] / $maxCount : 0;
            $nativeTitle = BrazilStates::NAMES[$uf] . ($data['count'] > 0
                ? ' — ' . $data['count'] . ' licenciado(s): ' . implode(', ', $data['cities'])
                : ' — sem licenciado ainda');
            ?>
            <path d="<?= $d ?>"
                  class="br-state <?= $data['count'] > 0 ? 'has-licenciado' : 'empty' ?><?= $interactive ? ' br-state-clickable' : '' ?>"
                  style="<?= $data['count'] > 0 ? 'opacity:' . max(.45, $intensity) . ';' : '' ?>"
                  <?php if ($interactive): ?>
                  data-uf="<?= $uf ?>" data-name="<?= View::e(BrazilStates::NAMES[$uf]) ?>" data-count="<?= (int) $data['count'] ?>" data-cities="<?= View::e(implode(', ', $data['cities'])) ?>"
                  <?php endif; ?>
            ><?php if (!$interactive): ?><title><?= View::e($nativeTitle) ?></title><?php endif; ?></path>
        <?php endforeach; ?>
    </svg>
</div>
<p class="br-grid-legend"><span class="br-legend-dot has-licenciado"></span> Com licenciado &nbsp; <span class="br-legend-dot empty"></span> Sem licenciado ainda</p>
