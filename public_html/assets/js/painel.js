function bindItemsTable(root) {
    var body = root.querySelector('#items-body');
    if (!body || body.dataset.itemsBound) return;
    body.dataset.itemsBound = '1';

    var addBtn = root.querySelector('#add-item-row');
    var totalEl = root.querySelector('#order-total');
    var table = body.closest('table');
    var tiers = [];
    try {
        tiers = table && table.dataset.tiers ? JSON.parse(table.dataset.tiers) : [];
    } catch (e) {
        tiers = [];
    }

    // Preco unitario nao depende mais do produto escolhido, so da quantidade total do item (Fase
    // 24 -- tabela de precos por atacado): a faixa aplicada e a de maior min_qty que ainda seja <=
    // a quantidade. So preenche automaticamente -- o campo continua editavel pra excecoes.
    function priceForQty(qty) {
        var match = null;
        tiers.forEach(function (t) {
            if (t.min_qty <= qty && (!match || t.min_qty > match.min_qty)) match = t;
        });
        return match ? match.unit_price : null;
    }

    function fmt(n) {
        return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcRow(row) {
        var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        var price = parseFloat(row.querySelector('.item-price').value) || 0;
        var subtotal = qty * price;
        row.querySelector('.item-subtotal').textContent = fmt(subtotal);
        return subtotal;
    }

    function recalcAll() {
        var total = 0;
        body.querySelectorAll('.item-row').forEach(function (row) {
            total += recalcRow(row);
        });
        if (totalEl) totalEl.textContent = fmt(total);
    }

    function applyTierPrice(row) {
        if (!tiers.length) return;
        var qty = parseInt(row.querySelector('.item-qty').value, 10) || 1;
        var price = priceForQty(qty);
        if (price !== null) {
            row.querySelector('.item-price').value = price.toFixed(2);
        }
    }

    body.addEventListener('change', function (e) {
        if (e.target.classList.contains('item-product')) {
            var row = e.target.closest('.item-row');
            if (row) applyTierPrice(row);
        }
        recalcAll();
    });

    body.addEventListener('input', function (e) {
        if (e.target.classList.contains('item-qty')) {
            applyTierPrice(e.target.closest('.item-row'));
        }
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
            recalcAll();
        }
    });

    body.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove-row')) {
            var rows = body.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                recalcAll();
            }
        }
    });

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var firstRow = body.querySelector('.item-row');
            var clone = firstRow.cloneNode(true);
            clone.querySelectorAll('select, input').forEach(function (el) {
                if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                } else if (el.classList.contains('item-qty')) {
                    el.value = 1;
                } else {
                    el.value = '';
                }
            });
            clone.querySelector('.item-subtotal').textContent = fmt(0);
            applyTierPrice(clone);
            body.appendChild(clone);
        });
    }

    recalcAll();
}

function bindDashboardChart(root) {
    var canvas = root.querySelector('#dashboard-daily-chart');
    var dataScript = root.querySelector('#dashboard-daily-chart-data');
    if (!canvas || !dataScript || canvas.dataset.chartBound || typeof Chart === 'undefined') return;
    canvas.dataset.chartBound = '1';

    var data;
    try {
        data = JSON.parse(dataScript.textContent);
    } catch (e) {
        return;
    }

    var ctx = canvas.getContext('2d');
    var gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, 'rgba(110, 166, 44, 0.24)');
    gradient.addColorStop(1, 'rgba(110, 166, 44, 0)');

    function fmtCurrency(v) {
        return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Período atual',
                    data: data.current,
                    borderColor: '#6ea62c',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6ea62c',
                    pointBorderWidth: 2,
                    borderWidth: 2.5
                },
                {
                    label: 'Período anterior',
                    data: data.previous,
                    borderColor: '#c9cfdc',
                    backgroundColor: 'transparent',
                    borderDash: [4, 3],
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1.75
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#2c2c2a',
                    titleColor: '#f0efec',
                    bodyColor: '#c3c2b7',
                    padding: 10,
                    cornerRadius: 6,
                    callbacks: {
                        label: function (item) {
                            return item.dataset.label + ': ' + fmtCurrency(item.raw);
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#8a93ab', font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: {
                    beginAtZero: true,
                    grid: { color: '#eef0f5' },
                    ticks: {
                        color: '#8a93ab',
                        font: { size: 11 },
                        callback: function (v) {
                            return v >= 1000 ? 'R$ ' + (v / 1000).toFixed(1).replace('.0', '') + 'k' : 'R$ ' + v;
                        }
                    }
                }
            }
        }
    });
}

function bindDashboardSituacaoChart(root) {
    var canvas = root.querySelector('#dashboard-situacao-chart');
    var dataScript = root.querySelector('#dashboard-situacao-chart-data');
    if (!canvas || !dataScript || canvas.dataset.chartBound || typeof Chart === 'undefined') return;
    canvas.dataset.chartBound = '1';

    var data;
    try {
        data = JSON.parse(dataScript.textContent);
    } catch (e) {
        return;
    }

    function fmtCurrency(v) {
        return 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Total',
                    data: data.total,
                    borderColor: '#6ea62c',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 2.5
                },
                {
                    label: 'Pendente',
                    data: data.pending,
                    borderColor: '#d69a1e',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1.75
                },
                {
                    label: 'Pago',
                    data: data.paid,
                    borderColor: '#2a5c9a',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 1.75
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#2c2c2a',
                    titleColor: '#f0efec',
                    bodyColor: '#c3c2b7',
                    padding: 10,
                    cornerRadius: 6,
                    callbacks: {
                        label: function (item) {
                            return item.dataset.label + ': ' + fmtCurrency(item.raw);
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#8a93ab', font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: {
                    beginAtZero: true,
                    grid: { color: '#eef0f5' },
                    ticks: {
                        color: '#8a93ab',
                        font: { size: 11 },
                        callback: function (v) {
                            return v >= 1000 ? 'R$ ' + (v / 1000).toFixed(1).replace('.0', '') + 'k' : 'R$ ' + v;
                        }
                    }
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    bindItemsTable(document);
    bindDashboardChart(document);
    bindDashboardSituacaoChart(document);
});

(function () {
    // Menu mobile (drawer)
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('painel-menu-toggle');
        var sidebar = document.getElementById('painel-sidebar');
        var overlay = document.getElementById('painel-overlay');
        if (!toggle || !sidebar || !overlay) return;

        function closeSidebar() {
            sidebar.classList.remove('is-open');
            overlay.classList.remove('is-open');
        }

        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
            overlay.classList.toggle('is-open');
        });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', closeSidebar);
        });
    });

    // Modais (dialog nativo)
    document.addEventListener('DOMContentLoaded', function () {
        function bindModalClose(root) {
            root.querySelectorAll('[data-modal-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dialog = btn.closest('dialog');
                    if (dialog) dialog.close();
                    document.body.classList.remove('modal-open');
                });
            });
        }

        function bindAjaxForms(root) {
            root.querySelectorAll('form.ajax-form').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    var submitBtn = e.submitter || form.querySelector('button[type=submit]');
                    form.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = true; });

                    form.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; });
                    form.querySelectorAll('.has-error').forEach(function (el) { el.classList.remove('has-error'); });

                    var formData = new FormData(form);
                    if (submitBtn && submitBtn.name) {
                        formData.set(submitBtn.name, submitBtn.value);
                    }

                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.ok) {
                                window.location.href = data.redirect;
                                return;
                            }
                            Object.keys(data.errors || {}).forEach(function (field) {
                                var errEl = form.querySelector('[data-error-for="' + field + '"]');
                                if (errEl) errEl.textContent = data.errors[field];
                                var input = form.querySelector('[name="' + field + '"]');
                                if (input) input.classList.add('has-error');
                            });
                            form.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = false; });
                        })
                        .catch(function () {
                            alert('Erro ao salvar. Verifique sua conexão e tente novamente.');
                            form.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = false; });
                        });
                });
            });
        }

        function bindModalOpen(root) {
            root.querySelectorAll('[data-modal-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var modal = document.getElementById(btn.getAttribute('data-modal-open'));
                    if (modal) {
                        modal.showModal();
                        document.body.classList.add('modal-open');
                    }
                });
            });
        }

        bindModalOpen(document);
        bindModalClose(document);

        // Trava o scroll da pagina de fundo enquanto um dialog esta aberto -- sem isso, no mobile
        // (onde o dialog cobre a tela via 100dvh) a pagina por tras ainda rola atras do modal em
        // alguns navegadores/engines, ficando visivel nas bordas. O evento "close" do <dialog> nao
        // faz bubble, entao cada dialog precisa do proprio listener.
        document.querySelectorAll('dialog').forEach(function (dialog) {
            dialog.addEventListener('close', function () {
                document.body.classList.remove('modal-open');
            });
        });

        document.querySelectorAll('dialog[data-autoopen]').forEach(function (dialog) {
            dialog.showModal();
            document.body.classList.add('modal-open');
        });

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) {
                    dialog.close();
                    document.body.classList.remove('modal-open');
                }
            });
        });

        // Area de anexos (arrastar e soltar)
        function bindFileDrop(root) {
            root.querySelectorAll('[data-file-drop]').forEach(function (drop) {
                var input = drop.querySelector('input[type=file]');
                var list = drop.parentElement.querySelector('[data-file-list]');
                var label = drop.querySelector('[data-file-drop-label]');

                function renderList() {
                    list.innerHTML = '';
                    Array.from(input.files).forEach(function (file) {
                        var li = document.createElement('li');
                        li.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
                        list.appendChild(li);
                    });
                    if (label) {
                        label.textContent = input.files.length
                            ? input.files.length + ' arquivo(s) selecionado(s) — clique para trocar'
                            : 'Solte seus arquivos aqui ou clique para adicionar (PDF, JPG, PNG — até 5MB cada)';
                    }
                }

                input.addEventListener('change', renderList);

                ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
                    drop.addEventListener(evt, function (e) {
                        e.preventDefault();
                        drop.classList.toggle('is-dragover', evt === 'dragover');
                    });
                });
                drop.addEventListener('drop', function (e) {
                    if (e.dataTransfer.files.length) {
                        input.files = e.dataTransfer.files;
                        renderList();
                    }
                });
            });
        }

        bindFileDrop(document);
        bindAjaxForms(document);
        bindPropostaFacil(document);
        bindUserRoleFields(document);
        bindChargeForms(document);
        bindCityAutocomplete(document);

        // Criacao/edicao de usuario/pedido num modal: carrega o form via fetch (fragmento sem
        // layout) em vez de navegar pra outra pagina -- reaproveitado por Usuarios e Pedidos,
        // que ganham o modo ?fragment=1 nos respectivos Controllers (renderiza so o conteudo,
        // sem o layout do painel) pra isso funcionar.
        function openFragmentModal(dialogId, contentId, url, title) {
            var modal = document.getElementById(dialogId);
            var content = document.getElementById(contentId);
            if (!modal || !content) return;

            content.innerHTML = '<div class="modal-header"><h2>' + title + '</h2>'
                + '<button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>'
                + '<div class="modal-body"><p class="hint-text">Carregando...</p></div>';
            bindModalClose(content);
            modal.showModal();
            document.body.classList.add('modal-open');

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    content.innerHTML = html;
                    bindModalOpen(content);
                    bindModalClose(content);
                    bindAjaxForms(content);
                    bindFileDrop(content);
                    bindItemsTable(content);
                    bindPropostaFacil(content);
                    bindUserRoleFields(content);
                    bindChargeForms(content);
                    bindCityAutocomplete(content);
                })
                .catch(function () {
                    var body = content.querySelector('.modal-body');
                    if (body) body.innerHTML = '<p class="form-msg form-msg-erro">Erro ao carregar. Tente novamente.</p>';
                });
        }

        // Form de "Gerar cobranca" (Pedido/Orcamento): so mostra o seletor de parcelas (e a tabela
        // de parcelas, se houver) quando o meio escolhido e' Cartao de credito -- Pix/Boleto sao
        // sempre a vista, sem parcela. A tabela em si e' toda computada no servidor (mesmo padrao
        // ja usado em Proposta Facil, ver proposta/resultado.php) -- aqui so mostra/esconde.
        // Bindavel pelo mesmo motivo das outras (form carrega via fetch no modal de detalhe).
        function bindChargeForms(root) {
            root.querySelectorAll('.charge-form').forEach(function (form) {
                var billingSelect = form.querySelector('.charge-billing-type');
                var installmentsSelect = form.querySelector('.charge-installments');
                if (!billingSelect || !installmentsSelect) return;
                var previewTable = form.parentNode.querySelector('.charge-preview-table');

                function update() {
                    var isCard = billingSelect.value === 'CREDIT_CARD';
                    installmentsSelect.style.display = isCard ? '' : 'none';
                    if (previewTable) previewTable.hidden = !isCard;
                }

                billingSelect.addEventListener('change', update);
                update();
            });
        }

        // Autocomplete de cidade (br_cities, mesma base que o GeoMatch usa pro roteamento por
        // proximidade em /comprar) -- usado nos formularios de Usuario e Cliente (campo Cidade),
        // pra so aceitar municipio brasileiro real em vez de texto livre. Se o input tiver
        // data-city-uf-target, preenche tambem o campo de UF ao selecionar (esses formularios,
        // diferente do popup publico, ja tem um campo UF separado pra desambiguar). Bindavel
        // (nao <script> inline) porque esses forms sao carregados via fetch no modal de
        // Novo/Editar usuario/cliente -- innerHTML nao executa <script>.
        function bindCityAutocomplete(root) {
            root.querySelectorAll('[data-city-autocomplete]').forEach(function (input) {
                var results = input.parentElement.querySelector('.autocomplete-results');
                if (!results || input.dataset.cityBound) return;
                input.dataset.cityBound = '1';

                var ufTarget = input.dataset.cityUfTarget
                    ? root.querySelector('#' + input.dataset.cityUfTarget) || document.getElementById(input.dataset.cityUfTarget)
                    : null;

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
                                        if (ufTarget) ufTarget.value = city.uf;
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
        }

        // Form de usuario: mostra "Supervisor responsavel" so pra Licenciado e "Como pagar este
        // Vendedor" so pra Vendedor, conforme o papel escolhido no <select>. Bindavel (nao <script>
        // inline na view) pelo mesmo motivo de bindPropostaFacil acima -- innerHTML nao executa
        // <script>, e este form e carregado via fetch no modal de Novo/Editar usuario.
        function bindUserRoleFields(root) {
            var roleSelect = root.querySelector('#role_id');
            if (!roleSelect) return;

            var supervisorWrap = root.querySelector('#supervisor-field-wrap');
            var vendedorWrap = root.querySelector('#vendedor-commission-wrap');
            var commissionPctWrap = root.querySelector('#commission-pct-wrap');
            var licenciadoNote = root.querySelector('#licenciado-commission-note');
            var screensWrap = root.querySelector('#screens-permissions-wrap');

            function update() {
                var opt = roleSelect.options[roleSelect.selectedIndex];
                var slug = opt ? opt.dataset.slug : null;
                if (supervisorWrap) supervisorWrap.style.display = slug === 'licenciado' ? '' : 'none';
                if (vendedorWrap) vendedorWrap.style.display = slug === 'vendedor' ? '' : 'none';
                if (commissionPctWrap) commissionPctWrap.style.display = slug === 'licenciado' ? 'none' : '';
                if (licenciadoNote) licenciadoNote.style.display = slug === 'licenciado' ? '' : 'none';
                if (screensWrap) screensWrap.style.display = (slug === 'gestor' || slug === 'vendedor') ? '' : 'none';
            }

            roleSelect.addEventListener('change', update);
            update();
        }

        // Proposta Facil: formulario unico (comprador + veiculo + consumo) -> resultado, tudo dentro
        // do mesmo dialog (ver openFragmentModal acima). Precisa ser uma funcao "bindavel" (chamada
        // de novo a cada fragmento carregado) em vez de um <script> inline na view, porque innerHTML
        // nao executa <script> -- mesmo padrao ja usado por bindAjaxForms/bindItemsTable/bindFileDrop.
        function bindPropostaFacil(root) {
            var form = root.querySelector('#proposta-form');
            var novaBtn = root.querySelector('#btn-proposta-nova');

            // Previa do preco (Fase 24: tabela por quantidade) -- atualiza conforme a pessoa digita
            // a quantidade, so pra dar uma nocao antes de gerar a proposta de verdade.
            var qtyInput = root.querySelector('#quantidade');
            var pricePreview = root.querySelector('#proposta-price-preview');
            if (form && qtyInput && pricePreview) {
                var tiers = [];
                try {
                    tiers = form.dataset.tiers ? JSON.parse(form.dataset.tiers) : [];
                } catch (e) {
                    tiers = [];
                }

                var updatePreview = function () {
                    var qty = parseInt(qtyInput.value, 10) || 1;
                    var match = null;
                    tiers.forEach(function (t) {
                        if (t.min_qty <= qty && (!match || t.min_qty > match.min_qty)) match = t;
                    });
                    if (!match) {
                        pricePreview.textContent = '';
                        return;
                    }
                    var total = match.unit_price * qty;
                    var fmt = function (n) { return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
                    pricePreview.textContent = qty + 'x ' + fmt(match.unit_price) + ' = ' + fmt(total);
                };

                qtyInput.addEventListener('input', updatePreview);
                updatePreview();
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var submitBtn = form.querySelector('button[type=submit]');
                    if (submitBtn) submitBtn.disabled = true;

                    root.querySelectorAll('#proposta-form .field-error').forEach(function (p) { p.textContent = ''; });
                    var errorBox = root.querySelector('#proposta-form-error');
                    if (errorBox) { errorBox.hidden = true; errorBox.textContent = ''; }

                    var data = new FormData(form);
                    fetch(form.getAttribute('action'), {
                        method: 'POST',
                        body: data,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res.ok) {
                                if (form.dataset.modal === '1') {
                                    openFragmentModal('modal-proposta-facil', 'modal-proposta-facil-content', res.redirect + '?fragment=1', 'Proposta Fácil');
                                } else {
                                    window.location.href = res.redirect;
                                }
                                return;
                            }
                            Object.keys(res.errors || {}).forEach(function (field) {
                                if (field === '_geral') {
                                    if (errorBox) { errorBox.hidden = false; errorBox.textContent = res.errors._geral; }
                                    return;
                                }
                                var el = root.querySelector('[data-error-for="' + field + '"]');
                                if (el) el.textContent = res.errors[field];
                            });
                            if (submitBtn) submitBtn.disabled = false;
                        })
                        .catch(function () {
                            alert('Erro ao gerar a proposta. Verifique sua conexão e tente novamente.');
                            if (submitBtn) submitBtn.disabled = false;
                        });
                });
            }

            if (novaBtn) {
                novaBtn.addEventListener('click', function () {
                    openFragmentModal('modal-proposta-facil', 'modal-proposta-facil-content', '/painel/proposta-facil?fragment=1', 'Proposta Fácil');
                });
            }
        }

        var propostaFacilBtn = document.getElementById('btn-proposta-facil');
        if (propostaFacilBtn) {
            propostaFacilBtn.addEventListener('click', function () {
                openFragmentModal('modal-proposta-facil', 'modal-proposta-facil-content', '/painel/proposta-facil?fragment=1', 'Proposta Fácil');
            });
        }

        document.querySelectorAll('[data-edit-user]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openFragmentModal('modal-user-edit', 'modal-user-edit-content', '/painel/usuarios/' + btn.getAttribute('data-edit-user') + '/editar?fragment=1', 'Editar usuário');
            });
        });

        var newUserBtn = document.getElementById('btn-new-user');
        if (newUserBtn) {
            newUserBtn.addEventListener('click', function () {
                openFragmentModal('modal-user-edit', 'modal-user-edit-content', '/painel/usuarios/novo?fragment=1', 'Novo usuário');
            });
        }

        document.querySelectorAll('[data-view-order]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-view-order');
                openFragmentModal('modal-order-detail', 'modal-order-detail-content', '/painel/pedidos/' + id + '?fragment=1', 'Pedido #' + id);
            });
        });

        document.querySelectorAll('[data-edit-order]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-edit-order');
                openFragmentModal('modal-order-detail', 'modal-order-detail-content', '/painel/pedidos/' + id + '/editar?fragment=1', 'Editar Pedido #' + id);
            });
        });

        document.querySelectorAll('[data-view-quote]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-view-quote');
                openFragmentModal('modal-quote-detail', 'modal-quote-detail-content', '/painel/orcamentos/' + id + '?fragment=1', 'Orçamento #' + id);
            });
        });

        document.querySelectorAll('[data-edit-quote]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-edit-quote');
                openFragmentModal('modal-quote-detail', 'modal-quote-detail-content', '/painel/orcamentos/' + id + '/editar?fragment=1', 'Editar Orçamento #' + id);
            });
        });

        document.querySelectorAll('[data-edit-client]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-edit-client');
                openFragmentModal('modal-client-edit', 'modal-client-edit-content', '/painel/clientes/' + id + '/editar?fragment=1', 'Editar cliente');
            });
        });

        document.querySelectorAll('[data-edit-transaction]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-edit-transaction');
                openFragmentModal('modal-transaction-edit', 'modal-transaction-edit-content', '/painel/financeiro/contas/' + id + '/editar?fragment=1', 'Editar lançamento');
            });
        });

        // Confirmacao antes de excluir -- data-confirm no botao de submit (nao no form), pra
        // poder escolher exatamente qual botao dispara o aviso quando o form tem mais de um.
        document.addEventListener('submit', function (e) {
            var btn = e.submitter;
            if (btn && btn.dataset.confirm && !window.confirm(btn.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
})();
