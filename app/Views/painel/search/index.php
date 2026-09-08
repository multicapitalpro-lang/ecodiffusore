<?php
use App\Core\View;
?>
<h1>Busca</h1>
<p class="section-sub">Encontre lead, cliente ou pedido por nome, telefone ou placa.</p>

<form method="get" action="/painel/busca" class="filter-bar">
    <input type="text" name="q" value="<?= View::e($term) ?>" placeholder="Nome, telefone ou placa..." autofocus style="min-width:280px">
    <button type="submit" class="btn btn-outline">Buscar</button>
</form>

<?php if ($term !== '' && mb_strlen($term) < 2): ?>
    <p class="hint-text" style="margin-top:16px">Digite ao menos 2 caracteres.</p>
<?php elseif ($term !== ''): ?>
    <?php $totalResults = count($clients) + count($leads) + count($orders); ?>

    <h3 class="section-title" style="margin-top:24px">Clientes <?= $clients ? '(' . count($clients) . ')' : '' ?></h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Nome</th><th>WhatsApp</th><th>Documento</th><th>Cidade</th><th>Vendedor</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><?= View::e($c['name']) ?></td>
                        <td><?= View::e($c['whatsapp'] ?: '—') ?></td>
                        <td><?= View::e($c['document'] ?: '—') ?></td>
                        <td><?= View::e($c['city'] ?: '—') ?></td>
                        <td><?= View::e($c['seller_name'] ?: '—') ?></td>
                        <td><a href="/painel/clientes/<?= (int) $c['id'] ?>" class="link-small">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?>
                    <tr><td colspan="6">Nenhum cliente encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3 class="section-title" style="margin-top:24px">Leads <?= $leads ? '(' . count($leads) . ')' : '' ?></h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Nome</th><th>WhatsApp</th><th>Placa</th><th>Status</th><th>Responsável</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($leads as $l): ?>
                    <tr>
                        <td><?= View::e($l['name']) ?></td>
                        <td><?= View::e($l['whatsapp'] ?: '—') ?></td>
                        <td><?= View::e($l['vehicle_plate'] ?: '—') ?></td>
                        <td><?= View::e(ucfirst(str_replace('_', ' ', $l['status']))) ?></td>
                        <td><?= View::e($l['assigned_name'] ?: '—') ?></td>
                        <td><a href="/painel/leads" class="link-small">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$leads): ?>
                    <tr><td colspan="6">Nenhum lead encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3 class="section-title" style="margin-top:24px">Pedidos <?= $orders ? '(' . count($orders) . ')' : '' ?></h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>#</th><th>Cliente</th><th>Placa</th><th>Data</th><th>Total</th><th>Vendedor</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= (int) $o['id'] ?></td>
                        <td><?= View::e($o['client_name']) ?></td>
                        <td><?= View::e($o['vehicle_plate'] ?: '—') ?></td>
                        <td><?= View::e($o['order_date']) ?></td>
                        <td>R$ <?= number_format((float) $o['total_value'], 2, ',', '.') ?></td>
                        <td><?= View::e($o['seller_name'] ?: '—') ?></td>
                        <td><a href="/painel/pedidos/<?= (int) $o['id'] ?>" class="link-small">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="7">Nenhum pedido encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalResults === 0): ?>
        <p class="hint-text" style="margin-top:16px">Nada encontrado pra "<?= View::e($term) ?>".</p>
    <?php endif; ?>
<?php endif; ?>
