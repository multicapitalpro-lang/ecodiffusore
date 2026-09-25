/* Fase 126: preview ao vivo da Calculadora de Economia de Diesel, espelhando o comportamento
   "recalcula a cada tecla" da planilha/Apps Script original -- so' o preview de topo (media km/l,
   gasto mensal/anual por veiculo, comparativo antes/depois). Os resultados completos da frota
   (mes a mes, 5 anos, PDF) continuam vindo do servidor no submit, como ja funcionava. */
(function () {
    var page = document.querySelector('.dc-page');
    if (!page) return;

    function parseBr(raw) {
        var value = (raw || '').trim();
        if (value === '') return 0;
        if (value.indexOf(',') !== -1) {
            value = value.replace(/\./g, '').replace(',', '.');
        }
        return parseFloat(value) || 0;
    }

    function fmtMoney(brl, currency, rate) {
        if (currency === 'PYG' && rate > 0) {
            return '₲ ' + Math.round(brl * rate).toLocaleString('pt-BR');
        }
        return 'R$ ' + brl.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtKml(value) {
        if (!value) return '—';
        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    var rate = parseFloat(page.getAttribute('data-rate') || '0');

    function currentCurrency() {
        var sel = document.getElementById('currency');
        return sel ? sel.value : 'BRL';
    }

    function recalc() {
        var currency = currentCurrency();
        var gastoDieselInput = parseBr(document.getElementById('gasto_diesel').value);
        var precoDieselInput = parseBr(document.getElementById('preco_diesel').value);
        var gastoDiesel = currency === 'PYG' && rate > 0 ? gastoDieselInput / rate : gastoDieselInput;
        var precoDiesel = currency === 'PYG' && rate > 0 ? precoDieselInput / rate : precoDieselInput;
        var pct = parseFloat(document.getElementById('pct_economia').value) || 0;

        var modoEl = document.querySelector('[data-modo-radio]:checked');
        var modo = modoEl ? modoEl.value : 'km_rodados';

        var litros = precoDiesel > 0 ? gastoDiesel / precoDiesel : 0;
        var mediaAtual = 0;
        if (modo === 'media') {
            var mediaInput = parseBr(document.getElementById('media_kml').value);
            mediaAtual = mediaInput;
        } else {
            var kmRodados = parseBr(document.getElementById('km_rodados').value);
            mediaAtual = litros > 0 ? kmRodados / litros : 0;
        }

        var fator = pct < 100 ? 1 / (1 - pct / 100) : 0;
        var mediaComEco = mediaAtual * fator;
        var gastoComEco = gastoDiesel * (1 - pct / 100);

        var haveBase = gastoDiesel > 0 && precoDiesel > 0 && mediaAtual > 0;

        setText('dc-media-atual', haveBase ? fmtKml(mediaAtual) + ' km/l' : '— km/l');
        setText('dc-gasto-mensal', haveBase ? fmtMoney(gastoDiesel, currency, rate) : (currency === 'PYG' ? '₲ —' : 'R$ —'));
        setText('dc-gasto-anual', haveBase ? fmtMoney(gastoDiesel * 12, currency, rate) : (currency === 'PYG' ? '₲ —' : 'R$ —'));
        setText('dc-antes-kml', haveBase ? fmtKml(mediaAtual) : '—');
        setText('dc-depois-kml', haveBase ? fmtKml(mediaComEco) : '—');
        setText('dc-gasto-sem', haveBase ? fmtMoney(gastoDiesel, currency, rate) : (currency === 'PYG' ? '₲ —' : 'R$ —'));
        setText('dc-gasto-com', haveBase ? fmtMoney(gastoComEco, currency, rate) : (currency === 'PYG' ? '₲ —' : 'R$ —'));
    }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    ['gasto_diesel', 'preco_diesel', 'km_rodados', 'media_kml', 'pct_economia'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', recalc);
    });
    document.querySelectorAll('[data-modo-radio]').forEach(function (radio) {
        radio.addEventListener('change', recalc);
    });
    var currencySelect = document.getElementById('currency');
    if (currencySelect) currencySelect.addEventListener('change', recalc);

    recalc();
})();
