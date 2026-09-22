<?php
use App\Core\View;
?>
<?php if (!$clients): ?>
    <p class="hint-text">Nenhum cliente vinculado.</p>
<?php else: ?>
    <table class="data-table data-table-compact">
        <thead>
            <tr><th>Nome</th><th>WhatsApp</th><th>Cidade/UF</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><a href="/painel/clientes/<?= (int) $c['id'] ?>" target="_blank"><?= View::e($c['name']) ?></a></td>
                    <td><?= View::e($c['whatsapp'] ?: '—') ?></td>
                    <td><?= $c['city'] ? View::e($c['city']) . ($c['state'] ? '/' . View::e($c['state']) : '') : '—' ?></td>
                    <td><span class="status-badge status-<?= View::e($c['status']) ?>"><?= View::e(ucfirst($c['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
