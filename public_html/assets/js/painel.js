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
})();
