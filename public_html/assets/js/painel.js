(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var body = document.getElementById('items-body');
        if (!body) return;

        var addBtn = document.getElementById('add-item-row');
        var totalEl = document.getElementById('order-total');

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

        body.addEventListener('change', function (e) {
            if (e.target.classList.contains('item-product')) {
                var opt = e.target.selectedOptions[0];
                var price = opt ? opt.getAttribute('data-price') : null;
                var row = e.target.closest('.item-row');
                if (price && row) {
                    row.querySelector('.item-price').value = parseFloat(price).toFixed(2);
                }
            }
            recalcAll();
        });

        body.addEventListener('input', function (e) {
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
                body.appendChild(clone);
            });
        }

        recalcAll();
    });

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

        document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = document.getElementById(btn.getAttribute('data-modal-open'));
                if (modal) modal.showModal();
            });
        });

        bindModalClose(document);

        document.querySelectorAll('dialog[data-autoopen]').forEach(function (dialog) {
            dialog.showModal();
        });

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) dialog.close();
            });
        });

        // Area de anexos (arrastar e soltar)
        document.querySelectorAll('[data-file-drop]').forEach(function (drop) {
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

        bindAjaxForms(document);

        // Criacao/edicao de usuario num modal: carrega o form via fetch (fragmento sem layout)
        // em vez de navegar pra outra pagina -- ver UserController::create()/edit()/store()/update().
        function openUserFormModal(url, title) {
            var modal = document.getElementById('modal-user-edit');
            var content = document.getElementById('modal-user-edit-content');
            if (!modal || !content) return;

            content.innerHTML = '<div class="modal-header"><h2>' + title + '</h2>'
                + '<button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button></div>'
                + '<div class="modal-body"><p class="hint-text">Carregando...</p></div>';
            bindModalClose(content);
            modal.showModal();

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    content.innerHTML = html;
                    bindModalClose(content);
                    bindAjaxForms(content);
                })
                .catch(function () {
                    var body = content.querySelector('.modal-body');
                    if (body) body.innerHTML = '<p class="form-msg form-msg-erro">Erro ao carregar. Tente novamente.</p>';
                });
        }

        document.querySelectorAll('[data-edit-user]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openUserFormModal('/painel/usuarios/' + btn.getAttribute('data-edit-user') + '/editar?fragment=1', 'Editar usuário');
            });
        });

        var newUserBtn = document.getElementById('btn-new-user');
        if (newUserBtn) {
            newUserBtn.addEventListener('click', function () {
                openUserFormModal('/painel/usuarios/novo?fragment=1', 'Novo usuário');
            });
        }
    });
})();
