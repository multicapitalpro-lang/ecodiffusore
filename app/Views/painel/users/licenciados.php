<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$statusFilter = $statusFilter ?? '';
$onboardingLabels = [
    'aguardando_perfil' => ['Aguardando perfil', 'aguardando-perfil'],
    'aguardando_assinatura' => ['Aguardando assinatura', 'aguardando-assinatura'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'aguardando-aprovacao'],
    'ativo' => ['Ativo', 'active'],
    'assinatura_recusada' => ['Assinatura recusada', 'recusado'],
    'kyc_recusado' => ['KYC recusado', 'recusado'],
];
?>
<div class="page-header">
    <h1>Licenciados</h1>
</div>
<p class="section-sub">Defina qual Supervisor fica responsável por dar suporte a cada Licenciado.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível atualizar.</p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total de licenciados</span>
        <strong><?= (int) $stats['total'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Ativos</span>
        <strong><?= (int) $stats['ativos'] ?></strong>
    </div>
    <div class="dash-card <?= $stats['sem_supervisor'] > 0 ? 'dash-card-danger' : '' ?>">
        <span>Sem supervisor</span>
        <strong><?= (int) $stats['sem_supervisor'] ?></strong>
    </div>
    <?php foreach (['aguardando_perfil', 'aguardando_assinatura', 'aguardando_aprovacao'] as $st): ?>
        <?php if (!empty($stats['por_status'][$st])): ?>
            <div class="dash-card">
                <span><?= $onboardingLabels[$st][0] ?></span>
                <strong><?= (int) $stats['por_status'][$st] ?></strong>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<form method="get" class="filter-bar">
    <input type="text" id="licenciado-search" placeholder="Buscar por código, nome ou e-mail...">
    <select name="status">
        <option value="">Onboarding (todos)</option>
        <?php foreach ($onboardingLabels as $key => [$label, ]): ?>
            <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php if ($statusFilter !== ''): ?><a class="link-small" href="/painel/licenciados">Limpar filtro</a><?php endif; ?>
</form>

<form method="post" action="/painel/licenciados/atribuir-lote" id="bulk-form">
    <?= Csrf::field() ?>
    <div id="bulk-bar" style="display:none;margin-bottom:12px;align-items:center;gap:10px;">
        <select name="supervisor_id" id="bulk-supervisor-select">
            <option value="">— nenhum —</option>
            <?php foreach ($supervisors as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= View::e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Atribuir aos <span id="bulk-count">0</span> selecionados</button>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="licenciados-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-licenciados"></th>
                    <th>Código</th>
                    <th>Licenciado</th>
                    <th>E-mail</th>
                    <th>Cidade/UF</th>
                    <th>Onboarding</th>
                    <th>Comissão (total)</th>
                    <th>Vendas da equipe</th>
                    <th>Supervisor responsável</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenciados as $l): ?>
                    <?php
                    $status = $l['licenciado_onboarding_status'] ?? 'nao_aplicavel';
                    [$statusLabel, $statusBadge] = $onboardingLabels[$status] ?? [null, null];
                    $comm = $commissionTotals[(int) $l['id']] ?? null;
                    $vendas = $vendasTotals[(int) $l['id']] ?? null;
                    $semSupervisor = empty($l['supervisor_id']);
                    $whatsapp = preg_replace('/\D/', '', (string) ($l['whatsapp'] ?? ''));
                    ?>
                    <tr class="<?= $semSupervisor ? 'row-attention' : '' ?>" data-search="<?= View::e(mb_strtolower(($l['licenciado_code'] ?? '') . ' ' . $l['name'] . ' ' . $l['email'])) ?>">
                        <td><input type="checkbox" name="licenciado_ids[]" value="<?= (int) $l['id'] ?>" class="row-select-licenciado"></td>
                        <td><strong><?= View::e($l['licenciado_code'] ?? '—') ?></strong></td>
                        <td>
                            <?= View::e($l['name']) ?>
                            <?php if ($whatsapp !== ''): ?>
                                <a href="https://wa.me/55<?= $whatsapp ?>" target="_blank" rel="noopener" title="Falar no WhatsApp">💬</a>
                            <?php endif; ?>
                        </td>
                        <td><?= View::e($l['email']) ?></td>
                        <td><?= $l['city'] ? View::e($l['city']) . ($l['state'] ? '/' . View::e($l['state']) : '') : '—' ?></td>
                        <td>
                            <?php if ($statusLabel): ?>
                                <span class="status-badge status-<?= View::e($statusBadge) ?>"><?= View::e($statusLabel) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                            <?php if ($status === 'aguardando_aprovacao'): ?>
                                <br><a class="link-small" href="/painel/licenciados/<?= (int) $l['id'] ?>/perfil" title="Confira os documentos com atenção antes de aprovar">⚠️ revisar documentos</a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($comm): ?>
                                R$ <?= number_format((float) $comm['total'], 2, ',', '.') ?>
                                <br><small class="hint-text">pago: R$ <?= number_format((float) $comm['total_pago'], 2, ',', '.') ?></small>
                            <?php else: ?>
                                R$ 0,00
                            <?php endif; ?>
                        </td>
                        <td><?= $vendas ? 'R$ ' . number_format((float) $vendas['total_value'], 2, ',', '.') : 'R$ 0,00' ?></td>
                        <td>
                            <select class="row-supervisor-select" data-id="<?= (int) $l['id'] ?>">
                                <option value="">— nenhum —</option>
                                <?php foreach ($supervisors as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($l['supervisor_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                        <?= View::e($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <a href="/painel/licenciados/<?= (int) $l['id'] ?>/perfil">Ver cadastro</a>
                            <?php if (!in_array($status, ['aguardando_perfil', 'nao_aplicavel'], true)): ?>
                                <br><a href="#" class="link-small row-resend-signature" data-id="<?= (int) $l['id'] ?>" title="Gera um contrato novo (com a assinatura automática do Roberson) e pede pra ele assinar de novo no próximo login">✍️ Forçar novo contrato</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$licenciados): ?>
                    <tr><td colspan="10">Nenhum licenciado encontrado com esses filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
(function () {
    var searchInput = document.getElementById('licenciado-search');
    var rows = Array.from(document.querySelectorAll('#licenciados-table tbody tr[data-search]'));
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var term = searchInput.value.trim().toLowerCase();
            rows.forEach(function (row) {
                row.hidden = term !== '' && row.getAttribute('data-search').indexOf(term) === -1;
            });
        });
    }

    var selectAll = document.getElementById('select-all-licenciados');
    var bar = document.getElementById('bulk-bar');
    var countEl = document.getElementById('bulk-count');

    function rowCheckboxes() {
        return Array.from(document.querySelectorAll('.row-select-licenciado'));
    }

    function updateBar() {
        var checked = rowCheckboxes().filter(function (c) { return c.checked; });
        countEl.textContent = checked.length;
        bar.style.display = checked.length > 0 ? 'flex' : 'none';
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes().forEach(function (c) { c.checked = selectAll.checked; });
            updateBar();
        });
    }
    rowCheckboxes().forEach(function (c) { c.addEventListener('change', updateBar); });

    // Troca individual de supervisor: AJAX direto (sem form aninhado, ja que o checkbox de
    // selecao em lote e o select individual moram dentro do mesmo <form> de bulk).
    var csrfToken = document.querySelector('#bulk-form input[name=csrf_token]').value;
    document.querySelectorAll('.row-supervisor-select').forEach(function (select) {
        select.addEventListener('change', function () {
            var id = select.getAttribute('data-id');
            var formData = new FormData();
            formData.set('csrf_token', csrfToken);
            formData.set('supervisor_id', select.value);
            fetch('/painel/licenciados/' + id + '/supervisor', { method: 'POST', body: formData })
                .then(function () { window.location.href = '/painel/licenciados?sucesso=1'; })
                .catch(function () { window.location.href = '/painel/licenciados?erro=1'; });
        });
    });

    // Forcar novo contrato: mesmo padrao AJAX acima (sem form aninhado dentro do bulk-form).
    document.querySelectorAll('.row-resend-signature').forEach(function (link) {
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (!confirm('Gerar um novo contrato (já com a assinatura automática do Roberson) e pedir pra esse licenciado assinar de novo no próximo login?')) {
                return;
            }
            var id = link.getAttribute('data-id');
            var formData = new FormData();
            formData.set('csrf_token', csrfToken);
            fetch('/painel/licenciados/' + id + '/reenviar-assinatura', { method: 'POST', body: formData })
                .then(function () { window.location.href = '/painel/licenciados?sucesso=1'; })
                .catch(function () { window.location.href = '/painel/licenciados?erro=1'; });
        });
    });
})();
</script>
