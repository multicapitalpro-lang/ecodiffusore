<?php
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\View;
$statusLabels = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'convertido' => 'Convertido'];
$errors = $errors ?? [];
$values = $values ?? [];
$items = $items ?? [];
$stats = $stats ?? ['total' => 0, 'pendentes' => 0, 'pagos' => 0, 'cancelados' => 0];
$filters = $filters ?? [];
$licenciados = $licenciados ?? [];
$showLicenciadoColumn = $showLicenciadoColumn ?? false;
$isVendedor = ($user['role_slug'] ?? '') === 'vendedor';
$isViewOnly = in_array($user['role_slug'] ?? '', Roles::NATIONAL_SUPPORT, true);
$preselectClientId = (int) ($_GET['cliente_id'] ?? 0);
$openModal = (isset($_GET['novo']) || $errors) && !$isViewOnly;
$csrfToken = Csrf::token();
?>
<div class="page-header">
    <h1>Orçamentos</h1>
    <div class="page-header-actions">
        <a href="/painel/orcamentos/kanban" class="btn btn-outline">Ver como Kanban</a>
        <?php if (!$isViewOnly): ?>
            <button type="button" class="btn btn-primary" data-modal-open="modal-quote">+ Gerar Orçamento</button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['excluido'])): ?>
    <p class="form-msg form-msg-ok">Orçamento excluído.</p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total de orçamentos</span>
        <strong><?= (int) $stats['total'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Pagos</span>
        <strong><?= (int) $stats['pagos'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Pendentes de pagamento</span>
        <strong><?= (int) $stats['pendentes'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Cancelados</span>
        <strong><?= (int) $stats['cancelados'] ?></strong>
    </div>
</div>

<form method="get" class="filter-bar">
    <select name="status">
        <option value="">Todas as situações</option>
        <?php foreach ($statusLabels as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($licenciados): ?>
        <select name="licenciado_id">
            <option value="">Todos os licenciados</option>
            <?php foreach ($licenciados as $l): ?>
                <option value="<?= (int) $l['id'] ?>" <?= (string) ($filters['licenciado_id'] ?? '') === (string) $l['id'] ? 'selected' : '' ?>><?= View::e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if (!$isVendedor): ?>
        <select name="seller_id">
            <option value="">Todos os vendedores</option>
            <?php foreach ($sellers as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['seller_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <input type="text" name="city" placeholder="Cidade" value="<?= View::e($filters['city'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Cliente</th><th>Telefone</th><th>Origem</th><th>Vendedor</th><?php if ($showLicenciadoColumn): ?><th>Licenciado</th><?php endif; ?><th>Cidade</th><th>Gerado em</th><th>Válido até</th><th>Total</th><th>Pagamento</th><th>Situação</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($quotes as $q): ?>
                <?php $situation = $q['payment_situation'] ?? ['label' => '—', 'badge' => 'novo']; ?>
                <tr>
                    <td><button type="button" class="link-button" data-view-quote="<?= (int) $q['id'] ?>">#<?= (int) $q['id'] ?></button></td>
                    <td><?= View::e($q['client_name']) ?></td>
                    <td>
                        <?php $phone = $q['client_whatsapp'] ?: $q['lead_whatsapp']; ?>
                        <?php if ($phone): ?>
                            <a href="https://wa.me/55<?= preg_replace('/\D/', '', $phone) ?>" target="_blank" rel="noopener" class="link-small">💬 <?= View::e($phone) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= !empty($q['lead_id']) ? '🌐 Site' : 'Interno' ?></td>
                    <td><?= View::e($q['seller_name'] ?: '—') ?></td>
                    <?php if ($showLicenciadoColumn): ?><td><?= View::e($q['licenciado_name'] ?? '—') ?></td><?php endif; ?>
                    <td><?= View::e($q['client_city'] ?: $q['lead_city'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($q['created_at']))) ?></td>
                    <td><?= $q['valid_until'] ? View::e(date('d/m/Y', strtotime($q['valid_until']))) : '—' ?></td>
                    <td>R$ <?= number_format((float) $q['total_value'], 2, ',', '.') ?></td>
                    <td><?= View::e($q['payment_method'] ?: '—') ?></td>
                    <td>
                        <?php if (!$isViewOnly && $q['status'] !== 'convertido'): ?>
                            <select class="quote-status-select" data-quote-id="<?= (int) $q['id'] ?>">
                                <?php foreach (['aberto', 'aprovado', 'recusado'] as $key): ?>
                                    <option value="<?= $key ?>" <?= $q['status'] === $key ? 'selected' : '' ?>><?= $statusLabels[$key] ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <span class="status-badge status-<?= $q['status'] === 'convertido' || $q['status'] === 'aprovado' ? 'active' : ($q['status'] === 'recusado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$q['status']] ?? $q['status'] ?></span>
                        <?php endif; ?>
                        <br><small class="hint-text">Pagamento: <?= View::e($situation['label']) ?></small>
                    </td>
                    <td>
                        <button type="button" class="link-button" data-view-quote="<?= (int) $q['id'] ?>">Ver</button>
                        <?php if ($q['status'] === 'aberto' && !$isViewOnly): ?>
                            · <button type="button" class="link-button" data-edit-quote="<?= (int) $q['id'] ?>">Editar</button>
                        <?php endif; ?>
                        <?php if (($user['role_slug'] ?? '') === 'admin' && $q['status'] !== 'convertido'): ?>
                            · <form action="/painel/orcamentos/<?= (int) $q['id'] ?>/excluir" method="post" style="display:inline;">
                                <?= Csrf::field() ?>
                                <button type="submit" class="link-button" style="color:#c53030;" data-confirm="Excluir o orçamento #<?= (int) $q['id'] ?> definitivamente? Essa ação não pode ser desfeita.">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$quotes): ?>
                <tr><td colspan="<?= $showLicenciadoColumn ? 13 : 12 ?>">Nenhum orçamento encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<dialog class="modal" id="modal-quote" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Gerar Orçamento</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/orcamentos" method="post" class="panel-form panel-form-wide ajax-form" id="order-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar Orçamento</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal" id="modal-quote-detail">
    <div id="modal-quote-detail-content">
        <div class="modal-header"><h2>Orçamento</h2><button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>
        <div class="modal-body"><p class="hint-text">Carregando...</p></div>
    </div>
</dialog>

<?php $redirectTo = '/painel/orcamentos'; include __DIR__ . '/../_client_quick_modal.php'; ?>

<script>
(function () {
    var csrfToken = <?= json_encode($csrfToken) ?>;
    document.querySelectorAll('.quote-status-select').forEach(function (select) {
        select.addEventListener('change', function () {
            var formData = new FormData();
            formData.set('csrf_token', csrfToken);
            formData.set('status', select.value);
            fetch('/painel/orcamentos/' + select.dataset.quoteId + '/status', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function () { window.location.reload(); });
        });
    });
})();
</script>
