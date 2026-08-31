<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\FinancialTransaction;

$orderStatusLabels = ['em_andamento' => 'Em andamento', 'atendido' => 'Atendido', 'verificado' => 'Verificado', 'cancelado' => 'Cancelado'];
$totalComprado = array_sum(array_map(fn ($o) => $o['status'] !== 'cancelado' ? (float) $o['total_value'] : 0, $orders));
?>
<div class="page-header">
    <h1><?= View::e($client['name']) ?></h1>
    <a href="/painel/clientes/<?= (int) $client['id'] ?>/editar" class="btn btn-outline">Editar cadastro</a>
</div>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total comprado</span>
        <strong>R$ <?= number_format($totalComprado, 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Pedidos</span>
        <strong><?= count($orders) ?></strong>
    </div>
    <div class="dash-card">
        <span>Status</span>
        <strong style="font-size:1rem;"><?= $client['status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></strong>
    </div>
</div>

<div class="two-col">
    <div class="order-summary">
        <p><strong>Tipo:</strong> <?= $client['person_type'] === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física' ?></p>
        <p><strong>Documento:</strong> <?= View::e($client['document'] ?: '—') ?></p>
        <p><strong>Inscrição Estadual:</strong> <?= View::e($client['state_registration'] ?: '—') ?></p>
        <p><strong>E-mail:</strong> <?= View::e($client['email'] ?: '—') ?></p>
        <p><strong>WhatsApp:</strong> <?= View::e($client['whatsapp'] ?: '—') ?></p>
        <p><strong>Cidade/UF:</strong> <?= View::e(trim(($client['city'] ?: '') . ($client['state'] ? '/' . $client['state'] : '')) ?: '—') ?></p>
        <p><strong>Endereço:</strong> <?= View::e($client['address'] ?: '—') ?></p>
        <p><strong>Limite de crédito:</strong> <?php
            if ($client['credit_limit_type'] === 'valor') {
                echo 'R$ ' . number_format((float) $client['credit_limit_value'], 2, ',', '.');
            } elseif ($client['credit_limit_type'] === 'zero') {
                echo 'Zero (sem crédito)';
            } else {
                echo 'Ilimitado';
            }
        ?></p>
        <p><strong>Condição de pagamento:</strong> <?= View::e($client['payment_terms'] ?: '—') ?></p>
        <p><strong>Vendedor:</strong> <?= View::e($client['seller_name'] ?: '—') ?></p>
        <p><strong>Cliente desde:</strong> <?= View::e(date('d/m/Y', strtotime($client['created_at']))) ?></p>
    </div>

    <div id="notas">
        <h3 class="section-title" style="margin-top:0;">Observações internas</h3>
        <form action="/painel/clientes/<?= (int) $client['id'] ?>/notas" method="post" class="panel-form">
            <?= Csrf::field() ?>
            <textarea name="note" placeholder="Escreva uma observação sobre este cliente..." required></textarea>
            <button type="submit" class="btn btn-outline btn-sm">Adicionar observação</button>
        </form>
        <div class="notes-list">
            <?php foreach ($notes as $n): ?>
                <div class="note-item">
                    <p><?= nl2br(View::e($n['note'])) ?></p>
                    <small><?= View::e($n['user_name'] ?? 'Sistema') ?> — <?= View::e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$notes): ?>
                <p class="hint-text">Nenhuma observação registrada ainda.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<h3 class="section-title">Pedidos</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Data</th><th>Vendedor</th><th>Total</th><th>Situação</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= (int) $o['id'] ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                    <td><?= View::e($o['seller_name'] ?: '—') ?></td>
                    <td>R$ <?= number_format((float) $o['total_value'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $o['status'] === 'verificado' ? 'active' : ($o['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $orderStatusLabels[$o['status']] ?? $o['status'] ?></span></td>
                    <td><a href="/painel/pedidos/<?= (int) $o['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="6">Nenhum pedido registrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h3 class="section-title">Lançamentos financeiros vinculados</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Data</th><th>Categoria</th><th>Histórico</th><th>Tipo</th><th>Valor</th><th>Situação</th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['category_name'] ?: '—') ?></td>
                    <td><?= View::e($t['description'] ?: '—') ?></td>
                    <td><?= $t['type'] === 'entrada' ? 'Entrada' : 'Saída' ?></td>
                    <td class="<?= $t['type'] === 'entrada' ? 'text-green' : 'text-red' ?>">R$ <?= number_format(FinancialTransaction::totalValue($t), 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $t['status'] === 'pendente' ? 'Pendente' : 'Pago' ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="6">Nenhum lançamento vinculado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
