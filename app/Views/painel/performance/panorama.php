<?php
use App\Core\BrazilStates;
use App\Core\View;
?>
<div class="page-header">
    <h1>Panorama Nacional</h1>
</div>
<p class="section-sub">Onde a Ecodiffusore já tem Licenciado e pra onde ainda dá pra expandir — visão do país inteiro, sem filtro de rede.</p>

<div class="cards-grid">
    <div class="dash-card">
        <span>Licenciados ativos</span>
        <strong><?= (int) $totalLicenciados ?></strong>
    </div>
    <div class="dash-card">
        <span>Estados com cobertura</span>
        <strong><?= (int) $estadosCobertos ?> / <?= (int) $totalEstados ?></strong>
    </div>
    <div class="dash-card">
        <span>Estados sem Licenciado</span>
        <strong><?= count($missingStates) ?></strong>
    </div>
</div>

<h3 class="section-title">Mapa de cobertura</h3>
<?php include __DIR__ . '/../_brazil_grid.php'; ?>

<h3 class="section-title">Onde buscar Licenciado primeiro</h3>
<p class="section-sub">Estados ainda sem Licenciado, ordenados por quantidade de Leads recebidos vindos de lá — sinal de demanda esperando cobertura.</p>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Estado</th><th>Leads recebidos</th></tr></thead>
        <tbody>
            <?php
            $sortedMissing = $missingStates;
            uksort($sortedMissing, fn ($a, $b) => ($leadsByMissingState[$b] ?? 0) <=> ($leadsByMissingState[$a] ?? 0));
            ?>
            <?php foreach ($sortedMissing as $uf => $name): ?>
                <tr>
                    <td><?= View::e($name) ?> <span class="hint-text">(<?= $uf ?>)</span></td>
                    <td>
                        <?php if (!empty($leadsByMissingState[$uf])): ?>
                            <span class="status-badge status-novo"><?= (int) $leadsByMissingState[$uf] ?> lead(s)</span>
                        <?php else: ?>
                            <span class="hint-text">sem leads ainda</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$missingStates): ?>
                <tr><td colspan="2">Todos os estados já têm Licenciado. 🎉</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
