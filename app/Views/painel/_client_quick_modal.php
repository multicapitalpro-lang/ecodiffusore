<?php
use App\Core\Csrf;
/** @var string $redirectTo query string value pra ClientController::store() saber pra onde voltar */
$redirectTo = $redirectTo ?? '';
?>
<dialog class="modal" id="modal-client-inline">
    <div class="modal-header">
        <h2>Novo cliente / fornecedor</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <form action="/painel/clientes<?= $redirectTo ? '?redirect_to=' . urlencode($redirectTo) : '' ?>" method="post" class="panel-form ajax-form">
            <?= Csrf::field() ?>
            <label for="ci-name">Nome / Razão social</label>
            <input type="text" id="ci-name" name="name" required>
            <p class="field-error" data-error-for="name"></p>
            <label for="ci-document">CPF/CNPJ</label>
            <input type="text" id="ci-document" name="document">
            <label for="ci-whatsapp">WhatsApp</label>
            <input type="text" id="ci-whatsapp" name="whatsapp">
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
