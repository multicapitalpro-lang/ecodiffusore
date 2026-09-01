<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['aberto' => 'Aberto', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'convertido' => 'Convertido'];
$csrfToken = Csrf::token();
$isViewOnly = $isViewOnly ?? false;
?>
<div class="page-header">
    <h1>Orçamentos — Kanban</h1>
    <a href="/painel/orcamentos" class="btn btn-outline">Ver como lista</a>
</div>
<p class="section-sub"><?= $isViewOnly ? 'Visualização somente leitura.' : 'Arraste para Aprovado/Recusado (a partir de Aberto) ou para Convertido (a partir de Aprovado).' ?></p>

<div class="kanban-board" id="quotes-board">
    <?php foreach ($statusLabels as $status => $label): ?>
        <div class="kanban-col" data-status="<?= $status ?>">
            <div class="kanban-col-header">
                <?= View::e($label) ?>
                <span class="kanban-count"><?= count($columns[$status]) ?></span>
            </div>
            <div class="kanban-col-body" data-drop-status="<?= $status ?>">
                <?php foreach ($columns[$status] as $q): ?>
                    <div class="kanban-card" draggable="<?= $isViewOnly ? 'false' : 'true' ?>" data-quote-id="<?= (int) $q['id'] ?>" data-from-status="<?= $status ?>">
                        <strong>#<?= (int) $q['id'] ?> — <?= View::e($q['client_name']) ?></strong>
                        <span class="kanban-card-meta"><?= View::e($q['seller_name'] ?: 'Sem vendedor') ?></span>
                        <span class="kanban-card-meta">R$ <?= number_format((float) $q['total_value'], 2, ',', '.') ?></span>
                        <a href="/painel/orcamentos/<?= (int) $q['id'] ?>" class="link-small">Ver detalhes</a>
                    </div>
                <?php endforeach; ?>
                <?php if (!$columns[$status]): ?>
                    <p class="hint-text kanban-empty">Nenhum orçamento aqui.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const board = document.getElementById('quotes-board');
    if (!board) return;

    const allowedDrops = { aberto: ['aprovado', 'recusado'], aprovado: ['convertido'] };
    let dragged = null;

    board.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', (e) => {
            dragged = card;
            card.classList.add('is-dragging');
        });
        card.addEventListener('dragend', () => { card.classList.remove('is-dragging'); dragged = null; });
    });

    board.querySelectorAll('.kanban-col-body').forEach((col) => {
        col.addEventListener('dragover', (e) => {
            if (!dragged) return;
            const allowed = (allowedDrops[dragged.dataset.fromStatus] || []).includes(col.dataset.dropStatus);
            if (allowed) { e.preventDefault(); col.classList.add('is-dragover'); }
        });
        col.addEventListener('dragleave', () => col.classList.remove('is-dragover'));
        col.addEventListener('drop', async (e) => {
            e.preventDefault();
            col.classList.remove('is-dragover');
            if (!dragged) return;

            const from = dragged.dataset.fromStatus;
            const to = col.dataset.dropStatus;
            if (!(allowedDrops[from] || []).includes(to)) return;

            const id = dragged.dataset.quoteId;
            const formData = new FormData();
            formData.set('csrf_token', csrfToken);

            const url = to === 'convertido'
                ? '/painel/orcamentos/' + id + '/converter'
                : '/painel/orcamentos/' + id + '/status';
            if (to !== 'convertido') formData.set('status', to);

            await fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            window.location.reload();
        });
    });
})();
</script>
