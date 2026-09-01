<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = [
    'novo' => 'Novo',
    'contatado' => 'Contatado',
    'convertido' => 'Convertido',
    'descartado' => 'Descartado',
];
$csrfToken = Csrf::token();
$isViewOnly = $isViewOnly ?? false;
?>
<h1>Leads</h1>
<p class="section-sub"><?= $isViewOnly ? 'Visualização somente leitura.' : 'Arraste o card entre as colunas para atualizar o status.' ?></p>

<div class="kanban-board" id="leads-board">
    <?php foreach ($statusLabels as $status => $label): ?>
        <div class="kanban-col" data-status="<?= $status ?>">
            <div class="kanban-col-header">
                <?= View::e($label) ?>
                <span class="kanban-count"><?= count($columns[$status]) ?></span>
            </div>
            <div class="kanban-col-body" data-drop-status="<?= $status ?>">
                <?php foreach ($columns[$status] as $lead): ?>
                    <div class="kanban-card" draggable="<?= $isViewOnly ? 'false' : 'true' ?>" data-lead-id="<?= (int) $lead['id'] ?>">
                        <strong><?= View::e($lead['name']) ?></strong>
                        <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" target="_blank" rel="noopener" class="link-small"><?= View::e($lead['whatsapp']) ?></a>
                        <span class="kanban-card-meta"><?= View::e($lead['city'] ?: '—') ?> · <?= View::e($lead['truck_brand'] ?: '—') ?></span>
                        <?php if (!empty($lead['vehicle_plate'])): ?>
                            <span class="kanban-card-meta">
                                🚚 <?= View::e($lead['vehicle_plate']) ?>
                                <?= $lead['vehicle_year'] ? '· ' . View::e($lead['vehicle_year']) : '' ?>
                                <?= $lead['vehicle_brand'] ? '· ' . View::e($lead['vehicle_brand']) : '' ?>
                                <?= !empty($lead['vehicle_model']) ? '· ' . View::e($lead['vehicle_model']) : '' ?>
                                <?= $lead['vehicle_power'] ? '· ' . View::e($lead['vehicle_power']) : '' ?>
                                <?= $lead['vehicle_ecu_status'] ? '· ' . View::e($lead['vehicle_ecu_status'] === 'original' ? 'Original' : 'Reprogramado') : '' ?>
                                <?= !empty($lead['vehicle_reprogrammed_power']) ? ' (' . View::e($lead['vehicle_reprogrammed_power']) . ')' : '' ?>
                                <?= !empty($lead['vehicle_has_arla']) ? '· ARLA: ' . View::e($lead['vehicle_has_arla'] === 'sim' ? 'Sim' : 'Não') : '' ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($canAssign): ?>
                            <select class="lead-assign-select" data-lead-id="<?= (int) $lead['id'] ?>">
                                <option value="">Sem responsável</option>
                                <?php foreach ($sellers as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($lead['assigned_to_user_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (!empty($lead['assigned_name'])): ?>
                            <span class="kanban-card-meta">Com: <?= View::e($lead['assigned_name']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$columns[$status]): ?>
                    <p class="hint-text kanban-empty">Nenhum lead aqui.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const board = document.getElementById('leads-board');
    if (!board) return;

    let draggedId = null;

    board.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', () => { draggedId = card.dataset.leadId; card.classList.add('is-dragging'); });
        card.addEventListener('dragend', () => card.classList.remove('is-dragging'));
    });

    board.querySelectorAll('.kanban-col-body').forEach((col) => {
        col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('is-dragover'); });
        col.addEventListener('dragleave', () => col.classList.remove('is-dragover'));
        col.addEventListener('drop', async (e) => {
            e.preventDefault();
            col.classList.remove('is-dragover');
            if (!draggedId) return;

            const status = col.dataset.dropStatus;
            const formData = new FormData();
            formData.set('csrf_token', csrfToken);
            formData.set('status', status);

            await fetch('/painel/leads/' + draggedId + '/status', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            window.location.reload();
        });
    });

    board.querySelectorAll('.lead-assign-select').forEach((select) => {
        select.addEventListener('change', async () => {
            const formData = new FormData();
            formData.set('csrf_token', csrfToken);
            formData.set('seller_id', select.value);
            await fetch('/painel/leads/' + select.dataset.leadId + '/atribuir', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            window.location.reload();
        });
    });
})();
</script>
