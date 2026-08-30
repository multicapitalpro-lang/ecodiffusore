<?php
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
?>
<div class="page-header">
    <h1>Clientes</h1>
    <a href="/painel/clientes/novo" class="btn btn-primary">+ Novo cliente</a>
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
                    <td><?= View::e($c['name']) ?></td>
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
