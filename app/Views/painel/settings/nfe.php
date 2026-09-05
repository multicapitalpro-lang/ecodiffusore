<?php
use App\Core\Csrf;
use App\Core\View;
$values = $settings;
$sucesso = isset($_GET['sucesso']);
?>
<h1>Configurações de NF-e</h1>
<p class="hint-text" style="margin-top:0;">Emissão automática de nota fiscal de serviço pela Asaas quando um pedido é confirmado como pago. Enquanto estiver desligada, nenhuma nota é emitida — os pedidos continuam sendo processados normalmente.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso.</p>
<?php endif; ?>

<div class="proposta-installments-note">
    ⚠️ Antes de ativar: confirme com o contador as alíquotas abaixo (a conta está em Simples Nacional) e
    confirme que o cadastro fiscal da empresa está completo no painel da própria Asaas (app.asaas.com →
    Configurações → Notas fiscais). Emitir nota fiscal é uma ação com efeito fiscal real — errar a
    configuração pode gerar uma nota incorreta que precisa ser cancelada/corrigida manualmente.
</div>

<form action="/painel/configuracoes/nfe" method="post" class="panel-form-wide" id="nfe-settings-form">
    <?= Csrf::field() ?>

    <label class="checkbox-inline" style="font-size:1.05rem;">
        <input type="checkbox" name="enabled" value="1" <?= !empty($values['enabled']) ? 'checked' : '' ?>>
        Emitir NF-e automaticamente quando um pedido for confirmado como pago
    </label>

    <h3>Serviço municipal</h3>
    <p class="hint-text" style="margin-top:0;">Busque pela descrição do serviço cadastrado na Asaas (ex: "perícia", "análise técnica"). Selecione um resultado pra preencher o código automaticamente.</p>
    <div style="position:relative;max-width:520px;">
        <input type="text" id="nfe-service-search" placeholder="Buscar serviço municipal..." autocomplete="off">
        <div id="nfe-service-results" class="autocomplete-results" hidden></div>
    </div>
    <div class="form-grid-2" style="margin-top:10px;">
        <div>
            <label for="municipal_service_id">Código do serviço selecionado</label>
            <input type="text" id="municipal_service_id" name="municipal_service_id" value="<?= View::e((string) ($values['municipal_service_id'] ?? '')) ?>" readonly>
            <p class="field-error" data-error-for="municipal_service_id"><?= View::e($errors['municipal_service_id'] ?? '') ?></p>
        </div>
        <div>
            <label for="municipal_service_description">Descrição do serviço</label>
            <input type="text" id="municipal_service_description" name="municipal_service_description" value="<?= View::e((string) ($values['municipal_service_description'] ?? '')) ?>" readonly>
        </div>
    </div>

    <h3>Impostos</h3>
    <p class="hint-text" style="margin-top:0;">Percentuais aplicados sobre o valor da nota. Empresas em Simples Nacional normalmente deixam COFINS/CSLL/INSS/IR/PIS zerados (o imposto já é pago via DAS) — confirme com o contador.</p>
    <div class="form-grid-2">
        <div>
            <label for="iss_pct">ISS (%)</label>
            <input type="number" id="iss_pct" name="iss_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['iss_pct'] ?? '0')) ?>" required>
            <p class="field-error" data-error-for="iss_pct"><?= View::e($errors['iss_pct'] ?? '') ?></p>
        </div>
        <div>
            <label class="checkbox-inline" style="margin-top:28px;">
                <input type="checkbox" name="retain_iss" value="1" <?= !empty($values['retain_iss']) ? 'checked' : '' ?>>
                Reter ISS na nota
            </label>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="cofins_pct">COFINS (%)</label>
            <input type="number" id="cofins_pct" name="cofins_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['cofins_pct'] ?? '0')) ?>" required>
            <p class="field-error" data-error-for="cofins_pct"><?= View::e($errors['cofins_pct'] ?? '') ?></p>
        </div>
        <div>
            <label for="csll_pct">CSLL (%)</label>
            <input type="number" id="csll_pct" name="csll_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['csll_pct'] ?? '0')) ?>" required>
            <p class="field-error" data-error-for="csll_pct"><?= View::e($errors['csll_pct'] ?? '') ?></p>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="inss_pct">INSS (%)</label>
            <input type="number" id="inss_pct" name="inss_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['inss_pct'] ?? '0')) ?>" required>
            <p class="field-error" data-error-for="inss_pct"><?= View::e($errors['inss_pct'] ?? '') ?></p>
        </div>
        <div>
            <label for="ir_pct">IR (%)</label>
            <input type="number" id="ir_pct" name="ir_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['ir_pct'] ?? '0')) ?>" required>
            <p class="field-error" data-error-for="ir_pct"><?= View::e($errors['ir_pct'] ?? '') ?></p>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="pis_pct">PIS (%)</label>
            <input type="number" id="pis_pct" name="pis_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['pis_pct'] ?? '0')) ?>" required>
            <p class="field-error" data-error-for="pis_pct"><?= View::e($errors['pis_pct'] ?? '') ?></p>
        </div>
    </div>

    <h3>Descrição da nota</h3>
    <p class="hint-text" style="margin-top:0;">Use <code>{pedido}</code> pra inserir o número do pedido automaticamente.</p>
    <div>
        <label for="service_description_template">Descrição do serviço na nota</label>
        <input type="text" id="service_description_template" name="service_description_template" value="<?= View::e((string) ($values['service_description_template'] ?? '')) ?>" maxlength="255">
    </div>
    <div style="margin-top:10px;">
        <label for="observations_template">Observações (opcional)</label>
        <textarea id="observations_template" name="observations_template" rows="2"><?= View::e((string) ($values['observations_template'] ?? '')) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:16px;">Salvar</button>
</form>

<script>
(function () {
    var input = document.getElementById('nfe-service-search');
    var results = document.getElementById('nfe-service-results');
    var idField = document.getElementById('municipal_service_id');
    var descField = document.getElementById('municipal_service_description');
    if (!input) { return; }

    var timer = null;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 3) {
            results.hidden = true;
            results.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch('/painel/configuracoes/nfe/buscar-servico?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    results.innerHTML = '';
                    if (!data.ok || !data.data.length) {
                        results.innerHTML = '<div class="autocomplete-empty">Nenhum serviço encontrado.</div>';
                        results.hidden = false;
                        return;
                    }
                    data.data.forEach(function (service) {
                        var item = document.createElement('div');
                        item.className = 'autocomplete-item';
                        item.textContent = service.description + ' (ISS ' + service.issTax + '%)';
                        item.addEventListener('click', function () {
                            idField.value = service.id;
                            descField.value = service.description;
                            input.value = service.description;
                            results.hidden = true;
                        });
                        results.appendChild(item);
                    });
                    results.hidden = false;
                })
                .catch(function () {
                    results.innerHTML = '<div class="autocomplete-empty">Erro ao buscar. Tente de novo.</div>';
                    results.hidden = false;
                });
        }, 350);
    });

    document.addEventListener('click', function (e) {
        if (e.target !== input && !results.contains(e.target)) {
            results.hidden = true;
        }
    });
})();
</script>
