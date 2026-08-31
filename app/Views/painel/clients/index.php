<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
$values = $values ?? [];
$sellers = $sellers ?? [];
$openModal = isset($_GET['novo']) || $errors;
$canAssignSeller = in_array($user['role_slug'] ?? '', ['admin', 'gerente', 'supervisor'], true);
?>
<div class="page-header">
    <h1>Clientes</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-client">+ Novo cliente</button>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<form action="/painel/clientes/vincular-vendedor" method="post" id="bulk-seller-form">
    <?= Csrf::field() ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <?php if ($canAssignSeller): ?><th><input type="checkbox" id="select-all-clients"></th><?php endif; ?>
                    <th>Nome</th><th>Documento</th><th>Cidade/UF</th><th>WhatsApp</th><th>Vendedor</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <?php if ($canAssignSeller): ?>
                            <td><input type="checkbox" name="client_ids[]" value="<?= (int) $c['id'] ?>" class="client-checkbox"></td>
                        <?php endif; ?>
                        <td><a href="/painel/clientes/<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?></a></td>
                        <td><?= View::e($c['document'] ?: '—') ?></td>
                        <td><?= View::e(trim(($c['city'] ?: '') . ($c['state'] ? '/' . $c['state'] : '')) ?: '—') ?></td>
                        <td><?= View::e($c['whatsapp'] ?: '—') ?></td>
                        <td><?= View::e($c['seller_name'] ?: '—') ?></td>
                        <td><span class="status-badge status-<?= $c['status'] === 'ativo' ? 'active' : 'inactive' ?>"><?= $c['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
                        <td><a href="/painel/clientes/<?= (int) $c['id'] ?>/editar">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?>
                    <tr><td colspan="<?= $canAssignSeller ? 8 : 7 ?>">Nenhum cliente cadastrado ainda.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canAssignSeller): ?>
        <div class="filter-bar" style="margin-top:14px;">
            <label>Vincular selecionados a:
                <select name="seller_id">
                    <option value="">Sem vendedor</option>
                    <?php foreach ($sellers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= View::e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn-outline">Vincular a vendedor</button>
        </div>
    <?php endif; ?>
</form>

<script>
(function () {
    var selectAll = document.getElementById('select-all-clients');
    if (!selectAll) return;
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.client-checkbox').forEach(function (cb) { cb.checked = selectAll.checked; });
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
