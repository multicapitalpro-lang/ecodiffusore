(function () {
    var kmInput = document.getElementById('calc-km');
    if (!kmInput) return;

    var CONSUMO_KM_L = 2.8;

    var precoLitroInput = document.getElementById('calc-preco-litro');

    var fmt = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    var kmLabel = document.getElementById('calc-km-label');
    var ctaValue = document.getElementById('calc-cta-value');
    var fields = {
        min: { month: 'calc-min-month', year: 'calc-min-year', y5: 'calc-min-5y', pct: 0.05 },
        mid: { month: 'calc-mid-month', year: 'calc-mid-year', y5: 'calc-mid-5y', pct: 0.08 },
        avg: { month: 'calc-avg-month', year: 'calc-avg-year', y5: 'calc-avg-5y', pct: 0.12 },
        max: { month: 'calc-max-month', year: 'calc-max-year', y5: 'calc-max-5y', pct: 0.20 }
    };

    function update() {
        var km = parseInt(kmInput.value, 10);
        kmLabel.textContent = km.toLocaleString('pt-BR') + ' km/mês';

        var precoLitro = parseFloat(precoLitroInput.value) || 6.10;
        var gastoMensal = (km / CONSUMO_KM_L) * precoLitro;

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
            if (btn.disabled) return;
            var goto_ = btn.dataset.goto;
            goToStep(goto_ !== undefined ? parseInt(goto_, 10) : current + 1);
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
        goToStep(2);
    });

    // Marca -- campo de busca com sugestoes conforme digita. "Outra marca" abre um campo pra
    // informar qual marca de verdade (o valor digitado ali vira o "brand" enviado, nao o rotulo
    // generico "Outra marca").
    var brandSearchInput = document.getElementById('wizard-brand-search');
    var brandHidden = document.getElementById('wizard-brand');
    var brandSuggestions = document.getElementById('wizard-brand-suggestions');
    var brandCustomWrap = document.getElementById('wizard-brand-custom-wrap');
    var brandCustomInput = document.getElementById('wizard-brand-custom');
    var brandNextBtn = document.getElementById('wizard-brand-next');
    var brandNames = Object.keys(catalog);

    var modelSelectWrap = document.getElementById('wizard-model-select-wrap');
    var modelSelect = document.getElementById('wizard-model-select');
    var modelTextWrap = document.getElementById('wizard-model-text-wrap');
    var modelTextInput = document.getElementById('wizard-model-text');
    var modelHidden = document.getElementById('wizard-model');

    function renderBrandSuggestions(filter) {
        var f = filter.toLowerCase();
        var matches = brandNames.filter(function (name) { return name.toLowerCase().indexOf(f) !== -1; });
        brandSuggestions.innerHTML = '';

        if (!matches.length) {
            brandSuggestions.style.display = 'none';
            return;
        }

        matches.forEach(function (name) {
            var item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = name;
            item.addEventListener('click', function () { selectBrand(name); });
            brandSuggestions.appendChild(item);
        });
        brandSuggestions.style.display = 'block';
    }

    function updateBrandNext() {
        var isOther = brandSearchInput.value.trim() === 'Outra marca';
        brandNextBtn.disabled = isOther ? brandCustomInput.value.trim() === '' : brandHidden.value.trim() === '';
    }

    function populateModelStep(brandName) {
        var isKnown = Object.prototype.hasOwnProperty.call(catalog, brandName) && brandName !== 'Outra marca';
        modelHidden.value = '';

        if (isKnown) {
            modelSelectWrap.style.display = '';
            modelTextWrap.style.display = 'none';
            modelSelect.disabled = false;
            modelSelect.innerHTML = '';

            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Selecione...';
            modelSelect.appendChild(empty);

            catalog[brandName].forEach(function (model) {
                var opt = document.createElement('option');
                opt.value = model;
                opt.textContent = model;
                modelSelect.appendChild(opt);
            });
        } else {
            modelSelectWrap.style.display = 'none';
            modelTextWrap.style.display = '';
            modelTextInput.value = '';
        }
    }

    function selectBrand(name) {
        brandSearchInput.value = name;
        brandSuggestions.style.display = 'none';
        brandSuggestions.innerHTML = '';

        if (name === 'Outra marca') {
            brandHidden.value = brandCustomInput.value.trim();
            brandCustomWrap.style.display = '';
            brandCustomInput.focus();
        } else {
            brandHidden.value = name;
            brandCustomWrap.style.display = 'none';
            brandCustomInput.value = '';
        }

        updateBrandNext();
        populateModelStep(name);
    }

    brandSearchInput.addEventListener('input', function () {
        brandHidden.value = '';
        brandCustomWrap.style.display = 'none';
        updateBrandNext();
        renderBrandSuggestions(brandSearchInput.value.trim());
    });

    brandSearchInput.addEventListener('focus', function () {
        if (!brandSearchInput.value.trim()) renderBrandSuggestions('');
    });

    brandCustomInput.addEventListener('input', function () {
        brandHidden.value = brandCustomInput.value.trim();
        updateBrandNext();
    });

    document.addEventListener('click', function (e) {
        if (e.target !== brandSearchInput && !brandSuggestions.contains(e.target)) {
            brandSuggestions.style.display = 'none';
        }
    });

    modelSelect.addEventListener('change', function () { modelHidden.value = modelSelect.value; });
    modelTextInput.addEventListener('input', function () { modelHidden.value = modelTextInput.value.trim(); });

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

    // ARLA -- "Nao" libera o "Proximo" direto; "Sim" so libera apos confirmar o aviso.
    var arlaRadios = wizard.querySelectorAll('input[name=has_arla]');
    var arlaNotice = document.getElementById('wizard-arla-notice');
    var arlaConfirm = document.getElementById('wizard-arla-confirm');
    var arlaNextBtn = document.getElementById('wizard-arla-next-btn');

    function updateArlaNext() {
        var checked = wizard.querySelector('input[name=has_arla]:checked');
        if (!checked) { arlaNextBtn.style.display = 'none'; return; }
        if (checked.value === 'sim') {
            arlaNotice.style.display = '';
            arlaNextBtn.style.display = arlaConfirm.checked ? '' : 'none';
        } else {
            arlaNotice.style.display = 'none';
            arlaNextBtn.style.display = '';
        }
    }

    arlaRadios.forEach(function (radio) { radio.addEventListener('change', updateArlaNext); });
    arlaConfirm.addEventListener('change', updateArlaNext);

    // Ultimo passo -- calculo de economia/payback. Km mensal, km/litro e preco do diesel sao
    // obrigatorios (precisa disso pra estimar o gasto mensal); gasto mensal direto e opcional (se
    // informado, o backend usa ele no lugar do calculado, igual a calculadora da landing page).
    var kmMensalInput = document.getElementById('wizard-km-mensal');
    var kmLitroInput = document.getElementById('wizard-km-litro');
    var precoDieselInput = document.getElementById('wizard-preco-diesel');
    var finalSubmitBtn = document.getElementById('wizard-submit-btn');

    function updateFinalSubmit() {
        var ok = kmMensalInput.value.trim() !== '' && kmLitroInput.value.trim() !== '' && precoDieselInput.value.trim() !== '';
        finalSubmitBtn.disabled = !ok;
    }

    [kmMensalInput, kmLitroInput, precoDieselInput].forEach(function (el) {
        el.addEventListener('input', updateFinalSubmit);
    });
    updateFinalSubmit();

    // Ramo maquina agricola (Fase 45) -- tipo de maquina (com "Outro" abrindo campo livre, mesmo
    // padrao da "Outra marca" do ramo caminhao acima) e o envio final, que so libera quando as 3
    // fotos obrigatorias estiverem selecionadas.
    var machineTypeSelect = document.getElementById('wizard-machine-type');
    var machineTypeCustomWrap = document.getElementById('wizard-machine-type-custom-wrap');
    var machineTypeCustomInput = document.getElementById('wizard-machine-type-custom');
    var machineTypeNextBtn = document.getElementById('wizard-machine-type-next');

    function updateMachineTypeNext() {
        var isOther = machineTypeSelect.value === 'outro';
        machineTypeCustomWrap.style.display = isOther ? '' : 'none';
        machineTypeNextBtn.disabled = isOther ? machineTypeCustomInput.value.trim() === '' : machineTypeSelect.value === '';
    }

    if (machineTypeSelect) {
        machineTypeSelect.addEventListener('change', updateMachineTypeNext);
        machineTypeCustomInput.addEventListener('input', updateMachineTypeNext);
    }

    var machinePhotoGeneral = document.getElementById('wizard-machine-photo-general');
    var machinePhotoNameplate = document.getElementById('wizard-machine-photo-nameplate');
    var machinePhotoHose = document.getElementById('wizard-machine-photo-hose');
    var machineSubmitBtn = document.getElementById('wizard-machine-submit-btn');

    function updateMachineSubmit() {
        var ok = machinePhotoGeneral.files.length > 0 && machinePhotoNameplate.files.length > 0 && machinePhotoHose.files.length > 0;
        machineSubmitBtn.disabled = !ok;
    }

    if (machineSubmitBtn) {
        [machinePhotoGeneral, machinePhotoNameplate, machinePhotoHose].forEach(function (el) {
            el.addEventListener('change', updateMachineSubmit);
        });
    }
})();

// Autocomplete de cidade (br_cities, mesma base que o GeoMatch usa pro roteamento por
// proximidade) -- roda em qualquer pagina que tenha um campo marcado [data-city-autocomplete],
// sem depender de nenhum elemento especifico existir (diferente das IIFEs acima, que so fazem
// sentido dentro do wizard/calculadora). Sem isso o cliente podia digitar qualquer texto
// (bairro, cidade de outro pais, erro de digitacao) e o roteamento pro Vendedor mais proximo
// nunca encontrava ninguem no raio.
document.querySelectorAll('[data-city-autocomplete]').forEach(function (input) {
    var results = input.parentElement.querySelector('.autocomplete-results');
    if (!results) return;

    var timer = null;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) {
            results.hidden = true;
            results.innerHTML = '';
            return;
        }
        timer = setTimeout(function () {
            fetch('/cidades/buscar?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    results.innerHTML = '';
                    var cities = (res && res.data) || [];
                    if (!cities.length) {
                        results.innerHTML = '<div class="autocomplete-empty">Nenhuma cidade encontrada.</div>';
                        results.hidden = false;
                        return;
                    }
                    cities.forEach(function (city) {
                        var item = document.createElement('div');
                        item.className = 'autocomplete-item';
                        item.textContent = city.name + ' - ' + city.uf;
                        item.addEventListener('click', function () {
                            input.value = city.name;
                            results.hidden = true;
                        });
                        results.appendChild(item);
                    });
                    results.hidden = false;
                })
                .catch(function () {
                    results.hidden = true;
                });
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (e.target !== input && !results.contains(e.target)) {
            results.hidden = true;
        }
    });
});

function initCarousel(carouselId, trackSelector, slideSelector, options) {
    var carousel = document.getElementById(carouselId);
    if (!carousel) return;
    options = options || {};

    var track = carousel.querySelector(trackSelector);
    var slides = carousel.querySelectorAll(slideSelector);
    var dots = carousel.querySelectorAll('.carousel-dots button');
    var index = 0;

    function itemsPerView() {
        if (!slides.length) return 1;
        return Math.max(1, Math.round(carousel.clientWidth / slides[0].offsetWidth));
    }

    function maxIndex() {
        return Math.max(0, slides.length - itemsPerView());
    }

    function goTo(i) {
        var max = maxIndex();
        if (i < 0) i = max;
        if (i > max) i = 0;
        index = i;
        var slideWidth = slides[0].offsetWidth;
        track.style.transform = 'translateX(-' + (index * slideWidth) + 'px)';
        dots.forEach(function (d, di) { d.classList.toggle('is-active', di === index); });
    }

    carousel.querySelector('.prev').addEventListener('click', function () { goTo(index - 1); });
    carousel.querySelector('.next').addEventListener('click', function () { goTo(index + 1); });
    dots.forEach(function (d, di) { d.addEventListener('click', function () { goTo(di); }); });
    window.addEventListener('resize', function () { goTo(Math.min(index, maxIndex())); });

    if (options.autoplay) {
        var timer = setInterval(function () { goTo(index + 1); }, options.autoplay);
        carousel.addEventListener('mouseenter', function () { clearInterval(timer); });
        carousel.addEventListener('mouseleave', function () { timer = setInterval(function () { goTo(index + 1); }, options.autoplay); });
    }
}

// Chat widget do site (Fase 107) -- roda em toda pagina publica (layouts/site.php). Mesmo roteiro
// de decisao do bot de WhatsApp (Fase 106): coleta nome/WhatsApp, menu de 4 opcoes, pede cidade
// quando precisa rotear por regiao (GeoMatch, mesmo endpoint /cidades/buscar do autocomplete que
// ja existe em /comprar), e so mostra um link de WhatsApp de verdade no passo final -- o visitante
// nunca sai da pagina sozinho, so' quando ele mesmo toca no botao.
(function () {
    var widget = document.getElementById('site-chat-widget');
    if (!widget) return;

    var csrfToken = widget.dataset.csrf;
    var bubble = document.getElementById('site-chat-bubble');
    var panel = document.getElementById('site-chat-panel');
    var closeBtn = document.getElementById('site-chat-close');
    var messagesEl = document.getElementById('site-chat-messages');
    var inputArea = document.getElementById('site-chat-input-area');
    var greeted = false;

    function esc(text) {
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatText(text) {
        return esc(text).replace(/\*(.+?)\*/g, '<strong>$1</strong>');
    }

    function addBotMessage(text) {
        var el = document.createElement('div');
        el.className = 'site-chat-msg site-chat-msg-bot';
        el.innerHTML = formatText(text);
        messagesEl.appendChild(el);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function addUserMessage(text) {
        var el = document.createElement('div');
        el.className = 'site-chat-msg site-chat-msg-user';
        el.textContent = text;
        messagesEl.appendChild(el);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function post(step, data) {
        return fetch('/chat/mensagem', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ step: step, csrf_token: csrfToken }, data || {}))
        })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: 'Não consegui falar com o servidor, tenta de novo.' }; });
    }

    function showContatoForm() {
        inputArea.innerHTML =
            '<form id="site-chat-contato-form" class="site-chat-form">' +
                '<input type="text" name="name" placeholder="Seu nome" required>' +
                '<input type="text" name="whatsapp" placeholder="WhatsApp com DDD" required>' +
                '<div class="city-autocomplete-wrap">' +
                    '<input type="text" id="site-chat-city-input" name="city" placeholder="Sua cidade" autocomplete="off" required>' +
                    '<div class="autocomplete-results" id="site-chat-city-results" hidden></div>' +
                '</div>' +
                '<button type="submit" class="btn btn-primary">Continuar</button>' +
            '</form>';

        bindCityAutocomplete();

        document.getElementById('site-chat-contato-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var name = this.name.value.trim();
            var whatsapp = this.whatsapp.value.trim();
            var city = this.city.value.trim();
            if (!name || !whatsapp || !city) return;
            addUserMessage(name + ' — ' + whatsapp + ' — ' + city);
            post('contato', { name: name, whatsapp: whatsapp, city: city }).then(function (res) {
                if (!res.ok) {
                    addBotMessage(res.error || 'Algo deu errado, tenta de novo.');
                    showContatoForm();
                    return;
                }
                addBotMessage('Prazer, ' + name.split(' ')[0] + '! 🌱 Como posso te ajudar?');
                showMenu();
            });
        });
    }

    function showMenu() {
        inputArea.innerHTML =
            '<div class="site-chat-options">' +
                '<button type="button" class="site-chat-option" data-choice="1">1️⃣ Quero comprar / pedir um orçamento</button>' +
                '<button type="button" class="site-chat-option" data-choice="2">2️⃣ Já sou cliente — suporte</button>' +
                '<button type="button" class="site-chat-option" data-choice="3">3️⃣ Sou Licenciado ou Vendedor</button>' +
                '<button type="button" class="site-chat-option" data-choice="4">4️⃣ Falar com um atendente</button>' +
            '</div>';

        inputArea.querySelectorAll('.site-chat-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                addUserMessage(btn.textContent);
                post('opcao', { choice: btn.dataset.choice }).then(handleOpcaoResponse);
            });
        });
    }

    function handleOpcaoResponse(res) {
        if (!res.ok) {
            addBotMessage(res.error || 'Algo deu errado, tenta de novo.');
            showMenu();
            return;
        }
        if (res.next === 'final') {
            addBotMessage(res.message);
            showFinal(res.whatsapp_link);
        } else {
            addBotMessage('Não entendi 🤔 Escolha uma das opções abaixo:');
            showMenu();
        }
    }

    // Nome/WhatsApp/Cidade sao pedidos juntos logo no 1o passo (Fase 109) -- o roteamento por
    // regiao roda ali mesmo, antes do menu aparecer, entao esta funcao so' precisa ligar o
    // autocomplete no campo de cidade do formulario de contato (reaproveita o mesmo endpoint
    // /cidades/buscar do autocomplete que ja existe em /comprar).
    function bindCityAutocomplete() {
        var input = document.getElementById('site-chat-city-input');
        var results = document.getElementById('site-chat-city-results');
        var timer = null;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch('/cidades/buscar?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        results.innerHTML = '';
                        var cities = (res && res.data) || [];
                        if (!cities.length) {
                            results.innerHTML = '<div class="autocomplete-empty">Nenhuma cidade encontrada.</div>';
                            results.hidden = false;
                            return;
                        }
                        cities.forEach(function (c) {
                            var item = document.createElement('div');
                            item.className = 'autocomplete-item';
                            item.textContent = c.name + ' - ' + c.uf;
                            item.addEventListener('click', function () {
                                input.value = c.name;
                                results.hidden = true;
                            });
                            results.appendChild(item);
                        });
                        results.hidden = false;
                    })
                    .catch(function () { results.hidden = true; });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (e.target !== input && !results.contains(e.target)) {
                results.hidden = true;
            }
        });
    }

    function showFinal(waLink) {
        var html = '';
        if (waLink) {
            html += '<a href="' + waLink + '" target="_blank" rel="noopener" class="btn btn-whatsapp site-chat-wa-btn">💬 Abrir WhatsApp</a>';
        }
        html += '<button type="button" id="site-chat-restart">↺ Recomeçar conversa</button>';
        inputArea.innerHTML = html;

        var restart = document.getElementById('site-chat-restart');
        if (restart) {
            restart.addEventListener('click', function () {
                addBotMessage('🌱 Como mais posso te ajudar?');
                showMenu();
            });
        }
    }

    function openChat() {
        panel.hidden = false;
        bubble.classList.add('is-open');
        if (!greeted) {
            greeted = true;
            addBotMessage('🌱 Olá! Bem-vindo(a) à Ecodiffusore Brasil. Pra começar, me conta seu nome, WhatsApp e cidade:');
            showContatoForm();
        }
    }

    function closeChat() {
        panel.hidden = true;
        bubble.classList.remove('is-open');
    }

    bubble.addEventListener('click', function () {
        if (panel.hidden) {
            openChat();
        } else {
            closeChat();
        }
    });
    closeBtn.addEventListener('click', closeChat);
})();

initCarousel('depoimentos-carousel', '.depoimentos-carousel-track', '.depoimento-slide', { autoplay: 4500 });
