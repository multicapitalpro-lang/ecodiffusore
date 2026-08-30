<?php
use App\Core\View;
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'conciliado' => 'Conciliado'];
?>
<h1><?= View::e($title) ?></h1>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Vencimento</th><th>Conta</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Situação</th></tr></thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= View::e(date('d/m/Y', strtotime($t['due_date']))) ?></td>
                    <td><?= View::e($t['account_name']) ?></td>
                    <td><?= View::e($t['category']) ?></td>
                    <td><?= View::e($t['description'] ?: '—') ?></td>
                    <td>R$ <?= number_format((float) $t['amount'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $t['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$t['status']] ?? $t['status'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$transactions): ?>
                <tr><td colspan="6">Nenhum lançamento encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
