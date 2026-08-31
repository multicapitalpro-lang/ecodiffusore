(function () {
    var kmInput = document.getElementById('calc-km');
    if (!kmInput) return;

    var CONSUMO_KM_L = 2.8;
    var PRECO_LITRO = 6.10;

    var fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    var kmLabel = document.getElementById('calc-km-label');
    var fields = {
        min: { month: 'calc-min-month', year: 'calc-min-year', y5: 'calc-min-5y', pct: 0.08 },
        avg: { month: 'calc-avg-month', year: 'calc-avg-year', y5: 'calc-avg-5y', pct: 0.12 },
        max: { month: 'calc-max-month', year: 'calc-max-year', y5: 'calc-max-5y', pct: 0.15 }
    };

    function update() {
        var km = parseInt(kmInput.value, 10);
        kmLabel.textContent = km.toLocaleString('pt-BR') + ' km';

        var gastoMensal = (km / CONSUMO_KM_L) * PRECO_LITRO;

        Object.keys(fields).forEach(function (key) {
            var f = fields[key];
            var mensal = gastoMensal * f.pct;
            document.getElementById(f.month).textContent = fmt.format(mensal);
            document.getElementById(f.year).textContent = fmt.format(mensal * 12);
            document.getElementById(f.y5).textContent = fmt.format(mensal * 12 * 5);
        });
    }

    kmInput.addEventListener('input', update);
    update();
})();
