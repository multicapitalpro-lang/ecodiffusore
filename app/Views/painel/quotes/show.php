<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'convertido' => 'Convertido'];
$sucesso = isset($_GET['sucesso']);
?>
<div class="page-header">
    <h1>Orçamento #<?= (int) $quote['id'] ?></h1>
    <?php if ($quote['status'] === 'aberto'): ?>
        <a href="/painel/orcamentos/<?= (int) $quote['id'] ?>/editar" class="btn btn-outline">Editar</a>
    <?php endif; ?>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($quote['client_name']) ?></p>
    <p><strong>Vendedor:</strong> <?= View::e($quote['seller_name'] ?: 'Sem vendedor') ?></p>
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y', strtotime($quote['quote_date']))) ?></p>
    <p><strong>Válido até:</strong> <?= $quote['valid_until'] ? View::e(date('d/m/Y', strtotime($quote['valid_until']))) : '—' ?></p>
    <p><strong>Situação:</strong> <span class="status-badge status-<?= $quote['status'] === 'convertido' || $quote['status'] === 'aprovado' ? 'active' : ($quote['status'] === 'recusado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$quote['status']] ?? $quote['status'] ?></span></p>
    <?php if ($quote['converted_order_id']): ?>
        <p><strong>Convertido em:</strong> <a href="/painel/pedidos/<?= (int) $quote['converted_order_id'] ?>">Pedido #<?= (int) $quote['converted_order_id'] ?></a></p>
    <?php endif; ?>
    <?php if ($quote['notes']): ?><p><strong>Obs.:</strong> <?= nl2br(View::e($quote['notes'])) ?></p><?php endif; ?>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= View::e($item['product_name']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td>R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td><strong>R$ <?= number_format((float) $quote['total_value'], 2, ',', '.') ?></strong></td></tr>
        </tfoot>
    </table>
</div>

<?php if ($quote['status'] === 'aberto'): ?>
<div class="order-actions">
    <form action="/painel/orcamentos/<?= (int) $quote['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="aprovado">
        <button type="submit" class="btn btn-primary">Marcar como Aprovado</button>
    </form>
    <form action="/painel/orcamentos/<?= (int) $quote['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="recusado">
        <button type="submit" class="btn btn-danger">Marcar como Recusado</button>
    </form>
</div>
<?php elseif ($quote['status'] === 'aprovado'): ?>
<div class="order-actions">
    <form action="/painel/orcamentos/<?= (int) $quote['id'] ?>/converter" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary">Converter em Pedido</button>
    </form>
</div>
<p class="hint-text">Isso cria um Pedido de Venda novo com os mesmos itens deste orçamento.</p>
<?php endif; ?>
