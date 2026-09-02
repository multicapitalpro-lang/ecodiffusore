<?php
use App\Core\View;
$sucesso = $_GET['sucesso'] ?? null;
$temp = $_GET['temp'] ?? null;
$onboardingLabels = [
    'aguardando_perfil' => ['Aguardando perfil', 'novo'],
    'aguardando_assinatura' => ['Aguardando assinatura', 'novo'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'novo'],
    'ativo' => ['Contrato assinado', 'active'],
    'assinatura_recusada' => ['Assinatura recusada', 'inactive'],
    'kyc_recusado' => ['KYC recusado', 'inactive'],
];
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
            <tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Status</th><th>Onboarding</th><th>Último login</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= View::e($u['name']) ?></td>
                    <td><?= View::e($u['email']) ?></td>
                    <td><?= View::e($u['role_name']) ?></td>
                    <td><span class="status-badge status-<?= View::e($u['status']) ?>"><?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <?php if (($u['licenciado_onboarding_status'] ?? 'nao_aplicavel') !== 'nao_aplicavel'): ?>
                            <?php [$label, $badge] = $onboardingLabels[$u['licenciado_onboarding_status']] ?? [$u['licenciado_onboarding_status'], 'novo']; ?>
                            <span class="status-badge status-<?= View::e($badge) ?>"><?= View::e($label) ?></span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $u['last_login_at'] ? View::e($u['last_login_at']) : '—' ?></td>
                    <td><a href="/painel/usuarios/<?= (int) $u['id'] ?>/editar">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?>
                <tr><td colspan="7">Nenhum usuário cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
