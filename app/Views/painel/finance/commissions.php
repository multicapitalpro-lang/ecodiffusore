<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago'];
$sucesso = isset($_GET['sucesso']);
?>
<h1>Comissões</h1>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Quantidade</span>
        <strong><?= $summary['count'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Total gerado</span>
        <strong>R$ <?= number_format($summary['total'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Pago</span>
        <strong>R$ <?= number_format($summary['pago'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Pendente</span>
        <strong>R$ <?= number_format($summary['pendente'], 2, ',', '.') ?></strong>
    </div>
</div>

<?php if (count($bySeller) > 1 || !$canManage): ?>
<h3 class="section-title">Resumo por beneficiário</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Beneficiário</th><th>Qtd.</th><th>Total gerado</th><th>Pago</th><th>Pendente</th></tr></thead>
        <tbody>
            <?php foreach ($bySeller as $s): ?>
                <tr>
                    <td><?= View::e($s['name']) ?></td>
                    <td><?= (int) $s['count_total'] ?></td>
                    <td>R$ <?= number_format((float) $s['total'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $s['total_pago'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $s['total_pendente'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h3 class="section-title">Lançamentos</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Pedido</th><th>Beneficiário</th><th>Papel</th><th>Cliente</th><th>Data</th><th>%</th><th>Comissão</th><th>Situação</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
            <?php $roleLabels = ['licenciado' => 'Licenciado', 'supervisor' => 'Supervisor', 'gerente' => 'Gerente']; ?>
            <?php foreach ($commissions as $c): ?>
                <tr>
                    <td>#<?= (int) $c['order_id'] ?></td>
                    <td>
                        <?= View::e($c['beneficiary_name']) ?>
                        <?php if ((int) $c['beneficiary_id'] !== (int) $c['seller_id']): ?>
                            <br><small class="hint-text">pedido de <?= View::e($c['seller_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= View::e($roleLabels[$c['role_slug']] ?? $c['role_slug']) ?></td>
                    <td><?= View::e($c['client_name']) ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($c['order_date']))) ?></td>
                    <td><?= number_format((float) $c['percentage'], 2, ',', '.') ?>%</td>
                    <td>R$ <?= number_format((float) $c['amount'], 2, ',', '.') ?></td>
                    <td><span class="status-badge status-<?= $c['status'] === 'pendente' ? 'contatado' : 'active' ?>"><?= $statusLabels[$c['status']] ?? $c['status'] ?></span></td>
                    <?php if ($canManage): ?>
                        <td>
                            <?php if ($c['status'] === 'pendente'): ?>
                                <form action="/painel/financeiro/comissoes/<?= (int) $c['id'] ?>/baixar" method="post" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button">Dar baixa</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$commissions): ?>
                <tr><td colspan="<?= $canManage ? 9 : 8 ?>">Nenhuma comissão gerada ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
