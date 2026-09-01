(function () {
    var kmInput = document.getElementById('calc-km');
    if (!kmInput) return;

    var CONSUMO_KM_L = 2.8;

    var precoLitroInput = document.getElementById('calc-preco-litro');
    var gastoMensalInput = document.getElementById('calc-gasto-mensal');

    var fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    var kmLabel = document.getElementById('calc-km-label');
    var ctaValue = document.getElementById('calc-cta-value');
    var fields = {
        min: { month: 'calc-min-month', year: 'calc-min-year', y5: 'calc-min-5y', pct: 0.05 },
        avg: { month: 'calc-avg-month', year: 'calc-avg-year', y5: 'calc-avg-5y', pct: 0.10 },
        max: { month: 'calc-max-month', year: 'calc-max-year', y5: 'calc-max-5y', pct: 0.20 }
    };

    function update() {
        var km = parseInt(kmInput.value, 10);
        kmLabel.textContent = km.toLocaleString('pt-BR') + ' km/mês';

        // Se o caminhoneiro informou o gasto mensal direto, usa esse valor (mais preciso).
        // Senao, estima pelo km rodado x preco do diesel na regiao dele.
        var gastoDireto = parseFloat(gastoMensalInput.value);
        var precoLitro = parseFloat(precoLitroInput.value) || 6.10;
        var gastoMensal = gastoDireto > 0 ? gastoDireto : (km / CONSUMO_KM_L) * precoLitro;

        Object.keys(fields).forEach(function (key) {
            var f = fields[key];
            var mensal = gastoMensal * f.pct;
            document.getElementById(f.month).textContent = fmt.format(mensal);
            document.getElementById(f.year).textContent = fmt.format(mensal * 12);
            document.getElementById(f.y5).textContent = fmt.format(mensal * 12 * 5);

            if (key === 'avg' && ctaValue) {
                ctaValue.textContent = fmt.format(mensal);
            }
        });
    }

    kmInput.addEventListener('input', update);
    precoLitroInput.addEventListener('input', update);
    gastoMensalInput.addEventListener('input', update);
    update();
})();

(function () {
    document.querySelectorAll('dialog[data-autoopen]').forEach(function (dialog) {
        dialog.showModal();
    });
})();

(function () {
    // Navegadores bloqueiam autoplay com som -- o video comeca mudo e mostra um botao
    // pra ativar o som com 1 clique, ja que autoplay-com-audio nao e tecnicamente possivel.
    var video = document.getElementById('hero-video');
    var soundBtn = document.getElementById('hero-video-sound');
    if (!video || !soundBtn) return;

    soundBtn.addEventListener('click', function () {
        video.muted = false;
        video.play();
        soundBtn.style.display = 'none';
    });
})();

(function () {
    var wizard = document.getElementById('placa-wizard');
    if (!wizard) return;

    var csrfToken = wizard.dataset.csrf;
    var steps = wizard.querySelectorAll('.wizard-step');
    var current = 0;
    var catalog = window.ECO_VEHICLE_CATALOG || {};

    function goToStep(i) {
        current = i;
        steps.forEach(function (step, si) { step.classList.toggle('is-active', si === i); });
    }

    wizard.querySelectorAll('.wizard-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!btn.disabled) goToStep(current + 1);
        });
    });

    var plateInput = document.getElementById('wizard-plate');
    var searchBtn = document.getElementById('wizard-search-btn');
    var searchStatus = document.getElementById('wizard-search-status');

    searchBtn.addEventListener('click', async function () {
        var plate = plateInput.value.trim();
        if (!plate) return;

        searchStatus.textContent = 'Buscando...';
        searchBtn.disabled = true;

        try {
            var formData = new FormData();
            formData.set('csrf_token', csrfToken);
            formData.set('plate', plate);
            var res = await fetch('/comprar/buscar-placa', { method: 'POST', body: formData });
            await res.json();
            // Dataflow ainda e' stub (sempre "nao encontrado") -- quando integrada de verdade, o
            // retorno "found" pode pre-preencher os campos abaixo e pular direto pro orcamento.
            searchStatus.textContent = '';
        } catch (e) {
            searchStatus.textContent = '';
        }

        searchBtn.disabled = false;
        goToStep(1);
    });

    // Marca -> Modelo em cascata.
    var brandSelect = document.getElementById('wizard-brand');
    var modelSelect = document.getElementById('wizard-model');

    brandSelect.addEventListener('change', function () {
        var models = catalog[brandSelect.value] || [];
        modelSelect.innerHTML = '';

        if (!models.length) {
            modelSelect.disabled = true;
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione a marca primeiro';
            modelSelect.appendChild(placeholder);
            return;
        }

        modelSelect.disabled = false;
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Selecione...';
        modelSelect.appendChild(empty);

        models.forEach(function (model) {
            var opt = document.createElement('option');
            opt.value = model;
            opt.textContent = model;
            modelSelect.appendChild(opt);
        });
    });

    // Original ou reprogramado -- so libera o "Proximo" com a escolha feita, e so exige a potencia
    // reprogramada quando "Reprogramado" for selecionado.
    var ecuRadios = wizard.querySelectorAll('input[name=ecu_status]');
    var reprogWrap = document.getElementById('wizard-reprogrammed-power-wrap');
    var reprogInput = document.getElementById('wizard-reprogrammed-power');
    var ecuNextBtn = document.getElementById('wizard-ecu-next');

    function updateEcuNext() {
        var checked = wizard.querySelector('input[name=ecu_status]:checked');
        if (!checked) { ecuNextBtn.disabled = true; return; }
        if (checked.value === 'reprogramado') {
            reprogWrap.style.display = '';
            ecuNextBtn.disabled = reprogInput.value.trim() === '';
        } else {
            reprogWrap.style.display = 'none';
            ecuNextBtn.disabled = false;
        }
    }

    ecuRadios.forEach(function (radio) { radio.addEventListener('change', updateEcuNext); });
    reprogInput.addEventListener('input', updateEcuNext);

    // ARLA -- "Nao" libera o orcamento direto; "Sim" so libera apos confirmar o aviso.
    var arlaRadios = wizard.querySelectorAll('input[name=has_arla]');
    var arlaNotice = document.getElementById('wizard-arla-notice');
    var arlaConfirm = document.getElementById('wizard-arla-confirm');
    var submitBtn = document.getElementById('wizard-submit-btn');

    function updateArlaSubmit() {
        var checked = wizard.querySelector('input[name=has_arla]:checked');
        if (!checked) { submitBtn.style.display = 'none'; return; }
        if (checked.value === 'sim') {
            arlaNotice.style.display = '';
            submitBtn.style.display = arlaConfirm.checked ? '' : 'none';
        } else {
            arlaNotice.style.display = 'none';
            submitBtn.style.display = '';
        }
    }

    arlaRadios.forEach(function (radio) { radio.addEventListener('change', updateArlaSubmit); });
    arlaConfirm.addEventListener('change', updateArlaSubmit);
})();

function initCarousel(carouselId, trackSelector, slideSelector) {
    var carousel = document.getElementById(carouselId);
    if (!carousel) return;

    var track = carousel.querySelector(trackSelector);
    var slides = carousel.querySelectorAll(slideSelector);
    var dots = carousel.querySelectorAll('.install-carousel-dots button');
    var index = 0;

    function goTo(i) {
        index = (i + slides.length) % slides.length;
        track.style.transform = 'translateX(-' + (index * 100) + '%)';
        dots.forEach(function (d, di) { d.classList.toggle('is-active', di === index); });
    }

    carousel.querySelector('.prev').addEventListener('click', function () { goTo(index - 1); });
    carousel.querySelector('.next').addEventListener('click', function () { goTo(index + 1); });
    dots.forEach(function (d, di) { d.addEventListener('click', function () { goTo(di); }); });
}

initCarousel('install-carousel', '.install-carousel-track', '.install-slide');
initCarousel('depoimentos-carousel', '.depoimentos-carousel-track', '.depoimento-slide');
