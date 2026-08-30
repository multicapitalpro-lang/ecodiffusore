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
        document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = document.getElementById(btn.getAttribute('data-modal-open'));
                if (modal) modal.showModal();
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dialog = btn.closest('dialog');
                if (dialog) dialog.close();
            });
        });

        document.querySelectorAll('dialog[data-autoopen]').forEach(function (dialog) {
            dialog.showModal();
        });

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) dialog.close();
            });
        });

        // Envio via AJAX (fica na mesma tela, sem navegar pra outra pagina)
        document.querySelectorAll('form.ajax-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var submitBtn = form.querySelector('button[type=submit]');
                if (submitBtn) submitBtn.disabled = true;

                form.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; });
                form.querySelectorAll('.has-error').forEach(function (el) { el.classList.remove('has-error'); });

                fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: new FormData(form),
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
                        if (submitBtn) submitBtn.disabled = false;
                    })
                    .catch(function () {
                        alert('Erro ao salvar. Verifique sua conexão e tente novamente.');
                        if (submitBtn) submitBtn.disabled = false;
                    });
            });
        });
    });
})();
