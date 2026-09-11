<?php
use App\Core\View;
$statusLabels = ['aberta' => 'Aguardando análise', 'em_analise' => 'Em análise', 'aprovada' => 'Instalação confirmada', 'rejeitada' => 'Pendência a corrigir', 'concluida' => 'Concluída'];
$statusBadge = ['aberta' => 'novo', 'em_analise' => 'contatado', 'aprovada' => 'active', 'rejeitada' => 'inactive', 'concluida' => 'active'];
?>
<div class="page-header">
    <h1>Pós-venda de Instalação</h1>
</div>

<form method="get" class="filter-bar">
    <select name="status" onchange="this.form.submit()">
        <option value="">Todos os status</option>
        <?php foreach ($statusLabels as $slug => $label): ?>
            <option value="<?= $slug ?>" <?= $status === $slug ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Pedido</th><th>Cliente</th><th>Vendedor</th><th>Aberta em</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($warranties as $w): ?>
                <tr>
                    <td>#<?= (int) $w['order_id'] ?></td>
                    <td><?= View::e($w['client_name']) ?></td>
                    <td><?= View::e($w['seller_name'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($w['created_at']))) ?></td>
                    <td><span class="status-badge status-<?= $statusBadge[$w['status']] ?? 'novo' ?>"><?= $statusLabels[$w['status']] ?? $w['status'] ?></span></td>
                    <td><a href="/painel/garantias/<?= (int) $w['id'] ?>">Ver detalhes</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$warranties): ?>
                <tr><td colspan="6">Nenhuma confirmação de instalação encontrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
