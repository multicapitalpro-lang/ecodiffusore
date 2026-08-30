<?php
use App\Core\View;
$sucesso = $_GET['sucesso'] ?? null;
$temp = $_GET['temp'] ?? null;
?>
<div class="page-header">
    <h1>Usuários</h1>
    <a href="/painel/usuarios/novo" class="btn btn-primary">+ Novo usuário</a>
</div>

<?php if ($sucesso === '1'): ?>
    <p class="form-msg form-msg-ok">Usuário salvo com sucesso.</p>
<?php elseif ($sucesso === '2' && $temp): ?>
    <p class="form-msg form-msg-ok">Senha redefinida. Senha temporária: <strong><?= View::e($temp) ?></strong> (o usuário deverá trocá-la no próximo login).</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Status</th><th>Último login</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= View::e($u['name']) ?></td>
                    <td><?= View::e($u['email']) ?></td>
                    <td><?= View::e($u['role_name']) ?></td>
                    <td><span class="status-badge status-<?= View::e($u['status']) ?>"><?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                    <td><?= $u['last_login_at'] ? View::e($u['last_login_at']) : '—' ?></td>
                    <td><a href="/painel/usuarios/<?= (int) $u['id'] ?>/editar">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="6">Nenhum usuário cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
