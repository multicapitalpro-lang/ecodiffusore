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
$vendorContractLabels = [
    'pendente_envio' => ['Contrato pendente de envio', 'aguardando-perfil'],
    'aguardando_aprovacao' => ['Contrato aguardando aprovação', 'aguardando-assinatura'],
    'aprovado' => ['Contrato aprovado', 'active'],
    'reprovado' => ['Contrato reprovado', 'recusado'],
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

<?php if (($_GET['aviso'] ?? '') === 'email_licenciado_pendente'): ?>
    <p class="form-msg form-msg-erro">⚠️ O e-mail foi alterado, mas o contrato desse Licenciado no ClickSign ainda está com o e-mail ANTIGO (o token de assinatura continuaria indo pro endereço errado). Vá em <a href="/painel/licenciados/aprovacoes">Aprovação de Cadastros</a> e clique em "✍️ Pedir nova assinatura" pra gerar um envelope novo com o e-mail corrigido.</p>
<?php endif; ?>

<?php
// Cards clicaveis -- cada um filtra a tabela abaixo por papel (pedido explicito do usuario: clicar
// em "Licenciados" so mostra Licenciados, "Todos" limpa o filtro, etc), preservando os demais
// filtros ja aplicados (busca/status/onboarding/cidade/UF).
$cardLink = function (string $roleValue) use ($filters): string {
    $qs = array_merge($filters, ['role' => $roleValue]);
    return '/painel/usuarios?' . http_build_query(array_filter($qs, fn ($v) => $v !== ''));
};
$cardActive = fn (string $roleValue) => $filters['role'] === $roleValue ? ' is-active' : '';
?>
<div class="cards-grid">
    <a class="dash-card dash-card-link<?= $cardActive('') ?>" href="<?= $cardLink('') ?>">
        <span>Todos</span>
        <strong><?= (int) $stats['total'] ?></strong>
    </a>
    <a class="dash-card dash-card-link<?= $cardActive('licenciado') ?>" href="<?= $cardLink('licenciado') ?>">
        <span>Licenciados</span>
        <strong><?= (int) $stats['licenciado'] ?></strong>
    </a>
    <a class="dash-card dash-card-link<?= $cardActive('gestor') ?>" href="<?= $cardLink('gestor') ?>">
        <span>Gestores</span>
        <strong><?= (int) $stats['gestor'] ?></strong>
    </a>
    <a class="dash-card dash-card-link<?= $cardActive('vendedor') ?>" href="<?= $cardLink('vendedor') ?>">
        <span>Vendedores</span>
        <strong><?= (int) $stats['vendedor'] ?></strong>
    </a>
    <a class="dash-card dash-card-link<?= $cardActive('cliente') ?>" href="<?= $cardLink('cliente') ?>">
        <span>Total Clientes</span>
        <strong><?= (int) $stats['cliente'] ?></strong>
    </a>
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
                <?php
                // Fase 79: organograma em pirâmide (sanfona) -- Licenciado expande mostrando
                // Gestores/Vendedores da rede (2 níveis: diretos + vendedores de cada Gestor);
                // Vendedor (direto na tabela ou dentro da sanfona de um Licenciado) expande
                // mostrando os próprios Clientes, carregados sob demanda via fetch().
                $renderSellerAccordionRow = function (array $seller, int $depth) {
                    ?>
                    <tr class="accordion-row" data-accordion-for="seller-<?= (int) $seller['id'] ?>" hidden>
                        <td colspan="12">
                            <div class="accordion-content" data-clients-for="<?= (int) $seller['id'] ?>" data-loaded="0" style="margin-left:<?= $depth * 20 ?>px">
                                <p class="hint-text">Carregando clientes...</p>
                            </div>
                        </td>
                    </tr>
                    <?php
                };

                $renderLicenciadoAccordionRow = function (array $licenciado) use ($byManager, $renderSellerAccordionRow) {
                    $directChildren = $byManager[(int) $licenciado['id']] ?? [];
                    ?>
                    <tr class="accordion-row" data-accordion-for="licenciado-<?= (int) $licenciado['id'] ?>" hidden>
                        <td colspan="12">
                            <div class="accordion-content">
                                <?php if (!$directChildren): ?>
                                    <p class="hint-text">Nenhum Gestor ou Vendedor cadastrado nessa rede ainda.</p>
                                <?php else: ?>
                                    <table class="data-table data-table-compact">
                                        <thead><tr><th>Nome</th><th>Papel</th><th>Cidade/UF</th><th></th></tr></thead>
                                        <tbody>
                                            <?php foreach ($directChildren as $child): ?>
                                                <tr>
                                                    <td class="text-standardized"><?= View::e($child['name']) ?></td>
                                                    <td><span class="role-badge role-<?= View::e($child['role_slug']) ?>"><?= View::e($child['role_name']) ?></span></td>
                                                    <td><?= $child['city'] ? View::e($child['city']) . ($child['state'] ? '/' . View::e($child['state']) : '') : '—' ?></td>
                                                    <td>
                                                        <?php if ($child['role_slug'] === 'vendedor'): ?>
                                                            <button type="button" class="accordion-toggle" data-toggle-clients="<?= (int) $child['id'] ?>"><span class="accordion-toggle-icon"></span> Ver clientes</button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php if ($child['role_slug'] === 'vendedor'): $renderSellerAccordionRow($child, 1); ?>
                                                <?php elseif ($child['role_slug'] === 'gestor'): ?>
                                                    <?php foreach (($byManager[(int) $child['id']] ?? []) as $grandchild): ?>
                                                        <?php if ($grandchild['role_slug'] !== 'vendedor') continue; ?>
                                                        <tr>
                                                            <td class="text-standardized" style="padding-left:24px">↳ <?= View::e($grandchild['name']) ?></td>
                                                            <td><span class="role-badge role-vendedor">Vendedor</span></td>
                                                            <td><?= $grandchild['city'] ? View::e($grandchild['city']) . ($grandchild['state'] ? '/' . View::e($grandchild['state']) : '') : '—' ?></td>
                                                            <td><button type="button" class="accordion-toggle" data-toggle-clients="<?= (int) $grandchild['id'] ?>"><span class="accordion-toggle-icon"></span> Ver clientes</button></td>
                                                        </tr>
                                                        <?php $renderSellerAccordionRow($grandchild, 2); ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                };
                ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" class="row-select-user" <?= $u['role_slug'] === 'admin' ? 'disabled' : '' ?>></td>
                        <td class="text-standardized">
                            <?php if ($u['role_slug'] === 'licenciado'): ?>
                                <button type="button" class="accordion-toggle" data-toggle-licenciado="<?= (int) $u['id'] ?>" title="Ver rede deste Licenciado"><span class="accordion-toggle-icon"></span></button>
                            <?php elseif ($u['role_slug'] === 'vendedor'): ?>
                                <button type="button" class="accordion-toggle" data-toggle-clients="<?= (int) $u['id'] ?>" title="Ver clientes deste Vendedor"><span class="accordion-toggle-icon"></span></button>
                            <?php endif; ?>
                            <?= View::e($u['name']) ?>
                        </td>
                        <td><?= View::e($u['email']) ?></td>
                        <td><span class="role-badge role-<?= View::e($u['role_slug']) ?>"><?= View::e($u['role_name']) ?></span></td>
                        <td><span class="status-badge status-<?= View::e($u['status']) ?>"><?= $u['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></td>
                        <td>
                            <?php if (($u['licenciado_onboarding_status'] ?? 'nao_aplicavel') !== 'nao_aplicavel'): ?>
                                <?php [$label, $badge] = $onboardingLabels[$u['licenciado_onboarding_status']] ?? [$u['licenciado_onboarding_status'], 'novo']; ?>
                                <span class="status-badge status-<?= View::e($badge) ?>"><?= View::e($label) ?></span>
                            <?php elseif (($u['vendedor_contract_status'] ?? 'nao_aplicavel') !== 'nao_aplicavel'): ?>
                                <?php [$label, $badge] = $vendorContractLabels[$u['vendedor_contract_status']] ?? [$u['vendedor_contract_status'], 'novo']; ?>
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
                    <?php if ($u['role_slug'] === 'licenciado'): $renderLicenciadoAccordionRow($u); ?>
                    <?php elseif ($u['role_slug'] === 'vendedor'): $renderSellerAccordionRow($u, 1); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                    <tr><td colspan="12">Nenhum usuário encontrado com esses filtros.</td></tr>
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

    // Fase 79: organograma em pirâmide -- clicar num Licenciado expande a rede (Gestores/
    // Vendedores); clicar num Vendedor expande os Clientes dele, carregados sob demanda.
    var table = document.querySelector('.table-scroll table');
    if (table) {
        table.addEventListener('click', function (e) {
            var licBtn = e.target.closest('[data-toggle-licenciado]');
            if (licBtn) {
                toggleAccordion('licenciado-' + licBtn.dataset.toggleLicenciado, licBtn);
                return;
            }
            var clBtn = e.target.closest('[data-toggle-clients]');
            if (clBtn) {
                var sellerId = clBtn.dataset.toggleClients;
                var row = toggleAccordion('seller-' + sellerId, clBtn);
                if (row) {
                    var box = row.querySelector('[data-clients-for="' + sellerId + '"]');
                    if (box && box.dataset.loaded === '0' && !row.hidden) {
                        box.dataset.loaded = '1';
                        fetch('/painel/usuarios/' + sellerId + '/clientes')
                            .then(function (r) { return r.text(); })
                            .then(function (html) { box.innerHTML = html; })
                            .catch(function () { box.innerHTML = '<p class="hint-text">Erro ao carregar clientes.</p>'; });
                    }
                }
            }
        });
    }

    function toggleAccordion(key, btn) {
        var row = table.querySelector('[data-accordion-for="' + key + '"]');
        if (!row) return null;
        row.hidden = !row.hidden;
        btn.classList.toggle('is-open', !row.hidden);
        return row;
    }
})();
</script>
