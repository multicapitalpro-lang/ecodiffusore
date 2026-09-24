<?php
use App\Core\Csrf;
use App\Core\SubscriptionGate;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'conciliado' => 'Conciliado'];
$openModal = !empty($errors);
$filters = $filters ?? [];
$hasSub = SubscriptionGate::hasAccess($user);
?>
<div class="page-header">
    <h1>Caixas e Bancos</h1>
    <div class="page-header-actions">
        <?php if (count($accounts) > 1): ?>
            <button type="button" class="btn btn-outline" data-modal-open="<?= $hasSub ? 'modal-transfer' : 'modal-assinatura' ?>">Transferir entre contas</button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" data-modal-open="<?= $hasSub ? 'modal-transaction' : 'modal-assinatura' ?>">+ Incluir Lançamento</button>
    </div>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Verifique os dados informados.</p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($accounts as $acc): ?>
        <div class="dash-card">
            <span>
                <?= View::e($acc['name']) ?> (<?= $acc['type'] === 'caixa' ? 'Caixa' : 'Banco' ?>)
                <?php if (!empty($acc['is_default'])): ?>
                    <span class="tag-default">padrão</span>
                <?php endif; ?>
            </span>
            <strong class="<?= (float) $acc['balance'] < 0 ? 'text-red' : 'text-green' ?>"><?= SubscriptionGate::money($user, (float) $acc['balance']) ?></strong>
            <?php if ((float) $acc['initial_balance'] != 0): ?>
                <small class="hint-inline">Saldo inicial: R$ <?= number_format((float) $acc['initial_balance'], 2, ',', '.') ?> + movimentações</small>
            <?php endif; ?>
            <?php if (empty($acc['is_default'])): ?>
                <?php if ($hasSub): ?>
                    <form action="/painel/financeiro/caixas-bancos/<?= (int) $acc['id'] ?>/padrao" method="post" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="link-button">Tornar padrão</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="link-button" data-modal-open="modal-assinatura">Tornar padrão</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if (($acc['balance_source'] ?? 'manual') === 'asaas'): ?>
            <?php
            // Fase 118: card A MAIS (nunca substitui o de cima) -- saldo real puxado ao vivo da
            // API, pra comparar com o calculado localmente por financial_transactions. Se
            // divergirem, e' sinal de lancamento categorizado na conta errada (ja aconteceu, ver
            // changelog Fase 115) -- fica visualmente destacado quando isso acontece.
            $diff = $asaasApiBalance !== null ? round($asaasApiBalance - (float) $acc['balance'], 2) : null;
            ?>
            <?php
            // A cor do VALOR (verde/vermelho) reflete o SINAL do numero -- nao usar
            // dash-card-danger/warning aqui (elas recolorem o <strong> pro tom de alerta, o que
            // fazia um saldo POSITIVO aparecer em vermelho so' porque os cards divergiam,
            // confundindo "negativo" com "atencao"). A divergencia vira so' uma borda amarela
            // (inline, sem mexer na cor do valor) + o aviso no texto pequeno abaixo.
            $diverge = $diff !== null && abs($diff) > 0.01;
            ?>
            <div class="dash-card" <?= $diverge ? 'style="border-top-color:#d69a1e"' : '' ?>>
                <span>ASAAS API <span class="tag-default" title="Saldo puxado ao vivo da API do Asaas, não do livro-razão local">ao vivo</span></span>
                <?php if ($asaasApiBalance === null): ?>
                    <strong>Indisponível</strong>
                    <small class="hint-inline">Não foi possível consultar a Asaas agora.</small>
                <?php else: ?>
                    <strong class="<?= $asaasApiBalance < 0 ? 'text-red' : 'text-green' ?>"><?= SubscriptionGate::money($user, $asaasApiBalance) ?></strong>
                    <?php if ($diverge): ?>
                        <small class="hint-inline" style="color:#b3790f;">⚠ Diverge do card "ASAAS" em R$ <?= number_format(abs($diff), 2, ',', '.') ?> — confira se algum lançamento foi categorizado na conta errada.</small>
                    <?php else: ?>
                        <small class="hint-inline">✔ Bate com o card "ASAAS" acima.</small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<p class="hint-text">A conta padrão é pra onde vão automaticamente o recebimento de um pedido pago e a saída de uma comissão dada baixa.</p>

<?php if ($hasSub): ?>
    <details class="inline-details">
        <summary>+ Nova conta financeira</summary>
        <form action="/painel/financeiro/caixas-bancos/contas" method="post" class="panel-form">
            <?= Csrf::field() ?>
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required>
            <label for="type">Tipo</label>
            <select id="type" name="type">
                <option value="caixa">Caixa</option>
                <option value="banco">Banco</option>
            </select>
            <label for="initial_balance">Saldo inicial / aporte (R$)</label>
            <input type="number" step="0.01" id="initial_balance" name="initial_balance" value="0">
            <p class="hint-text" style="margin-top:-8px;">Valor que já existe nessa conta antes de começar a lançar movimentações aqui — soma direto no saldo mostrado no card, mesmo sem nenhum lançamento.</p>
            <label class="checkbox-inline"><input type="checkbox" name="is_default" value="1"> Tornar essa a conta padrão</label>
            <button type="submit" class="btn btn-primary">Adicionar conta</button>
        </form>
    </details>
<?php else: ?>
    <button type="button" class="btn btn-outline" data-modal-open="modal-assinatura">+ Nova conta financeira</button>
<?php endif; ?>

<h3 class="section-title">Movimentações</h3>
<form method="get" class="filter-bar">
    <input type="date" name="from" value="<?= View::e($filters['from'] ?? '') ?>">
    <input type="date" name="to" value="<?= View::e($filters['to'] ?? '') ?>">
    <select name="account_id">
        <option value="">Todas as contas</option>
        <?php foreach ($accounts as $acc): ?>
            <option value="<?= (int) $acc['id'] ?>" <?= (string) ($filters['account_id'] ?? '') === (string) $acc['id'] ? 'selected' : '' ?>><?= View::e($acc['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="category_id">
        <option value="">Todas as categorias</option>
        <?php foreach ($categoryGroups as $group): ?>
            <optgroup label="<?= View::e($group['parent']['name']) ?>">
                <?php foreach ($group['children'] as $child): ?>
                    <option value="<?= (int) $child['id'] ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $child['id'] ? 'selected' : '' ?>><?= View::e($child['name']) ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <select name="client_id">
        <option value="">Todos os clientes/fornecedores</option>
        <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Filtrar</button>
    <?php if ($filters): ?><a href="/painel/financeiro/caixas-bancos" class="link-small">Limpar filtros</a><?php endif; ?>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Data</th><th>Categoria</th><th>Histórico</th><th>Cliente/Fornecedor</th><th>Conta</th><th>Valor</th><th>Situação</th><th>Anexos</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['category_name'] ?: '—') ?></td>
                    <td>
                        <?= View::e($t['description'] ?: '—') ?>
                        <?php if (!empty($t['is_transfer'])): ?><span class="tag-default">transferência</span><?php endif; ?>
                        <?php if (!empty($t['recurrence_frequency'])): ?><span class="tag-default" title="Lançamento recorrente">🔁</span><?php endif; ?>
                        <?php if (!empty($t['order_id'])): ?>
                            <br><button type="button" class="link-small" data-view-order="<?= (int) $t['order_id'] ?>">Ver pedido #<?= (int) $t['order_id'] ?></button>
                        <?php endif; ?>
                    </td>
                    <td><?= View::e($t['client_name'] ?: '—') ?></td>
                    <td><?= View::e($t['account_name']) ?></td>
                    <td class="<?= $t['type'] === 'entrada' ? 'text-green' : 'text-red' ?>">
                        <?= $t['type'] === 'entrada' ? '+' : '-' ?> <?= SubscriptionGate::money($user, (float) $t['amount']) ?>
                    </td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                    <td><?php $items = $attachmentsByTransaction[$t['id']] ?? []; include __DIR__ . '/_attachments_cell.php'; ?></td>
                    <td class="table-actions">
                        <?php if (empty($t['is_transfer'])): ?>
                            <?php if ($hasSub): ?>
                                <button type="button" class="link-small" data-edit-transaction="<?= (int) $t['id'] ?>">Editar</button>
                            <?php else: ?>
                                <button type="button" class="link-small" data-modal-open="modal-assinatura">Editar</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (empty($t['order_id'])): ?>
                            <?php if ($hasSub): ?>
                                <form action="/painel/financeiro/contas/<?= (int) $t['id'] ?>/excluir" method="post" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button icon-button-danger" data-confirm="<?= !empty($t['is_transfer']) ? 'Excluir essa transferência? As duas pernas (origem e destino) serão removidas juntas.' : 'Excluir esse lançamento? Essa ação não pode ser desfeita.' ?>">Excluir</button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="link-button icon-button-danger" data-modal-open="modal-assinatura">Excluir</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="9">Nenhuma movimentação encontrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<dialog class="modal modal-drawer" id="modal-transaction" <?= $openModal ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>Lançamento caixa</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/financeiro/lancamentos" method="post" class="panel-form ajax-form" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <?php include __DIR__ . '/_transaction_fields.php'; ?>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<dialog class="modal" id="modal-transfer">
    <div class="modal-header">
        <h2>Transferir entre contas</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/financeiro/transferencia" method="post" class="panel-form">
            <?= Csrf::field() ?>
            <label for="transfer-from">Conta de origem</label>
            <select id="transfer-from" name="from_account_id" required>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>"><?= View::e($acc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="transfer-to">Conta de destino</label>
            <select id="transfer-to" name="to_account_id" required>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>"><?= View::e($acc['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="transfer-amount">Valor (R$)</label>
            <input type="number" step="0.01" id="transfer-amount" name="amount" required>
            <label for="transfer-date">Data</label>
            <input type="date" id="transfer-date" name="date" value="<?= date('Y-m-d') ?>" required>
            <label for="transfer-desc">Observação (opcional)</label>
            <input type="text" id="transfer-desc" name="description">
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Transferir</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>

<?php include __DIR__ . '/_edit_transaction_modal.php'; ?>

<?php $redirectTo = '/painel/financeiro/caixas-bancos'; include __DIR__ . '/../_client_quick_modal.php'; ?>

<?php if (!$hasSub): ?><?php include __DIR__ . '/../subscription/_modal.php'; ?><?php endif; ?>
