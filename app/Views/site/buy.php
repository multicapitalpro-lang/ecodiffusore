<?php
use App\Core\Csrf;
use App\Core\View;
?>
<section class="buy-hero">
    <div class="site-container">
        <h1>Ecodiffusore — economia real de combustível</h1>
        <p>Dispositivo patenteado (INPI) que reduz o consumo de diesel e aumenta a performance do seu veículo, máquina ou gerador.</p>
    </div>
</section>

<section class="buy-section">
    <div class="site-container">
        <h2>Documentação técnica e validação</h2>
        <p class="section-sub">Tecnologia registrada e testada — não é promessa vazia.</p>

        <div class="buy-docs-grid">
            <div class="buy-doc-card">
                <h4>📜 Patente registrada no INPI</h4>
                <p>Carta Patente nº <strong>BR 202020013548-7</strong>, modelo de utilidade, título
                "Disposição construtiva aplicada em difusor de ar para motores de combustão".
                Depósito em 01/07/2020, validade de 15 anos.</p>
                <a href="<?= View::asset('/assets/docs/carta-patente.pdf') ?>" target="_blank" rel="noopener" class="link-small">Ver certificado completo (PDF) →</a>
            </div>
            <div class="buy-doc-card">
                <h4>®️ Marca registrada</h4>
                <p>Registro de marca Ecodiffusore no INPI, processo nº <strong>920298915</strong>,
                concedido em 27/04/2021, com vigência até 27/04/2031.</p>
                <a href="<?= View::asset('/assets/docs/certificado-registro-marca.pdf') ?>" target="_blank" rel="noopener" class="link-small">Ver certificado completo (PDF) →</a>
            </div>
            <div class="buy-doc-card">
                <h4>🔬 Estudo técnico ECOTEC</h4>
                <p>Acompanhamento de consumo em máquinas agrícolas (John Deere 6605, Valtra BP905,
                John Deere STS 9670) conduzido pelo Instituto de Pesquisas Ecotecnológicas, sob
                responsabilidade do MsC. Eng. Químico Jair Duarte (CRQ 09302137-PR).</p>
                <a href="<?= View::asset('/assets/docs/laudo-maquinas-agricolas.pdf') ?>" target="_blank" rel="noopener" class="link-small">Ver laudo completo (PDF) →</a>
            </div>
            <div class="buy-doc-card">
                <h4>📘 Manual técnico e testes de emissão</h4>
                <p>Testado com analisador de gases OPTMA 7 num Mercedes Actros 2548S (JR
                Transportes, Cascavel/PR): NOx caiu de <strong>5.130 para 163 ppm</strong>. Opacidade
                também testada — Toyota Hilux 4x4 de 0,31 para 0,12 m⁻¹ (CATA) e Mercedes Axor 2041
                de 0,28K para 0,02K (ATIVE). Responsável técnico Roberson R. Parizotto, Crea-PR
                115226/D, ART nº 1720264228859.</p>
                <a href="<?= View::asset('/assets/docs/manual-tecnico.pdf') ?>" target="_blank" rel="noopener" class="link-small">Ver manual completo (PDF) →</a>
            </div>
        </div>
    </div>
</section>

<section class="buy-section alt" id="orcamento">
    <div class="site-container">
        <h2 style="text-align:center;">Peça seu orçamento</h2>
        <p class="section-sub" style="text-align:center;">Informe a placa do seu veículo pra gente te dar um orçamento certeiro.</p>

        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'csrf'): ?>
            <p class="form-msg" style="background:#fdeaea;color:#b3261e;max-width:560px;margin:0 auto 16px;">Sessão expirada, tente novamente.</p>
        <?php elseif (isset($_GET['erro'])): ?>
            <p class="form-msg" style="background:#fdeaea;color:#b3261e;max-width:560px;margin:0 auto 16px;">Preencha todos os campos obrigatórios.</p>
        <?php endif; ?>

        <form action="/comprar/orcamento" method="post" class="buy-checkout-box" id="placa-wizard" data-csrf="<?= Csrf::token() ?>">
            <?= Csrf::field() ?>

            <div class="wizard-step is-active" data-step="0">
                <label for="wizard-plate">Placa do veículo</label>
                <input type="text" id="wizard-plate" name="plate" placeholder="ABC1D23" maxlength="8" style="text-transform:uppercase;">
                <button type="button" class="btn btn-primary" id="wizard-search-btn" style="width:100%;margin-top:10px;">Buscar</button>
                <p class="hint-text" id="wizard-search-status"></p>
            </div>

            <div class="wizard-step" data-step="1">
                <p class="hint-text">Não encontramos sua placa automaticamente ainda — só mais alguns dados rápidos:</p>
                <label for="wizard-name">Seu nome</label>
                <input type="text" id="wizard-name" name="name" value="<?= View::e($checkoutName) ?>">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="2">
                <label for="wizard-year">Ano modelo</label>
                <input type="text" id="wizard-year" name="year" placeholder="Ex: 2020">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="3">
                <label for="wizard-brand-search">Marca</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="wizard-brand-search" autocomplete="off" placeholder="Digite pra buscar...">
                    <div class="autocomplete-list" id="wizard-brand-suggestions"></div>
                </div>
                <input type="hidden" id="wizard-brand" name="brand">
                <div id="wizard-brand-custom-wrap" style="display:none;">
                    <label for="wizard-brand-custom">Qual a marca do seu veículo?</label>
                    <input type="text" id="wizard-brand-custom" placeholder="Digite a marca">
                </div>
                <button type="button" class="btn btn-primary wizard-next" id="wizard-brand-next" style="width:100%;margin-top:10px;" disabled>Próximo</button>
            </div>

            <div class="wizard-step" data-step="4">
                <div id="wizard-model-select-wrap">
                    <label for="wizard-model-select">Modelo</label>
                    <select id="wizard-model-select" disabled>
                        <option value="">Selecione a marca primeiro</option>
                    </select>
                </div>
                <div id="wizard-model-text-wrap" style="display:none;">
                    <label for="wizard-model-text">Modelo (se souber)</label>
                    <input type="text" id="wizard-model-text" placeholder="Ex: informe o modelo, se souber">
                </div>
                <input type="hidden" id="wizard-model" name="model">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="5">
                <label for="wizard-power">Potência do motor</label>
                <input type="text" id="wizard-power" name="power" placeholder="Ex: 460cv">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="6">
                <label>O motor é original de fábrica ou reprogramado (chip)?</label>
                <div class="buy-payment-methods">
                    <label><input type="radio" name="ecu_status" value="original" id="wizard-ecu-original"> Original</label>
                    <label><input type="radio" name="ecu_status" value="reprogramado" id="wizard-ecu-reprog"> Reprogramado</label>
                </div>
                <div id="wizard-reprogrammed-power-wrap" style="display:none;">
                    <label for="wizard-reprogrammed-power">Qual potência foi reprogramado?</label>
                    <input type="text" id="wizard-reprogrammed-power" name="reprogrammed_power" placeholder="Ex: 500cv">
                </div>
                <button type="button" class="btn btn-primary wizard-next" id="wizard-ecu-next" style="width:100%;margin-top:10px;" disabled>Próximo</button>
            </div>

            <div class="wizard-step" data-step="7">
                <label>O veículo possui sistema de ARLA?</label>
                <div class="buy-payment-methods">
                    <label><input type="radio" name="has_arla" value="sim" id="wizard-arla-sim"> Sim</label>
                    <label><input type="radio" name="has_arla" value="nao" id="wizard-arla-nao"> Não</label>
                </div>
                <div id="wizard-arla-notice" style="display:none;" class="buy-price-summary">
                    <p style="margin-top:0;">⚠️ Pra a economia funcionar, é obrigatório o sistema de ARLA estar em funcionamento.</p>
                    <label>
                        <input type="checkbox" id="wizard-arla-confirm"> Confirmo que o ARLA está funcionando corretamente
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" id="wizard-submit-btn" style="width:100%;margin-top:10px;display:none;">Ver meu orçamento</button>
            </div>
        </form>
    </div>
</section>

<script>window.ECO_VEHICLE_CATALOG = <?= json_encode($vehicleCatalog) ?>;</script>
