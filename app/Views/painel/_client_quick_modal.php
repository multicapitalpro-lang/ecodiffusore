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
            <div class="form-grid-2">
                <div>
                    <label for="ci-person-type">Tipo de pessoa</label>
                    <select id="ci-person-type" name="person_type">
                        <option value="fisica">Pessoa Física</option>
                        <option value="juridica">Pessoa Jurídica</option>
                    </select>
                </div>
                <div>
                    <label for="ci-document">CPF/CNPJ</label>
                    <input type="text" id="ci-document" name="document">
                </div>
            </div>
            <div id="ci-state-registration-wrap" style="display:none;">
                <label for="ci-state-registration">Inscrição Estadual (opcional)</label>
                <input type="text" id="ci-state-registration" name="state_registration" placeholder="Isento, se não houver">
            </div>
            <label for="ci-whatsapp">WhatsApp</label>
            <input type="text" id="ci-whatsapp" name="whatsapp">
            <script>
            (function () {
                var select = document.getElementById('ci-person-type');
                var wrap = document.getElementById('ci-state-registration-wrap');
                if (!select || !wrap) return;
                function sync() { wrap.style.display = select.value === 'juridica' ? '' : 'none'; }
                select.addEventListener('change', sync);
                sync();
            })();
            </script>
            <div class="modal-form-actions">
                <button type="submit" class="btn btn-primary">Salvar</button>
                <button type="button" class="btn btn-outline" data-modal-close>Cancelar</button>
            </div>
        </form>
    </div>
</dialog>
