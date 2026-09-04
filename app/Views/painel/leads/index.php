<?php
use App\Core\Csrf;
use App\Core\View;
$csrfToken = Csrf::token();
$isViewOnly = $isViewOnly ?? false;
$sucesso = $_GET['sucesso'] ?? null;
$erro = $_GET['erro'] ?? null;

$vehicleFieldLabels = [
    'vehicle_plate' => 'Placa',
    'vehicle_year' => 'Ano',
    'vehicle_brand' => 'Marca',
    'vehicle_model' => 'Modelo',
    'vehicle_power' => 'Potência',
    'vehicle_ecu_status' => 'Situação da ECU',
    'vehicle_reprogrammed_power' => 'Potência reprogramada',
    'vehicle_has_arla' => 'Usa ARLA',
];
?>
<p class="section-sub"><?= $isViewOnly ? 'Visualização somente leitura.' : 'Arraste o card entre as colunas para atualizar o status.' ?></p>

<?php if ($sucesso === '1'): ?>
    <p class="form-msg form-msg-ok">Lead excluído.</p>
<?php elseif ($sucesso === '2'): ?>
    <p class="form-msg form-msg-ok">Coluna criada.</p>
<?php elseif ($erro === 'csrf'): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($stages as $stage): ?>
        <div class="dash-card">
            <span><?= View::e($stage['name']) ?></span>
            <strong><?= count($columns[$stage['slug']] ?? []) ?></strong>
        </div>
    <?php endforeach; ?>
</div>

<div class="kanban-board" id="leads-board">
    <?php foreach ($stages as $stage): $status = $stage['slug']; ?>
        <div class="kanban-col" data-status="<?= View::e($status) ?>">
            <div class="kanban-col-header">
                <?= View::e($stage['name']) ?>
                <span class="kanban-count"><?= count($columns[$status] ?? []) ?></span>
            </div>
            <div class="kanban-col-body" data-drop-status="<?= View::e($status) ?>">
                <?php foreach ($columns[$status] ?? [] as $lead): ?>
                    <?php
                    $vehicleInfo = [];
                    foreach ($vehicleFieldLabels as $field => $label) {
                        if (!empty($lead[$field])) {
                            $value = $lead[$field];
                            if ($field === 'vehicle_ecu_status') {
                                $value = $value === 'original' ? 'Original' : 'Reprogramado';
                            } elseif ($field === 'vehicle_has_arla') {
                                $value = $value === 'sim' ? 'Sim' : 'Não';
                            }
                            $vehicleInfo[$label] = $value;
                        }
                    }
                    ?>
                    <div class="kanban-card"
                         draggable="<?= $isViewOnly ? 'false' : 'true' ?>"
                         data-lead-id="<?= (int) $lead['id'] ?>"
                         data-lead-name="<?= View::e($lead['name']) ?>"
                         data-lead-whatsapp="<?= View::e($lead['whatsapp']) ?>"
                         data-lead-city="<?= View::e($lead['city'] ?: '') ?>"
                         data-lead-truck="<?= View::e($lead['truck_brand'] ?: '') ?>"
                         data-lead-message="<?= View::e($lead['message'] ?: '') ?>"
                         data-lead-source="<?= View::e($lead['source'] ?: '') ?>"
                         data-lead-assigned="<?= View::e($lead['assigned_name'] ?? '') ?>"
                         data-lead-created="<?= View::e($lead['created_at'] ?? '') ?>"
                         data-lead-vehicle="<?= View::e(json_encode($vehicleInfo, JSON_UNESCAPED_UNICODE)) ?>">
                        <div class="kanban-card-top">
                            <strong><?= View::e($lead['name']) ?></strong>
                            <button type="button" class="icon-button-danger" data-delete-lead="<?= (int) $lead['id'] ?>" title="Excluir lead">🗑</button>
                        </div>
                        <span class="lead-phone">
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" target="_blank" rel="noopener" class="link-small" onclick="event.stopPropagation()">💬 <?= View::e($lead['whatsapp']) ?></a>
                        </span>
                        <span class="kanban-card-meta"><?= View::e($lead['city'] ?: '—') ?> · <?= View::e($lead['truck_brand'] ?: '—') ?></span>
                        <?php if ($vehicleInfo): ?>
                            <span class="kanban-card-meta">🚚 <?= View::e(implode(' · ', $vehicleInfo)) ?></span>
                        <?php endif; ?>
                        <?php if ($canAssign): ?>
                            <select class="lead-assign-select" data-lead-id="<?= (int) $lead['id'] ?>" onclick="event.stopPropagation()">
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
                <?php if (!($columns[$status] ?? [])): ?>
                    <p class="hint-text kanban-empty">Nenhum lead aqui.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="kanban-col kanban-col-add">
        <form method="post" action="/painel/leads/colunas" class="kanban-add-form">
            <?= Csrf::field() ?>
            <label for="new-stage-name" class="hint-text">+ Nova coluna</label>
            <input type="text" id="new-stage-name" name="name" placeholder="Nome da coluna" maxlength="60" required>
            <button type="submit" class="btn btn-outline">Adicionar</button>
        </form>
    </div>
</div>

<dialog class="modal" id="modal-lead-detail">
    <div class="modal-header">
        <h2 id="lead-detail-name">Lead</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <p><strong>WhatsApp:</strong> <a id="lead-detail-whatsapp-link" href="#" target="_blank" rel="noopener"></a></p>
        <p><strong>Cidade:</strong> <span id="lead-detail-city"></span></p>
        <p><strong>Marca do caminhão:</strong> <span id="lead-detail-truck"></span></p>
        <p><strong>Origem:</strong> <span id="lead-detail-source"></span></p>
        <p><strong>Responsável:</strong> <span id="lead-detail-assigned"></span></p>
        <p><strong>Cadastrado em:</strong> <span id="lead-detail-created"></span></p>
        <div id="lead-detail-vehicle"></div>
        <p><strong>Mensagem:</strong></p>
        <p id="lead-detail-message" class="hint-text"></p>
    </div>
</dialog>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const board = document.getElementById('leads-board');
    if (!board) return;

    let draggedId = null;

    board.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', () => { draggedId = card.dataset.leadId; card.classList.add('is-dragging'); });
        card.addEventListener('dragend', () => card.classList.remove('is-dragging'));

        card.addEventListener('click', (e) => {
            if (e.target.closest('select, a, button')) return;

            document.getElementById('lead-detail-name').textContent = card.dataset.leadName || 'Lead';
            const waLink = document.getElementById('lead-detail-whatsapp-link');
            const phone = (card.dataset.leadWhatsapp || '').replace(/\D/g, '');
            waLink.href = phone ? 'https://wa.me/55' + phone : '#';
            waLink.textContent = card.dataset.leadWhatsapp || '—';
            document.getElementById('lead-detail-city').textContent = card.dataset.leadCity || '—';
            document.getElementById('lead-detail-truck').textContent = card.dataset.leadTruck || '—';
            document.getElementById('lead-detail-source').textContent = card.dataset.leadSource || '—';
            document.getElementById('lead-detail-assigned').textContent = card.dataset.leadAssigned || 'Sem responsável';
            document.getElementById('lead-detail-created').textContent = card.dataset.leadCreated || '—';
            document.getElementById('lead-detail-message').textContent = card.dataset.leadMessage || '— sem mensagem —';

            const vehicleBox = document.getElementById('lead-detail-vehicle');
            vehicleBox.innerHTML = '';
            try {
                const vehicle = JSON.parse(card.dataset.leadVehicle || '{}');
                const keys = Object.keys(vehicle);
                if (keys.length) {
                    const title = document.createElement('p');
                    title.innerHTML = '<strong>Veículo:</strong>';
                    vehicleBox.appendChild(title);
                    keys.forEach((k) => {
                        const line = document.createElement('p');
                        line.className = 'hint-text';
                        line.textContent = k + ': ' + vehicle[k];
                        vehicleBox.appendChild(line);
                    });
                }
            } catch (err) { /* sem dados de veiculo */ }

            document.getElementById('modal-lead-detail').showModal();
        });
    });

    board.querySelectorAll('[data-delete-lead]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!confirm('Excluir este lead? Essa ação não pode ser desfeita.')) return;

            const formData = new FormData();
            formData.set('csrf_token', csrfToken);
            fetch('/painel/leads/' + btn.dataset.deleteLead + '/excluir', {
                method: 'POST',
                body: formData,
            }).then(() => window.location.reload());
        });
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
