<?php
use App\Core\Csrf;
use App\Core\View;
$isEdit = $editing !== null;
$isModal = $isModal ?? false;
$action = $isEdit ? '/painel/calendario/' . (int) $editing['id'] : '/painel/calendario';
$values = $editing ?? [];
$selectedParticipants = $participantIds ?? [];

$toLocalDatetimeValue = function (?string $v): string {
    return $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
};
?>
<div class="modal-header">
    <h2><?= $isEdit ? 'Editar evento' : 'Novo evento' ?></h2>
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
</div>
<div class="modal-body">
    <form action="<?= $action ?>" method="post" class="panel-form ajax-form">
        <?= Csrf::field() ?>
        <label for="cal-title">Título</label>
        <input type="text" id="cal-title" name="title" value="<?= View::e($values['title'] ?? '') ?>" required>
        <p class="field-error" data-error-for="title"></p>

        <label for="cal-type">Tipo</label>
        <select id="cal-type" name="event_type">
            <?php foreach ($typeLabels as $slug => $label): ?>
                <option value="<?= $slug ?>" <?= ($values['event_type'] ?? 'outro') === $slug ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="form-grid-2">
            <div>
                <label for="cal-starts">Início</label>
                <input type="datetime-local" id="cal-starts" name="starts_at" value="<?= $toLocalDatetimeValue($values['starts_at'] ?? null) ?>" required>
                <p class="field-error" data-error-for="starts_at"></p>
            </div>
            <div>
                <label for="cal-ends">Fim (opcional)</label>
                <input type="datetime-local" id="cal-ends" name="ends_at" value="<?= $toLocalDatetimeValue($values['ends_at'] ?? null) ?>">
            </div>
        </div>

        <label for="cal-location">Local (opcional)</label>
        <input type="text" id="cal-location" name="location" value="<?= View::e($values['location'] ?? '') ?>" placeholder="Endereço, ou 'Online'">

        <label for="cal-description">Descrição (opcional)</label>
        <textarea id="cal-description" name="description" rows="3"><?= View::e($values['description'] ?? '') ?></textarea>

        <?php if (count($participantOptions) > 1): ?>
            <label>Participantes</label>
            <p class="hint-text" style="margin-top:0;">Além de você (sempre incluído como dono).</p>
            <?php foreach ($participantOptions as $p): ?>
                <?php if ((int) $p['id'] === (int) $user['id']) continue; ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="participants[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $selectedParticipants, true) ? 'checked' : '' ?>>
                    <?= View::e($p['name']) ?>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>

        <p class="hint-text">Todo mundo convidado recebe um lembrete por WhatsApp ~1h antes.</p>

        <div class="modal-form-actions">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
        </div>
    </form>
</div>
