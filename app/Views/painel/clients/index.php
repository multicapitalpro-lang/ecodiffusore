<?php
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\View;
$sucesso = $_GET['sucesso'] ?? null;
$erro = $_GET['erro'] ?? null;
$errors = $errors ?? [];
$values = $values ?? [];
$sellers = $sellers ?? [];
$filters = $filters ?? [];
$stats = $stats ?? ['total' => 0, 'pagos' => 0, 'abertos' => 0, 'nunca_compraram' => 0];
$openModal = isset($_GET['novo']) || $errors;
$canAssignSeller = in_array($user['role_slug'] ?? '', Roles::MANAGEMENT, true);
$showLicenciadoColumn = $showLicenciadoColumn ?? false;
$erroLabels = [
    'csrf' => 'Sessão expirada, tente novamente.',
    'vinculo' => 'Não é possível excluir: este cliente tem pedidos ou outros registros vinculados.',
];
?>
<div class="page-header">
    <h1>Clientes</h1>
    <div class="page-header-actions">
        <a href="/painel/clientes/exportar" class="btn btn-outline">Exportar CSV</a>
        <button type="button" class="btn btn-primary" data-modal-open="modal-client">+ Novo cliente</button>
    </div>
</div>

<?php if ($sucesso === '1'): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($sucesso === '2'): ?>
    <p class="form-msg form-msg-ok">Cliente excluído.</p>
<?php elseif ($sucesso === '3'): ?>
    <p class="form-msg form-msg-ok"><?= (int) ($_GET['deletados'] ?? 0) ?> cliente(s) excluído(s)<?php if ((int) ($_GET['falhas'] ?? 0) > 0): ?>, <?= (int) $_GET['falhas'] ?> não puderam ser excluídos (vínculos com pedidos).<?php endif; ?></p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro"><?= View::e($erroLabels[$erro] ?? 'Não foi possível concluir a ação.') ?></p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total de clientes</span>
        <strong><?= (int) $stats['total'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Já pagaram algum pedido</span>
        <strong><?= (int) $stats['pagos'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Com pedido em aberto</span>
        <strong><?= (int) $stats['abertos'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Nunca compraram</span>
        <strong><?= (int) $stats['nunca_compraram'] ?></strong>
    </div>
</div>

<form method="get" class="filter-bar">
    <input type="text" name="q" placeholder="Nome, e-mail ou documento" value="<?= View::e($filters['q'] ?? '') ?>">
    <select name="seller_id">
        <option value="">Todos os vendedores</option>
        <?php foreach ($sellers as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= (string) ($filters['seller_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">Todos os status</option>
        <option value="ativo" <?= ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
        <option value="inativo" <?= ($filters['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
    </select>
    <input type="text" name="city" placeholder="Cidade" value="<?= View::e($filters['city'] ?? '') ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
</form>

<form action="/painel/clientes/vincular-vendedor" method="post" id="bulk-seller-form">
    <?= Csrf::field() ?>
    <div id="bulk-actions-bar" style="display:none;margin-bottom:12px;gap:10px;align-items:center;">
        <label>Vincular selecionados a:
            <select name="seller_id">
                <option value="">Sem vendedor</option>
                <?php foreach ($sellers as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= View::e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-outline">Vincular a vendedor</button>
        <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;">
            <input type="checkbox" name="cascade" value="1">
            Também excluir pedidos/orçamentos vinculados
        </label>
        <button type="submit" formaction="/painel/clientes/excluir-lote" class="btn btn-danger" data-confirm="Excluir os clientes selecionados? Se a caixa 'também excluir pedidos/orçamentos vinculados' estiver marcada, isso também apaga pedidos/orçamentos/lançamentos financeiros deles. Essa ação não pode ser desfeita.">🗑 Excluir selecionados (<span id="bulk-count">0</span>)</button>
    </div>

    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-clients"></th>
                    <th>Nome</th><th>Documento</th><th>Cidade/UF</th><th>WhatsApp</th><th>Vendedor</th><?php if ($showLicenciadoColumn): ?><th>Licenciado</th><?php endif; ?><th>Pedidos</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <?php $purchase = $c['purchase_status'] ?? ['label' => '—', 'badge' => 'novo']; ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" class="row-select-client"></td>
                        <td><a href="/painel/clientes/<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?></a></td>
                        <td><?= View::e($c['document'] ?: '—') ?></td>
                        <td><?= View::e(trim(($c['city'] ?: '') . ($c['state'] ? '/' . $c['state'] : '')) ?: '—') ?></td>
                        <td>
                            <?php if (!empty($c['whatsapp'])): ?>
                                <?php $waMessage = 'Olá, ' . $c['name'] . '! Aqui é da Ecodiffusore Brasil.'; ?>
                                <a href="https://wa.me/55<?= preg_replace('/\D/', '', $c['whatsapp']) ?>?text=<?= rawurlencode($waMessage) ?>" target="_blank" rel="noopener" class="link-small">💬 <?= View::e($c['whatsapp']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= View::e($c['seller_name'] ?: '—') ?></td>
                        <?php if ($showLicenciadoColumn): ?><td><?= View::e($c['licenciado_name'] ?? '—') ?></td><?php endif; ?>
                        <td>
                            <span class="status-badge status-<?= View::e($purchase['badge']) ?>"><?= View::e($purchase['label']) ?></span>
                            <?php if ($c['order_count'] > 0): ?><br><small class="hint-text"><?= (int) $c['order_count'] ?> pedido(s)</small><?php endif; ?>
                        </td>
                        <td><span class="status-badge status-<?= $c['status'] === 'ativo' ? 'active' : 'inactive' ?>"><?= $c['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
                        <td class="table-actions">
                            <button type="button" class="link-button" data-edit-client="<?= (int) $c['id'] ?>">Editar</button>
                            <button type="button" class="icon-button-danger" title="Excluir" data-delete-client="<?= (int) $c['id'] ?>">🗑</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?>
                    <tr><td colspan="<?= $showLicenciadoColumn ? 10 : 9 ?>">Nenhum cliente encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('bulk-seller-form');
    var selectAll = document.getElementById('select-all-clients');
    var bar = document.getElementById('bulk-actions-bar');
    var countEl = document.getElementById('bulk-count');

    function rowCheckboxes() {
        return Array.prototype.slice.call(form.querySelectorAll('.row-select-client'));
    }

    function updateBar() {
        var checked = rowCheckboxes().filter(function (c) { return c.checked; });
        countEl.textContent = checked.length;
        bar.style.display = checked.length ? 'flex' : 'none';
    }

    selectAll.addEventListener('change', function () {
        rowCheckboxes().forEach(function (c) { c.checked = selectAll.checked; });
        updateBar();
    });
    rowCheckboxes().forEach(function (c) { c.addEventListener('change', updateBar); });

    form.addEventListener('submit', function (e) {
        var btn = e.submitter;
        if (btn && btn.dataset.confirm && !confirm(btn.dataset.confirm)) {
            e.preventDefault();
        }
    });
})();
</script>

<dialog class="modal" id="modal-client" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Novo cliente</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/clientes" method="post" class="panel-form ajax-form">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar cliente</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal" id="modal-client-edit">
    <div id="modal-client-edit-content">
        <div class="modal-header"><h2>Editar cliente</h2><button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>
        <div class="modal-body"><p class="hint-text">Carregando...</p></div>
    </div>
</dialog>

<dialog class="modal" id="modal-client-delete">
    <div id="modal-client-delete-content">
        <div class="modal-header"><h2>Excluir cliente</h2><button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>
        <div class="modal-body"><p class="hint-text">Carregando...</p></div>
    </div>
</dialog>
