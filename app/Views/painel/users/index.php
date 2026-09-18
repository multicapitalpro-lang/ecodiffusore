<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = $_GET['sucesso'] ?? null;
$erro = $_GET['erro'] ?? null;
$temp = $_GET['temp'] ?? null;
$onboardingLabels = [
    'aguardando_perfil' => ['Aguardando perfil', 'aguardando-perfil'],
    'aguardando_assinatura' => ['Aguardando assinatura', 'aguardando-assinatura'],
    'aguardando_aprovacao' => ['Aguardando aprovação', 'aguardando-aprovacao'],
    'ativo' => ['Contrato assinado', 'active'],
    'assinatura_recusada' => ['Assinatura recusada', 'recusado'],
    'kyc_recusado' => ['KYC recusado', 'recusado'],
];
$erroLabels = [
    'csrf' => 'Sessão expirada, tente novamente.',
    'self' => 'Você não pode excluir seu próprio usuário.',
    'admin' => 'Não é possível excluir uma conta de Administrador por aqui.',
    'vinculo' => 'Não é possível excluir: este usuário tem pedidos, comissões ou outros registros vinculados.',
    'naoencontrado' => 'Usuário não encontrado.',
];
?>
<div class="page-header">
    <h1>Usuários</h1>
    <button type="button" class="btn btn-primary" id="btn-new-user">+ Novo usuário</button>
</div>

<?php if ($sucesso === '1'): ?>
    <p class="form-msg form-msg-ok">Usuário salvo com sucesso.</p>
<?php elseif ($sucesso === '2' && $temp): ?>
    <p class="form-msg form-msg-ok">Senha redefinida. Senha temporária: <strong><?= View::e($temp) ?></strong> (o usuário deverá trocá-la no próximo login).</p>
<?php elseif ($sucesso === '3'): ?>
    <p class="form-msg form-msg-ok">Usuário excluído.</p>
<?php elseif ($sucesso === '4'): ?>
    <p class="form-msg form-msg-ok"><?= (int) ($_GET['deletados'] ?? 0) ?> usuário(s) excluído(s)<?php if ((int) ($_GET['falhas'] ?? 0) > 0): ?>, <?= (int) $_GET['falhas'] ?> não puderam ser excluídos (vínculos, admin ou fora do seu escopo)<?php endif; ?>.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro"><?= View::e($erroLabels[$erro] ?? 'Não foi possível concluir a ação.') ?></p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total Clientes</span>
        <strong><?= (int) $stats['cliente'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Licenciados</span>
        <strong><?= (int) $stats['licenciado'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Gestores</span>
        <strong><?= (int) $stats['gestor'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Vendedores</span>
        <strong><?= (int) $stats['vendedor'] ?></strong>
    </div>
</div>

<form method="get" class="filter-bar">
    <input type="text" name="q" placeholder="Nome ou e-mail" value="<?= View::e($filters['q']) ?>">
    <select name="role">
        <option value="">Todos os papéis</option>
        <?php foreach ($roles as $role): ?>
            <option value="<?= View::e($role['slug']) ?>" <?= $filters['role'] === $role['slug'] ? 'selected' : '' ?>><?= View::e($role['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">Todos os status</option>
        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
    </select>
    <select name="onboarding">
        <option value="">Onboarding (todos)</option>
        <?php foreach ($onboardingLabels as $key => [$label, ]): ?>
            <option value="<?= $key ?>" <?= $filters['onboarding'] === $key ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="city" placeholder="Cidade" value="<?= View::e($filters['city']) ?>">
    <input type="text" name="state" placeholder="UF" maxlength="2" style="width:60px;text-transform:uppercase" value="<?= View::e($filters['state']) ?>">
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php if (array_filter($filters)): ?>
        <a href="/painel/usuarios" class="link-small">Limpar filtros</a>
    <?php endif; ?>
</form>

<form method="post" action="/painel/usuarios/excluir-lote" id="bulk-delete-form">
    <?= Csrf::field() ?>
    <div id="bulk-delete-bar" style="display:none;margin-bottom:12px;">
        <button type="submit" class="btn btn-danger" data-confirm="Excluir os usuários selecionados? Essa ação não pode ser desfeita.">🗑 Excluir selecionados (<span id="bulk-delete-count">0</span>)</button>
    </div>

    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all-users"></th>
                    <th>Nome</th><th>E-mail</th><th>Papel</th><th>Status</th><th>Onboarding</th><th>Aceite de Comissão</th><th>Cidade/UF</th><th>Responsável</th><th>Cadastrado em</th><th>Último login</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" class="row-select-user" <?= $u['role_slug'] === 'admin' ? 'disabled' : '' ?>></td>
                        <td class="text-standardized"><?= View::e($u['name']) ?></td>
                        <td><?= View::e($u['email']) ?></td>
                        <td><span class="role-badge role-<?= View::e($u['role_slug']) ?>"><?= View::e($u['role_name']) ?></span></td>
                        <td><span class="status-badge status-<?= View::e($u['status']) ?>"><?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                        <td>
                            <?php if (($u['licenciado_onboarding_status'] ?? 'nao_aplicavel') !== 'nao_aplicavel'): ?>
                                <?php [$label, $badge] = $onboardingLabels[$u['licenciado_onboarding_status']] ?? [$u['licenciado_onboarding_status'], 'novo']; ?>
                                <span class="status-badge status-<?= View::e($badge) ?>"><?= View::e($label) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($u['role_slug'], ['gestor', 'vendedor'], true)): ?>
                                <?php if (!empty($u['commission_accepted_at'])): ?>
                                    <span class="status-badge status-active">Aceito em <?= View::e(date('d/m/Y', strtotime($u['commission_accepted_at']))) ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-contatado">Aguardando aceite</span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-standardized"><?= $u['city'] ? View::e($u['city']) . ($u['state'] ? '/' . View::e($u['state']) : '') : '—' ?></td>
                        <td>
                            <?php if ($u['responsavel']): ?>
                                <?= View::e($u['responsavel']['name']) ?>
                                <?php $respFone = preg_replace('/\D/', '', (string) ($u['responsavel']['whatsapp'] ?? '')); ?>
                                <?php if ($respFone !== ''): ?>
                                    <a href="https://wa.me/55<?= $respFone ?>" target="_blank" rel="noopener" title="Falar no WhatsApp com <?= View::e($u['responsavel']['name']) ?>">💬</a>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= $u['created_at'] ? View::e(date('d/m/Y', strtotime($u['created_at']))) : '—' ?></td>
                        <td><?= $u['last_login_at'] ? View::e($u['last_login_at']) : '—' ?></td>
                        <td class="table-actions">
                            <button type="button" class="link-button" data-edit-user="<?= (int) $u['id'] ?>">Editar</button>
                            <?php if ($u['role_slug'] !== 'admin'): ?>
                                <button type="submit" formaction="/painel/usuarios/<?= (int) $u['id'] ?>/excluir" formnovalidate class="icon-button-danger" title="Excluir" data-confirm="Excluir o usuário <?= View::e($u['name']) ?>? Essa ação não pode ser desfeita.">🗑</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                    <tr><td colspan="10">Nenhum usuário encontrado com esses filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<dialog class="modal" id="modal-user-edit">
    <div id="modal-user-edit-content">
        <div class="modal-header"><h2>Editar usuário</h2><button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>
        <div class="modal-body"><p class="hint-text">Carregando...</p></div>
    </div>
</dialog>

<script>
(function () {
    var form = document.getElementById('bulk-delete-form');
    var selectAll = document.getElementById('select-all-users');
    var bar = document.getElementById('bulk-delete-bar');
    var countEl = document.getElementById('bulk-delete-count');

    function rowCheckboxes() {
        return Array.prototype.slice.call(form.querySelectorAll('.row-select-user'));
    }

    function updateBar() {
        var checked = rowCheckboxes().filter(function (c) { return c.checked; });
        countEl.textContent = checked.length;
        bar.style.display = checked.length ? '' : 'none';
    }

    selectAll.addEventListener('change', function () {
        rowCheckboxes().forEach(function (c) { if (!c.disabled) c.checked = selectAll.checked; });
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
