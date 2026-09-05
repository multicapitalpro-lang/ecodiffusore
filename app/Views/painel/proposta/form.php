<?php
use App\Core\Csrf;
use App\Core\View;
?>
<h1>⚡ Proposta Fácil</h1>
<p class="hint-text">Preencha os dados do interessado e do veículo pra gerar na hora a economia estimada, o preço do produto e as condições de pagamento — tudo pronto pra compartilhar por WhatsApp ou baixar em PDF.</p>

<?php if (($_GET['erro'] ?? '') === '1'): ?>
    <p class="form-msg form-msg-erro">Confira os campos obrigatórios (motor reprogramado exige a potência reprogramada) e tente de novo.</p>
<?php elseif (($_GET['erro'] ?? '') === 'csrf'): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php endif; ?>

<form action="/painel/proposta-facil" method="post" class="panel-form-wide">
    <?= Csrf::field() ?>

    <h3 style="margin-top:0;">Interessado</h3>
    <div class="form-grid-2">
        <div>
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div>
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp" placeholder="45999998888" required>
        </div>
    </div>

    <h3>Veículo</h3>
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
