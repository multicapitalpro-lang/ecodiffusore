<?php
use App\Core\Csrf;
use App\Core\View;
$csrfToken = Csrf::token();
$isViewOnly = $isViewOnly ?? false;
$showLicenciadoBadge = $showLicenciadoBadge ?? false;
$sucesso = $_GET['sucesso'] ?? null;
$erro = $_GET['erro'] ?? null;
$expirationWarningDays = $expirationWarningDays ?? 5;

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
<?php elseif ($sucesso === '3'): ?>
    <p class="form-msg form-msg-ok">Prazo estendido por mais 30 dias.</p>
<?php elseif ($erro === 'csrf'): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php elseif ($erro === 'justificativa'): ?>
    <p class="form-msg form-msg-erro">Informe a justificativa pra estender o prazo.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro"><?= View::e($erro) ?></p>
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
                         data-lead-vehicle="<?= View::e(json_encode($vehicleInfo, JSON_UNESCAPED_UNICODE)) ?>"
                         data-lead-notes="<?= View::e(json_encode($lead['notes'], JSON_UNESCAPED_UNICODE)) ?>">
                        <div class="kanban-card-top">
                            <strong><?= View::e($lead['name']) ?></strong>
                            <button type="button" class="icon-button-danger" data-delete-lead="<?= (int) $lead['id'] ?>" title="Excluir lead">🗑</button>
                        </div>
                        <?php
                        // Mensagem pronta com nome + contexto do veiculo -- elimina o vendedor ter
                        // que copiar numero e lembrar o que o lead informou toda vez que for chamar.
                        $waMessage = 'Olá, ' . $lead['name'] . '! Aqui é da Ecodiffusore Brasil.'
                            . ($vehicleInfo ? ' Vi as informações do seu veículo (' . implode(', ', $vehicleInfo) . ')' : ($lead['truck_brand'] ? ' Vi seu interesse no ' . $lead['truck_brand'] : ''))
                            . ' e queria te ajudar a economizar no diesel.';
                        ?>
                        <span class="lead-phone">
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>?text=<?= rawurlencode($waMessage) ?>" target="_blank" rel="noopener" class="link-small" onclick="event.stopPropagation()">💬 <?= View::e($lead['whatsapp']) ?></a>
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
                        <?php if ($showLicenciadoBadge && !empty($lead['licenciado_name'])): ?>
                            <span class="kanban-card-meta">Licenciado: <?= View::e($lead['licenciado_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($lead['days_until_expiration'] !== null && $lead['days_until_expiration'] <= $expirationWarningDays): ?>
                            <div class="lead-expiration-warning">
                                ⏳ <?= $lead['days_until_expiration'] > 0
                                    ? 'Expira em ' . (int) $lead['days_until_expiration'] . ' dia' . ((int) $lead['days_until_expiration'] === 1 ? '' : 's')
                                    : 'Prazo vencido — será devolvido ao Licenciado' ?>
                                <?php if (!$isViewOnly): ?>
                                    <button type="button" class="link-button" data-request-extension="<?= (int) $lead['id'] ?>" onclick="event.stopPropagation()">Solicitar extensão</button>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($lead['days_until_expiration'] !== null && $lead['days_until_expiration'] <= $earlyWarningDays): ?>
                            <div class="lead-expiration-warning lead-expiration-warning-early">
                                👀 Fica de olho: expira em <?= (int) $lead['days_until_expiration'] ?> dias
                                <?php if (!$isViewOnly): ?>
                                    <button type="button" class="link-button" data-request-extension="<?= (int) $lead['id'] ?>" onclick="event.stopPropagation()">Solicitar extensão</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($lead['follow_up_due'])): ?>
                            <div class="lead-expiration-warning lead-expiration-warning-early">
                                🔔 Retorno combinado pra <?= View::e(date('d/m', strtotime($lead['follow_up_due']))) ?>
                            </div>
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

<dialog class="modal" id="modal-lead-extension">
    <div class="modal-header">
        <h2>Solicitar extensão de prazo</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <p class="hint-text" style="margin-top:0;">Explique por que precisa manter esse lead por mais 30 dias. Fica registrado no histórico, visível pro Licenciado/Supervisor/Gerente/Admin.</p>
        <form id="lead-extension-form" class="panel-form" enctype="multipart/form-data">
            <label for="extension-justification">Justificativa</label>
            <textarea id="extension-justification" name="justification" rows="4" required></textarea>
            <label for="extension-attachment">Anexo (opcional — print da conversa, etc.)</label>
            <input type="file" id="extension-attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp">
            <button type="submit" class="btn btn-primary" style="margin-top:10px;">Estender por 30 dias</button>
        </form>
    </div>
</dialog>

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

        <div id="notas" style="margin-top:18px; border-top:1px solid var(--border); padding-top:14px;">
            <h3 class="section-title" style="margin-top:0;">Observações</h3>
            <?php if ($canAddNotes ?? true): ?>
                <form id="lead-note-form" class="panel-form">
                    <textarea name="note" placeholder="Ex: liguei, disse que vai pensar, volto a ligar semana que vem..." required></textarea>
                    <label for="lead-note-followup" style="margin-top:6px;">Lembrar de retornar em (opcional)</label>
                    <input type="date" id="lead-note-followup" name="follow_up_date" style="max-width:180px;">
                    <button type="submit" class="btn btn-outline btn-sm" style="margin-top:8px;">Adicionar observação</button>
                </form>
            <?php endif; ?>
            <div id="lead-detail-notes-list" class="notes-list"></div>
        </div>
    </div>
</dialog>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const board = document.getElementById('leads-board');
    if (!board) return;

    let draggedId = null;
    let currentLeadId = null;

    function renderNotes(notes) {
        const list = document.getElementById('lead-detail-notes-list');
        list.innerHTML = '';
        if (!notes || !notes.length) {
            list.innerHTML = '<p class="hint-text">Nenhuma observação registrada ainda.</p>';
            return;
        }
        const today = new Date().toISOString().slice(0, 10);
        notes.forEach((n) => {
            const item = document.createElement('div');
            item.className = 'note-item';
            const p = document.createElement('p');
            p.textContent = n.note;
            item.appendChild(p);
            const small = document.createElement('small');
            small.textContent = (n.user_name || 'Sistema') + ' — ' + new Date(n.created_at).toLocaleString('pt-BR');
            item.appendChild(small);
            if (n.follow_up_date) {
                const fu = document.createElement('small');
                fu.style.display = 'block';
                const late = !parseInt(n.follow_up_done, 10) && n.follow_up_date <= today;
                fu.className = late ? 'text-red' : 'hint-text';
                const label = parseInt(n.follow_up_done, 10) ? 'concluído' : 'combinado';
                fu.textContent = '🔔 Retorno ' + label + ' pra ' + n.follow_up_date.split('-').reverse().join('/');
                item.appendChild(fu);
            }
            list.appendChild(item);
        });
    }

    function buildWaMessage(card) {
        const name = card.dataset.leadName || '';
        const truck = card.dataset.leadTruck || '';
        let vehicleParts = [];
        try {
            vehicleParts = Object.values(JSON.parse(card.dataset.leadVehicle || '{}'));
        } catch (e) { /* ignora JSON invalido, so nao mostra detalhe do veiculo */ }

        let msg = 'Olá' + (name ? ', ' + name : '') + '! Aqui é da Ecodiffusore Brasil.';
        if (vehicleParts.length) {
            msg += ' Vi as informações do seu veículo (' + vehicleParts.join(', ') + ')';
        } else if (truck) {
            msg += ' Vi seu interesse no ' + truck;
        }
        return msg + ' e queria te ajudar a economizar no diesel.';
    }

    board.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', () => { draggedId = card.dataset.leadId; card.classList.add('is-dragging'); });
        card.addEventListener('dragend', () => card.classList.remove('is-dragging'));

        card.addEventListener('click', (e) => {
            if (e.target.closest('select, a, button')) return;

            currentLeadId = card.dataset.leadId;
            document.getElementById('lead-detail-name').textContent = card.dataset.leadName || 'Lead';
            const waLink = document.getElementById('lead-detail-whatsapp-link');
            const phone = (card.dataset.leadWhatsapp || '').replace(/\D/g, '');
            waLink.href = phone ? 'https://wa.me/55' + phone + '?text=' + encodeURIComponent(buildWaMessage(card)) : '#';
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

            let notes = [];
            try {
                notes = JSON.parse(card.dataset.leadNotes || '[]');
            } catch (err) { /* sem notas */ }
            renderNotes(notes);

            document.getElementById('modal-lead-detail').showModal();
        });
    });

    const leadNoteForm = document.getElementById('lead-note-form');
    if (leadNoteForm) {
        leadNoteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!currentLeadId) return;

            const formData = new FormData(leadNoteForm);
            formData.set('csrf_token', csrfToken);

            const submitBtn = leadNoteForm.querySelector('button[type=submit]');
            submitBtn.disabled = true;

            try {
                const res = await fetch('/painel/leads/' + currentLeadId + '/notas', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (data.ok) {
                    renderNotes(data.notes);
                    leadNoteForm.reset();
                    const card = board.querySelector('.kanban-card[data-lead-id="' + currentLeadId + '"]');
                    if (card) card.dataset.leadNotes = JSON.stringify(data.notes);
                } else {
                    alert(data.error || 'Erro ao salvar. Tente novamente.');
                }
            } catch (err) {
                alert('Erro ao salvar. Tente novamente.');
            }
            submitBtn.disabled = false;
        });
    }

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

    const extensionModal = document.getElementById('modal-lead-extension');
    const extensionForm = document.getElementById('lead-extension-form');
    let extensionLeadId = null;

    board.querySelectorAll('[data-request-extension]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            extensionLeadId = btn.dataset.requestExtension;
            extensionForm.reset();
            extensionModal.showModal();
        });
    });

    if (extensionForm) {
        extensionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!extensionLeadId) return;

            const formData = new FormData(extensionForm);
            formData.set('csrf_token', csrfToken);

            const submitBtn = extensionForm.querySelector('button[type=submit]');
            submitBtn.disabled = true;

            try {
                await fetch('/painel/leads/' + extensionLeadId + '/estender', {
                    method: 'POST',
                    body: formData,
                });
                window.location.href = '/painel/leads?sucesso=3';
            } catch (err) {
                submitBtn.disabled = false;
                alert('Erro ao enviar. Tente novamente.');
            }
        });
    }

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
