<?php
use App\Core\Csrf;
use App\Core\View;
?>
<h1>⚡ Proposta Fácil</h1>
<p class="hint-text">Preencha os dados do veículo pra gerar na hora a economia estimada, o preço do produto e as condições de pagamento — tudo pronto pra compartilhar por WhatsApp ou baixar em PDF.</p>

<?php if (($_GET['erro'] ?? '') === '1'): ?>
    <p class="form-msg form-msg-erro">Confira os campos obrigatórios (motor reprogramado exige a potência reprogramada) e tente de novo.</p>
<?php elseif (($_GET['erro'] ?? '') === 'csrf'): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php endif; ?>

<div id="comprador-summary" class="proposta-comprador-summary" hidden>
    Comprador: <strong id="comprador-summary-text"></strong>
    <button type="button" class="link-small" data-modal-open="comprador-modal" style="background:none;border:none;cursor:pointer;">editar</button>
</div>

<form id="proposta-form" action="/painel/proposta-facil" method="post" class="panel-form-wide">
    <?= Csrf::field() ?>

    <h3 style="margin-top:0;">Veículo</h3>
    <div class="form-grid-2">
        <div>
            <label for="plate">Placa</label>
            <input type="text" id="plate" name="plate" style="text-transform:uppercase">
        </div>
        <div>
            <label for="year">Ano</label>
            <input type="text" id="year" name="year" required>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="brand">Marca</label>
            <input type="text" id="brand" name="brand" placeholder="Ex: Scania, Volvo, Mercedes" required>
        </div>
        <div>
            <label for="model">Modelo</label>
            <input type="text" id="model" name="model">
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="power">Potência do motor</label>
            <input type="text" id="power" name="power" placeholder="Ex: 440cv">
        </div>
        <div>
            <label for="has_arla">Possui sistema de ARLA?</label>
            <select id="has_arla" name="has_arla" required>
                <option value="">Selecione</option>
                <option value="sim">Sim</option>
                <option value="nao">Não</option>
            </select>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="ecu_status">Motor original ou reprogramado?</label>
            <select id="ecu_status" name="ecu_status" required onchange="document.getElementById('reprogrammed-power-wrap').hidden = this.value !== 'reprogramado';">
                <option value="">Selecione</option>
                <option value="original">Original de fábrica</option>
                <option value="reprogramado">Reprogramado (chip)</option>
            </select>
        </div>
        <div id="reprogrammed-power-wrap" hidden>
            <label for="reprogrammed_power">Potência reprogramada</label>
            <input type="text" id="reprogrammed_power" name="reprogrammed_power" placeholder="Ex: 480cv">
        </div>
    </div>

    <h3>Consumo</h3>
    <div class="form-grid-2">
        <div>
            <label for="km_mensal">Média de KM rodados por mês</label>
            <input type="text" id="km_mensal" name="km_mensal" placeholder="Ex: 12000" required>
        </div>
        <div>
            <label for="km_litro">Média de KM por litro do veículo</label>
            <input type="text" id="km_litro" name="km_litro" placeholder="Ex: 2,8" required>
        </div>
    </div>
    <label for="preco_diesel">Preço médio do diesel na região (R$/litro)</label>
    <input type="text" id="preco_diesel" name="preco_diesel" placeholder="Ex: 6,10" required>

    <button type="submit" class="btn btn-primary">Gerar proposta</button>
</form>

<dialog class="modal" id="comprador-modal" data-autoopen>
    <div class="modal-header">
        <h2>Dados do comprador</h2>
    </div>
    <div class="modal-body">
        <p class="hint-text" style="margin-top:0;">Rápido: só o nome e o WhatsApp de quem vai receber a proposta. O resto (veículo e consumo) vem na próxima tela.</p>
        <label for="comprador-name">Nome</label>
        <input type="text" id="comprador-name" name="name" form="proposta-form" required>
        <p class="field-error" data-error-for="name"></p>

        <label for="comprador-whatsapp">WhatsApp</label>
        <input type="text" id="comprador-whatsapp" name="whatsapp" form="proposta-form" placeholder="45999998888" required>
        <p class="field-error" data-error-for="whatsapp"></p>

        <div class="modal-form-actions">
            <button type="button" class="btn btn-primary" id="comprador-continue" style="width:100%;">Continuar</button>
        </div>
    </div>
</dialog>

<script>
(function () {
    var modal = document.getElementById('comprador-modal');
    var nameInput = document.getElementById('comprador-name');
    var whatsappInput = document.getElementById('comprador-whatsapp');
    var summary = document.getElementById('comprador-summary');
    var summaryText = document.getElementById('comprador-summary-text');

    function updateSummary() {
        if (nameInput.value.trim() && whatsappInput.value.trim()) {
            summaryText.textContent = nameInput.value.trim() + ' · ' + whatsappInput.value.trim();
            summary.hidden = false;
        } else {
            summary.hidden = true;
        }
    }

    document.getElementById('comprador-continue').addEventListener('click', function () {
        var ok = true;
        document.querySelectorAll('#comprador-modal [data-error-for]').forEach(function (p) { p.textContent = ''; });

        if (!nameInput.value.trim()) {
            document.querySelector('[data-error-for="name"]').textContent = 'Informe o nome.';
            ok = false;
        }
        if (!whatsappInput.value.trim()) {
            document.querySelector('[data-error-for="whatsapp"]').textContent = 'Informe o WhatsApp.';
            ok = false;
        }
        if (!ok) return;

        updateSummary();
        modal.close();
    });

    modal.addEventListener('close', updateSummary);
})();
</script>
