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
    var form = document.getElementById('buy-form');
    if (!form) return;

    var CARD_FEE_PCT = 0.0299;
    var ANTECIPACAO_MES_PCT = 0.017;
    var TAXA_FIXA = 0.49;

    var fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    var productSelect = document.getElementById('buy-product');
    var methodInputs = form.querySelectorAll('input[name=billing_type]');
    var installmentsRow = document.getElementById('buy-installments-row');
    var installmentsSelect = document.getElementById('buy-installments');
    var summary = document.getElementById('buy-price-summary');

    function basePrice() {
        var opt = productSelect.options[productSelect.selectedIndex];
        return opt ? parseFloat(opt.dataset.price || '0') : 0;
    }

    function chargeAmount(price, installments) {
        if (installments <= 1) {
            return (price + TAXA_FIXA) / (1 - CARD_FEE_PCT);
        }
        var mesesMedios = (installments + 1) / 2;
        var pct = CARD_FEE_PCT + ANTECIPACAO_MES_PCT * mesesMedios;
        return (price + TAXA_FIXA) / (1 - pct);
    }

    function selectedMethod() {
        var checked = form.querySelector('input[name=billing_type]:checked');
        return checked ? checked.value : 'PIX';
    }

    function update() {
        var price = basePrice();
        var method = selectedMethod();
        var isCard = method === 'CREDIT_CARD';
        installmentsRow.classList.toggle('is-visible', isCard);

        if (!isCard) {
            summary.innerHTML = 'Valor à vista: <strong>' + fmt.format(price) + '</strong>';
            return;
        }

        var n = parseInt(installmentsSelect.value, 10) || 1;
        var total = chargeAmount(price, n);
        var parcela = total / n;

        summary.innerHTML = n === 1
            ? 'Valor no cartão à vista: <strong>' + fmt.format(total) + '</strong>'
            : n + 'x de <strong>' + fmt.format(parcela) + '</strong> (total ' + fmt.format(total) + ')';
    }

    productSelect.addEventListener('change', update);
    installmentsSelect.addEventListener('change', update);
    methodInputs.forEach(function (input) { input.addEventListener('change', update); });
    update();
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
