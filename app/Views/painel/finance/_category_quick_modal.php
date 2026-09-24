<?php
use App\Core\Csrf;
use App\Core\View;
/** @var string $type 'entrada' ou 'saida' -- herdado do formulario pai (Contas a Pagar/Receber) */
/** @var array $categoryParents */
/** @var string $redirectTo query string value pra FinanceController::storeCategory() saber pra onde voltar */
$redirectTo = $redirectTo ?? '';
?>
<dialog class="modal" id="modal-category-inline">
    <div class="modal-header">
        <h2>Nova categoria</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/financeiro/categorias<?= $redirectTo ? '?redirect_to=' . urlencode($redirectTo) : '' ?>" method="post" class="panel-form ajax-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="type" value="<?= View::e($type) ?>">
            <label for="cat-parent">Grupo</label>
            <select id="cat-parent" name="parent_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($categoryParents as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= View::e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-error" data-error-for="parent_id"></p>
            <label for="cat-name">Nome da categoria</label>
            <input type="text" id="cat-name" name="name" required>
            <p class="field-error" data-error-for="name"></p>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
