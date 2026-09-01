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

        <div class="buy-checkout-box" id="placa-wizard" data-csrf="<?= Csrf::token() ?>">
            <div class="wizard-step is-active" data-step="0">
                <label for="wizard-plate">Placa do veículo</label>
                <input type="text" id="wizard-plate" placeholder="ABC1D23" maxlength="8" style="text-transform:uppercase;">
                <button type="button" class="btn btn-primary" id="wizard-search-btn" style="width:100%;margin-top:10px;">Buscar</button>
                <p class="hint-text" id="wizard-search-status"></p>
            </div>

            <div class="wizard-step" data-step="1">
                <p class="hint-text">Não encontramos sua placa automaticamente ainda — só mais alguns dados rápidos:</p>
                <label for="wizard-year">Ano modelo</label>
                <input type="text" id="wizard-year" placeholder="Ex: 2020">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="2">
                <label for="wizard-brand">Marca</label>
                <input type="text" id="wizard-brand" placeholder="Ex: Scania, Volvo, DAF, Iveco...">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="3">
                <label for="wizard-power">Potência do motor</label>
                <input type="text" id="wizard-power" placeholder="Ex: 460cv">
                <button type="button" class="btn btn-primary wizard-next" style="width:100%;margin-top:10px;">Próximo</button>
            </div>

            <div class="wizard-step" data-step="4">
                <label>O motor é original de fábrica ou reprogramado (chip)?</label>
                <div class="buy-payment-methods">
                    <label><input type="radio" name="wizard-ecu" value="original"> Original</label>
                    <label><input type="radio" name="wizard-ecu" value="reprogramado"> Reprogramado</label>
                </div>
                <form action="/comprar/orcamento" method="post" id="wizard-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="plate" id="wizard-plate-hidden">
                    <input type="hidden" name="year" id="wizard-year-hidden">
                    <input type="hidden" name="brand" id="wizard-brand-hidden">
                    <input type="hidden" name="power" id="wizard-power-hidden">
                    <input type="hidden" name="ecu_status" id="wizard-ecu-hidden">
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px;">Ver meu orçamento</button>
                </form>
            </div>
        </div>
    </div>
</section>
