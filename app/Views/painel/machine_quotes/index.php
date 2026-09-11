<?php
use App\Core\View;
$statusLabels = ['pendente' => 'Pendente', 'respondido' => 'Respondido'];
$statusBadge = ['pendente' => 'novo', 'respondido' => 'active'];
?>
<div class="page-header">
    <h1>Cotações de Máquina Agrícola</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Solicitações vindas do site (sem placa, sem preço automático — ainda não tem tabela pronta por tipo de máquina). Abra, veja as fotos e digite o valor pra retornar pro cliente por fora (WhatsApp/telefone).</p>

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
        <thead><tr><th>Cliente</th><th>Tipo de máquina</th><th>Marca/Modelo</th><th>Data</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($quotes as $q): ?>
                <tr>
                    <td><?= View::e($q['client_name']) ?><?php if (!empty($q['client_whatsapp'])): ?><br><small class="hint-text">💬 <?= View::e($q['client_whatsapp']) ?></small><?php endif; ?></td>
                    <td><?= View::e($q['machine_type']) ?></td>
                    <td><?= View::e(trim(($q['brand'] ?? '') . ' ' . ($q['model'] ?? '')) ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($q['created_at']))) ?></td>
                    <td><span class="status-badge status-<?= $statusBadge[$q['status']] ?? 'novo' ?>"><?= $statusLabels[$q['status']] ?? $q['status'] ?></span></td>
                    <td><a href="/painel/cotacoes-maquina/<?= (int) $q['id'] ?>">Ver detalhes</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$quotes): ?>
                <tr><td colspan="6">Nenhuma cotação de máquina agrícola encontrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
