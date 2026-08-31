<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
$values = $values ?? [];
$openModal = isset($_GET['novo']) || $errors;
?>
<div class="page-header">
    <h1>Clientes</h1>
    <button type="button" class="btn btn-primary" data-modal-open="modal-client">+ Novo cliente</button>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Cliente salvo com sucesso.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>Nome</th><th>Documento</th><th>Cidade/UF</th><th>WhatsApp</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><a href="/painel/clientes/<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?></a></td>
                    <td><?= View::e($c['document'] ?: '—') ?></td>
                    <td><?= View::e(trim(($c['city'] ?: '') . ($c['state'] ? '/' . $c['state'] : '')) ?: '—') ?></td>
                    <td><?= View::e($c['whatsapp'] ?: '—') ?></td>
                    <td><span class="status-badge status-<?= $c['status'] === 'ativo' ? 'active' : 'inactive' ?>"><?= $c['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><a href="/painel/clientes/<?= (int) $c['id'] ?>/editar">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clients): ?>
                <tr><td colspan="6">Nenhum cliente cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

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
