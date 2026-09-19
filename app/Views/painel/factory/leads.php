<?php
use App\Core\View;
?>
<div class="page-header">
    <h1>Leads e Contatos</h1>
</div>
<p class="hint-text" style="margin-top:0;">Acompanhe o volume de gente entrando em contato com a Ecodiffusore — clientes se cadastrando, negociações em andamento, possíveis novos licenciados. Só nome, cidade e etapa — sem telefone ou e-mail, pra não abrir negociação direta com quem já está sendo atendido pela nossa rede.</p>

<form class="filter-bar" onsubmit="return false;">
    <input type="text" id="leads-search" placeholder="Buscar por nome ou cidade...">
</form>

<div class="table-scroll">
    <table class="data-table" id="leads-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Cidade</th>
                <th>Etapa</th>
                <th>Desde</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leads as $l): ?>
                <tr data-search="<?= View::e(mb_strtolower($l['name'] . ' ' . $l['city'])) ?>">
                    <td><?= View::e($l['name']) ?></td>
                    <td><?= $l['city'] ? View::e($l['city']) : '—' ?></td>
                    <td><span class="status-badge status-novo"><?= View::e($stageNames[$l['status']] ?? $l['status']) ?></span></td>
                    <td><?= View::e(date('d/m/Y', strtotime($l['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$leads): ?>
                <tr><td colspan="4">Nenhum contato registrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
    var searchInput = document.getElementById('leads-search');
    var rows = Array.from(document.querySelectorAll('#leads-table tbody tr[data-search]'));
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
        var term = searchInput.value.trim().toLowerCase();
        rows.forEach(function (row) {
            row.hidden = term !== '' && row.getAttribute('data-search').indexOf(term) === -1;
        });
    });
})();
</script>
