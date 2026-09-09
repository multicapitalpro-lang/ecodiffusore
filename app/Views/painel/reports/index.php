<?php
use App\Core\Roles;
use App\Core\View;
?>
<div class="page-header">
    <h1>Relatórios</h1>
    <?php if (in_array($user['role_slug'], Roles::MANAGEMENT, true)): ?>
        <a href="/painel/financeiro/relatorios/agendamentos" class="btn btn-outline">Agendamentos</a>
    <?php endif; ?>
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
