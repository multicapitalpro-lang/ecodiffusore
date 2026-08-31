<?php
use App\Core\View;
$statusLabels = ['aberta' => 'Aberta', 'enviada' => 'Enviada', 'retornada' => 'Retornada'];
?>
<div class="page-header">
    <h1>Remessas</h1>
    <div style="display:flex;gap:10px;">
        <a href="/painel/financeiro/remessas/nova?type=pagar" class="btn btn-outline">+ Remessa a Pagar</a>
        <a href="/painel/financeiro/remessas/nova?type=receber" class="btn btn-primary">+ Remessa a Receber</a>
    </div>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Tipo</th><th>Conta</th><th>Títulos</th><th>Valor</th><th>Situação</th><th>Criada em</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($remittances as $r): ?>
                <tr>
                    <td>#<?= (int) $r['id'] ?></td>
                    <td><?= $r['type'] === 'pagar' ? 'A Pagar' : 'A Receber' ?></td>
                    <td><?= View::e($r['account_name']) ?></td>
                    <td><?= (int) $r['items_count'] ?></td>
                    <td>R$ <?= number_format((float) $r['total_amount'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $r['status'] === 'aberta' ? 'contatado' : ($r['status'] === 'retornada' ? 'active' : 'novo') ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
                    <td><?= View::e(date('d/m/Y', strtotime($r['created_at']))) ?></td>
                    <td><a href="/painel/financeiro/remessas/<?= (int) $r['id'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$remittances): ?>
                <tr><td colspan="8">Nenhuma remessa criada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
