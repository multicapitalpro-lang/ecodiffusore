(function () {
    var kmInput = document.getElementById('calc-km');
    if (!kmInput) return;

    var CONSUMO_KM_L = 2.8;
    var PRECO_LITRO = 6.10;

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

        var gastoMensal = (km / CONSUMO_KM_L) * PRECO_LITRO;

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
    update();
})();

(function () {
    var carousel = document.getElementById('install-carousel');
    if (!carousel) return;

    var track = carousel.querySelector('.install-carousel-track');
    var slides = carousel.querySelectorAll('.install-slide');
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
})();
