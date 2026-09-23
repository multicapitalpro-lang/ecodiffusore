<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = $_GET['sucesso'] ?? null;
$erro = $_GET['erro'] ?? null;

$byDay = [];
foreach ($events as $e) {
    $day = date('Y-m-d', strtotime($e['starts_at']));
    $byDay[$day][] = $e;
}
ksort($byDay);

$prevWeek = date('Y-m-d', strtotime($from . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($from . ' +7 days'));
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = date('Y-m-d', strtotime($from . " +{$i} days"));
}

$typeIcons = ['reuniao' => '🗣️', 'visita' => '🚗', 'instalacao' => '🔧', 'tarefa' => '✅', 'outro' => '📌'];
$statusBadge = ['agendado' => 'novo', 'concluido' => 'active', 'cancelado' => 'inactive'];
$weekdayNames = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$formatDayLabel = function (string $day) use ($weekdayNames): string {
    $ts = strtotime($day);
    return $weekdayNames[(int) date('w', $ts)] . ' · ' . date('d/m', $ts);
};
?>
<div class="page-header">
    <h1>📅 Calendário da Equipe</h1>
    <button type="button" class="btn btn-primary" id="btn-new-event">+ Novo evento</button>
</div>
<p class="section-sub">Reuniões, visitas, instalações e tarefas — a equipe toda vê o que precisa ver.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Você não tem acesso a esse evento.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Verifique os dados e tente de novo.</p>
<?php endif; ?>

<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <a href="?from=<?= $prevWeek ?>&view=<?= View::e($view) ?>" class="btn btn-outline btn-sm">← Semana anterior</a>
    <a href="?from=<?= date('Y-m-d') ?>&view=<?= View::e($view) ?>" class="btn btn-outline btn-sm">Hoje</a>
    <a href="?from=<?= $nextWeek ?>&view=<?= View::e($view) ?>" class="btn btn-outline btn-sm">Próxima semana →</a>
    <?php if ($canSeeTeam): ?>
        <div style="margin-left:auto;display:flex;gap:6px;">
            <a href="?from=<?= $from ?>&view=minha" class="btn <?= $view === 'minha' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Minha agenda</a>
            <a href="?from=<?= $from ?>&view=equipe" class="btn <?= $view === 'equipe' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Visão da equipe</a>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($weekDays as $day): ?>
    <?php $dayEvents = $byDay[$day] ?? []; ?>
    <h3 class="section-title" style="margin-top:18px;">
        <?= View::e($formatDayLabel($day)) ?>
        <?= $day === date('Y-m-d') ? ' <span class="status-badge status-active">Hoje</span>' : '' ?>
    </h3>
    <?php if (!$dayEvents): ?>
        <p class="hint-text">Nenhum evento.</p>
    <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($dayEvents as $e): ?>
                <div class="dash-card <?= $e['status'] === 'cancelado' ? 'dash-card-soon' : '' ?>" style="text-align:left;">
                    <span><?= date('H:i', strtotime($e['starts_at'])) ?><?= $e['ends_at'] ? ' – ' . date('H:i', strtotime($e['ends_at'])) : '' ?> · <?= $typeIcons[$e['event_type']] ?? '📌' ?> <?= $typeLabels[$e['event_type']] ?? $e['event_type'] ?></span>
                    <strong style="font-size:1rem;"><?= View::e($e['title']) ?></strong>
                    <?php if ($e['location']): ?><span class="hint-inline">📍 <?= View::e($e['location']) ?></span><?php endif; ?>
                    <?php if ($e['lead_name'] || $e['client_name']): ?><span class="hint-inline">🔗 <?= View::e($e['lead_name'] ?: $e['client_name']) ?></span><?php endif; ?>
                    <span class="hint-inline">Dono: <?= View::e($e['owner_name']) ?></span>
                    <span class="status-badge status-<?= $statusBadge[$e['status']] ?? 'novo' ?>"><?= ucfirst($e['status']) ?></span>
                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                        <button type="button" class="link-button" data-edit-event="<?= (int) $e['id'] ?>">Editar</button>
                        <?php if ($e['status'] === 'agendado'): ?>
                            <form action="/painel/calendario/<?= (int) $e['id'] ?>/status" method="post" class="inline-form">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="status" value="concluido">
                                <button type="submit" class="link-button">Concluir</button>
                            </form>
                            <form action="/painel/calendario/<?= (int) $e['id'] ?>/status" method="post" class="inline-form">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="status" value="cancelado">
                                <button type="submit" class="link-button" style="color:#c53030">Cancelar</button>
                            </form>
                        <?php endif; ?>
                        <form action="/painel/calendario/<?= (int) $e['id'] ?>/excluir" method="post" class="inline-form" onsubmit="return confirm('Excluir este evento?');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<dialog class="modal" id="modal-calendar-event">
    <div id="modal-calendar-event-content"></div>
</dialog>

<script>
(function () {
    document.getElementById('btn-new-event')?.addEventListener('click', function () {
        openFragmentModal('modal-calendar-event', 'modal-calendar-event-content', '/painel/calendario/novo?fragment=1', 'Novo evento');
    });
    document.querySelectorAll('[data-edit-event]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openFragmentModal('modal-calendar-event', 'modal-calendar-event-content', '/painel/calendario/' + btn.dataset.editEvent + '/editar?fragment=1', 'Editar evento');
        });
    });
})();
</script>
