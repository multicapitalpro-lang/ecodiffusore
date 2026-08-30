<?php
use App\Core\View;
$statusLabels = [
    'novo' => 'Novo',
    'contatado' => 'Contatado',
    'convertido' => 'Convertido',
    'descartado' => 'Descartado',
];
?>
<h1>Leads</h1>

<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr><th>Nome</th><th>WhatsApp</th><th>Cidade</th><th>Caminhão</th><th>Status</th><th>Recebido em</th></tr>
        </thead>
        <tbody>
            <?php foreach ($leads as $lead): ?>
                <tr>
                    <td><?= View::e($lead['name']) ?></td>
                    <td><a href="https://wa.me/55<?= preg_replace('/\D/', '', $lead['whatsapp']) ?>" target="_blank" rel="noopener"><?= View::e($lead['whatsapp']) ?></a></td>
                    <td><?= View::e($lead['city'] ?: '—') ?></td>
                    <td><?= View::e($lead['truck_brand'] ?: '—') ?></td>
                    <td><span class="status-badge status-<?= View::e($lead['status']) ?>"><?= View::e($statusLabels[$lead['status']] ?? $lead['status']) ?></span></td>
                    <td><?= View::e($lead['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$leads): ?>
                <tr><td colspan="6">Nenhum lead recebido ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
