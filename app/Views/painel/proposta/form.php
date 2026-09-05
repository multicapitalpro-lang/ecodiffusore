<?php
use App\Core\Csrf;
$isModal = $isModal ?? false;
$isViewOnly = $isViewOnly ?? false;
?>
<?php if ($isModal): ?>
<div class="modal-header">
    <h2>⚡ Proposta Fácil</h2>
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
</div>
<div class="modal-body">
<?php else: ?>
<h1>⚡ Proposta Fácil</h1>
<?php endif; ?>

<?php if ($isViewOnly): ?>
    <p class="hint-text">Gerente e Supervisor têm acesso de visualização — peça pra um Vendedor ou Licenciado gerar a proposta.</p>
<?php else: ?>
    <p class="hint-text" style="margin-top:0;">Preencha os dados do veículo pra gerar na hora a economia estimada, o preço do produto e as condições de pagamento — tudo pronto pra compartilhar por WhatsApp ou baixar em PDF.</p>

    <p class="form-msg form-msg-erro" id="proposta-form-error" hidden></p>

    <div id="proposta-step-comprador">
        <div class="panel-form">
            <p class="hint-text" style="margin-top:0;">Rápido: só o nome e o WhatsApp de quem vai receber a proposta. O resto (veículo e consumo) vem na próxima etapa.</p>
            <label for="comprador-name">Nome</label>
            <input type="text" id="comprador-name" name="name" form="proposta-form" required>
            <p class="field-error" data-error-for="name"></p>

            <label for="comprador-whatsapp">WhatsApp</label>
            <input type="text" id="comprador-whatsapp" name="whatsapp" form="proposta-form" placeholder="45999998888" required>
            <p class="field-error" data-error-for="whatsapp"></p>

            <button type="button" class="btn btn-primary" id="comprador-continue" style="width:100%;">Continuar</button>
        </div>
    </div>

    <div id="proposta-step-detalhes" hidden>
        <div id="comprador-summary" class="proposta-comprador-summary">
            Comprador: <strong id="comprador-summary-text"></strong>
            <button type="button" id="comprador-edit" style="background:none;border:none;cursor:pointer;color:var(--green-dark);font-weight:700;">editar</button>
        </div>

        <form id="proposta-form" action="/painel/proposta-facil" method="post" class="panel-form panel-form-wide" data-modal="<?= $isModal ? '1' : '' ?>">
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
                    <p class="field-error" data-error-for="year"></p>
                </div>
            </div>
            <div class="form-grid-2">
                <div>
                    <label for="brand">Marca</label>
                    <input type="text" id="brand" name="brand" placeholder="Ex: Scania, Volvo, Mercedes" required>
                    <p class="field-error" data-error-for="brand"></p>
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
                    <p class="field-error" data-error-for="has_arla"></p>
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
                    <p class="field-error" data-error-for="ecu_status"></p>
                </div>
                <div id="reprogrammed-power-wrap" hidden>
                    <label for="reprogrammed_power">Potência reprogramada</label>
                    <input type="text" id="reprogrammed_power" name="reprogrammed_power" placeholder="Ex: 480cv">
                    <p class="field-error" data-error-for="reprogrammed_power"></p>
                </div>
            </div>

            <h3>Consumo</h3>
            <div class="form-grid-2">
                <div>
                    <label for="km_mensal">Média de KM rodados por mês</label>
                    <input type="text" id="km_mensal" name="km_mensal" placeholder="Ex: 12000" required>
                    <p class="field-error" data-error-for="km_mensal"></p>
                </div>
                <div>
                    <label for="km_litro">Média de KM por litro do veículo</label>
                    <input type="text" id="km_litro" name="km_litro" placeholder="Ex: 2,8" required>
                    <p class="field-error" data-error-for="km_litro"></p>
                </div>
            </div>
            <label for="preco_diesel">Preço médio do diesel na região (R$/litro)</label>
            <input type="text" id="preco_diesel" name="preco_diesel" placeholder="Ex: 6,10" required>
            <p class="field-error" data-error-for="preco_diesel"></p>

            <h3>Preço da venda</h3>
            <label class="checkbox-label">
                <input type="radio" name="price_tier" value="baixo" checked> Padrão — R$ <?= number_format($priceRange['low'] ?? 0, 2, ',', '.') ?>
            </label>
            <label class="checkbox-label">
                <input type="radio" name="price_tier" value="alto"> Máximo — R$ <?= number_format($priceRange['high'] ?? 0, 2, ',', '.') ?>
            </label>
            <p class="field-error" data-error-for="price_tier"></p>

            <button type="submit" class="btn btn-primary">Gerar proposta</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($isModal): ?>
</div>
<?php endif; ?>
