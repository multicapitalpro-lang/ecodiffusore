<?php
use App\Core\View;
?>
<div class="page-header">
    <h1>Relatórios Financeiros</h1>
    <a href="/painel/financeiro/relatorios/agendamentos" class="btn btn-outline">Agendamentos</a>
</div>

<?php foreach ($catalog as $group => $reports): ?>
    <h3 class="section-title"><?= View::e($group) ?></h3>
    <div class="cards-grid">
        <?php foreach ($reports as $key => $label): ?>
            <a href="/painel/financeiro/relatorios/<?= $key ?>" class="dash-card report-card">
                <span><?= View::e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
