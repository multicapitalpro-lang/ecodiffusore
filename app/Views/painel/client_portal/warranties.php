<?php
use App\Core\View;
$statusLabels = ['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'aprovada' => 'Aprovada', 'rejeitada' => 'Rejeitada', 'concluida' => 'Concluída'];
$statusBadge = ['aberta' => 'novo', 'em_analise' => 'contatado', 'aprovada' => 'active', 'rejeitada' => 'inactive', 'concluida' => 'active'];
$sucesso = isset($_GET['sucesso']);
?>
<div class="page-header">
    <h1>Minhas Garantias</h1>
    <?php if (count($eligibleOrders) === 1): ?>
        <a href="/painel/minhas-garantias/nova?order_id=<?= (int) $eligibleOrders[0]['id'] ?>" class="btn btn-primary">Solicitar Garantia</a>
    <?php endif; ?>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Solicitação enviada com sucesso.</p>
<?php endif; ?>

<?php if (count($eligibleOrders) > 1): ?>
    <form method="get" action="/painel/minhas-garantias/nova" class="inline-form" style="margin-bottom:16px">
        <select name="order_id">
            <?php foreach ($eligibleOrders as $o): ?>
                <option value="<?= (int) $o['id'] ?>">Pedido #<?= (int) $o['id'] ?> — <?= View::e($o['product_names'] ?: 'produto') ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Solicitar Garantia</button>
    </form>
<?php elseif (!$eligibleOrders): ?>
    <p class="hint-text">Você ainda não tem pedidos confirmados elegíveis pra solicitar garantia.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Pedido</th><th>Data</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($warranties as $w): ?>
                <tr>
                    <td>#<?= (int) $w['order_id'] ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($w['created_at']))) ?></td>
                    <td><span class="status-badge status-<?= $statusBadge[$w['status']] ?? 'novo' ?>"><?= $statusLabels[$w['status']] ?? $w['status'] ?></span></td>
                    <td><a href="/painel/minhas-garantias/<?= (int) $w['id'] ?>">Ver detalhes</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$warranties): ?>
                <tr><td colspan="4">Nenhuma solicitação de garantia ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
