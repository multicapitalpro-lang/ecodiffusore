<?php
use App\Core\BrazilStates;
use App\Core\View;
?>
<div class="page-header">
    <h1>Expansão Licenciados</h1>
</div>
<p class="section-sub">Onde a Ecodiffusore já tem Licenciado e pra onde ainda dá pra expandir — visão do país inteiro, sem filtro de rede. Clique num estado pra ver a equipe e as cidades de lá.</p>

<div class="cards-grid">
    <div class="dash-card">
        <span>Licenciados ativos</span>
        <strong><?= (int) $totalLicenciados ?></strong>
    </div>
    <div class="dash-card">
        <span>Estados com cobertura</span>
        <strong><?= (int) $estadosCobertos ?> / <?= (int) $totalEstados ?></strong>
    </div>
    <div class="dash-card">
        <span>Estados sem Licenciado</span>
        <strong><?= count($missingStates) ?></strong>
    </div>
</div>

<h3 class="section-title">Mapa de cobertura</h3>

<div id="panorama-national-view">
    <?php $interactive = true; include __DIR__ . '/../_brazil_grid.php'; ?>
</div>

<div id="panorama-state-view" class="state-detail-panel" hidden>
    <button type="button" class="btn btn-outline" id="panorama-back-btn">← Ver mapa nacional</button>
    <h3 class="section-title" id="panorama-state-title"></h3>
    <p class="section-sub">Role o mouse pra dar zoom, arraste pra mover — os nomes das cidades aparecem conforme você aproxima.</p>
    <div id="panorama-state-map" class="state-map-wrap"></div>
    <div id="panorama-state-regions"></div>
</div>

<div class="map-tooltip" id="map-tooltip" hidden></div>

<h3 class="section-title">Onde buscar Licenciado primeiro</h3>
<p class="section-sub">Estados ainda sem Licenciado, ordenados por quantidade de Leads recebidos vindos de lá — sinal de demanda esperando cobertura.</p>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Estado</th><th>Leads recebidos</th></tr></thead>
        <tbody>
            <?php
            $sortedMissing = $missingStates;
            uksort($sortedMissing, fn ($a, $b) => ($leadsByMissingState[$b] ?? 0) <=> ($leadsByMissingState[$a] ?? 0));
            ?>
            <?php foreach ($sortedMissing as $uf => $name): ?>
                <tr>
                    <td><?= View::e($name) ?> <span class="hint-text">(<?= $uf ?>)</span></td>
                    <td>
                        <?php if (!empty($leadsByMissingState[$uf])): ?>
                            <span class="status-badge status-novo"><?= (int) $leadsByMissingState[$uf] ?> lead(s)</span>
                        <?php else: ?>
                            <span class="hint-text">sem leads ainda</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$missingStates): ?>
                <tr><td colspan="2">Todos os estados já têm Licenciado. 🎉</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
    var grid = document.getElementById('panorama-grid');
    if (!grid) return;

    var nationalView = document.getElementById('panorama-national-view');
    var stateView = document.getElementById('panorama-state-view');
    var backBtn = document.getElementById('panorama-back-btn');
    var titleEl = document.getElementById('panorama-state-title');
    var mapEl = document.getElementById('panorama-state-map');
    var regionsEl = document.getElementById('panorama-state-regions');
    var tooltip = document.getElementById('map-tooltip');
    var roleLabels = { gestor: 'Gestor', vendedor: 'Vendedor' };
    var LABEL_ZOOM_THRESHOLD = 2.2;
    var zoomState = { scale: 1, tx: 0, ty: 0 };

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function waLink(phone) {
        var digits = (phone || '').replace(/\D/g, '');
        return digits ? 'https://wa.me/55' + digits : null;
    }

    function normCity(s) {
        var n = (s || '').toUpperCase().normalize('NFD');
        var out = '';
        for (var i = 0; i < n.length; i++) {
            var code = n.charCodeAt(i);
            if (code < 0x0300 || code > 0x036f) out += n[i];
        }
        return out.trim();
    }

    // ---------- tooltip flutuante (mapa nacional + cidades do estado) ----------
    function showTooltip(evt, html) {
        tooltip.innerHTML = html;
        tooltip.hidden = false;
        moveTooltip(evt);
    }
    function moveTooltip(evt) {
        var pad = 16;
        var x = evt.clientX + pad, y = evt.clientY + pad;
        var maxX = window.innerWidth - tooltip.offsetWidth - 10;
        var maxY = window.innerHeight - tooltip.offsetHeight - 10;
        tooltip.style.left = Math.max(4, Math.min(x, maxX)) + 'px';
        tooltip.style.top = Math.max(4, Math.min(y, maxY)) + 'px';
    }
    function hideTooltip() { tooltip.hidden = true; }

    // ---------- mapa nacional: hover com tooltip + clique abre o estado ----------
    grid.addEventListener('mousemove', function (e) {
        var el = e.target.closest('[data-uf]');
        if (!el) { hideTooltip(); return; }
        var count = Number(el.getAttribute('data-count'));
        var html = '<strong>' + esc(el.getAttribute('data-name')) + '</strong><br>Licenciados: ' + count;
        if (count > 0) {
            html += '<br>Cidades: ' + esc(el.getAttribute('data-cities'));
        }
        showTooltip(e, html);
    });
    grid.addEventListener('mouseleave', hideTooltip);
    grid.addEventListener('click', function (e) {
        var cell = e.target.closest('[data-uf]');
        if (!cell) return;
        hideTooltip();
        loadState(cell.getAttribute('data-uf'));
    });

    backBtn.addEventListener('click', function () {
        stateView.hidden = true;
        nationalView.hidden = false;
    });

    function personRow(person, roleLabel) {
        var link = waLink(person.whatsapp);
        return '<div class="state-region-person"><span class="role-tag">' + esc(roleLabel) + '</span> ' + esc(person.name) +
            (link ? ' <a href="' + link + '" target="_blank" rel="noopener">💬</a>' : '') + '</div>';
    }

    function buildRegions(regions) {
        if (!regions.length) {
            return '<p class="state-empty">Nenhum Licenciado cadastrado neste estado ainda.</p>';
        }
        return regions.map(function (r) {
            var licWa = waLink(r.licenciado.whatsapp);
            var html = '<div class="state-region-card">';
            html += '<div class="state-region-title">🏢 ' + esc(r.licenciado.name) +
                (r.licenciado.city ? ' <span class="hint-text">(' + esc(r.licenciado.city) + ')</span>' : '') + '</div>';
            if (licWa) {
                html += '<div class="state-region-person"><span class="role-tag">Licenciado</span> <a href="' + licWa + '" target="_blank" rel="noopener">💬 falar</a></div>';
            }
            if (r.supervisor) {
                html += personRow(r.supervisor, 'Supervisor');
            }
            r.team.forEach(function (member) {
                html += personRow(member, roleLabels[member.role] || member.role);
            });
            if (!r.supervisor && !r.team.length) {
                html += '<p class="hint-text" style="margin:6px 0 0 4px;">Sem supervisor nem equipe de gestor/vendedor ainda.</p>';
            }
            html += '</div>';
            return html;
        }).join('');
    }

    // ---------- mapa do estado: contorno real + cidades + zoom/pan estilo Google Maps ----------
    function buildStateMap(pathData, viewbox, cities, regions) {
        if (!pathData) {
            return '<p class="state-empty">Sem contorno disponível pra este estado.</p>';
        }

        // Mesma projecao usada no servidor pra gerar o path (equirretangular com correcao de
        // longitude por cos da latitude media do estado) -- garante que as cidades caiam
        // exatamente sobre o contorno real, e nao num retangulo solto.
        function px(lng) { return pathData.pad + pathData.xOff + (lng - pathData.minLon) * pathData.cos * pathData.scale; }
        function py(lat) { return pathData.pad + pathData.yOff + (pathData.maxLat - lat) * pathData.scale; }

        var licByCity = {};
        (regions || []).forEach(function (r) {
            var key = normCity(r.licenciado.city);
            (licByCity[key] = licByCity[key] || []).push(r.licenciado.name);
        });

        var dots = '', highlighted = '', labels = '';
        cities.forEach(function (c) {
            var cx = px(c.lng).toFixed(1), cy = py(c.lat).toFixed(1);
            var licNames = licByCity[normCity(c.name)] || [];
            var safeName = esc(c.name), safeLic = esc(licNames.join(', '));
            if (c.has_licenciado) {
                highlighted += '<circle class="city-dot" cx="' + cx + '" cy="' + cy + '" r="6" fill="#1f7a3d" stroke="#fff" stroke-width="1.2" data-name="' + safeName + '" data-lic="' + safeLic + '"></circle>';
                labels += '<text class="city-label city-label-important" x="' + (Number(cx) + 8) + '" y="' + (Number(cy) + 3) + '">' + safeName + '</text>';
            } else {
                dots += '<circle class="city-dot" cx="' + cx + '" cy="' + cy + '" r="2" fill="#98a6bd" fill-opacity=".65" data-name="' + safeName + '" data-lic="' + safeLic + '"></circle>';
                labels += '<text class="city-label" x="' + (Number(cx) + 4) + '" y="' + (Number(cy) + 2.5) + '">' + safeName + '</text>';
            }
        });

        var outline = '<path d="' + pathData.d + '" fill="#dbe2ee" stroke="#98a6bd" stroke-width="1.2"></path>';
        return '<svg viewBox="' + viewbox + '" xmlns="http://www.w3.org/2000/svg" id="state-svg">' +
            '<g id="state-zoom-group">' + outline + dots + labels + highlighted + '</g></svg>';
    }

    function applyZoomTransform() {
        var g = document.getElementById('state-zoom-group');
        var svg = document.getElementById('state-svg');
        if (!g || !svg) return;
        g.setAttribute('transform', 'translate(' + zoomState.tx + ',' + zoomState.ty + ') scale(' + zoomState.scale + ')');
        svg.classList.toggle('zoomed-in', zoomState.scale >= LABEL_ZOOM_THRESHOLD);
    }

    function clampPan(vb) {
        var margin = Math.min(vb.width, vb.height) * 0.4;
        var minTx = -(vb.width * zoomState.scale - vb.width) - margin;
        var minTy = -(vb.height * zoomState.scale - vb.height) - margin;
        zoomState.tx = Math.min(margin, Math.max(minTx, zoomState.tx));
        zoomState.ty = Math.min(margin, Math.max(minTy, zoomState.ty));
    }

    function zoomBy(factor, originX, originY) {
        var svg = document.getElementById('state-svg');
        if (!svg) return;
        var vb = svg.viewBox.baseVal;
        var ox = originX != null ? originX : vb.x + vb.width / 2;
        var oy = originY != null ? originY : vb.y + vb.height / 2;
        var newScale = Math.min(10, Math.max(1, zoomState.scale * factor));
        zoomState.tx = ox - (ox - zoomState.tx) * (newScale / zoomState.scale);
        zoomState.ty = oy - (oy - zoomState.ty) * (newScale / zoomState.scale);
        zoomState.scale = newScale;
        clampPan(vb);
        applyZoomTransform();
    }

    function resetZoom() {
        zoomState = { scale: 1, tx: 0, ty: 0 };
        applyZoomTransform();
    }

    function wireStateMapInteractions() {
        var svg = document.getElementById('state-svg');
        if (!svg) return;
        resetZoom();

        var dragging = false, moved = false, lastX = 0, lastY = 0;

        function toViewBoxPoint(clientX, clientY) {
            var rect = svg.getBoundingClientRect();
            var vb = svg.viewBox.baseVal;
            return {
                x: (clientX - rect.left) / rect.width * vb.width + vb.x,
                y: (clientY - rect.top) / rect.height * vb.height + vb.y,
            };
        }

        svg.addEventListener('wheel', function (e) {
            e.preventDefault();
            var p = toViewBoxPoint(e.clientX, e.clientY);
            zoomBy(e.deltaY < 0 ? 1.25 : 1 / 1.25, p.x, p.y);
        }, { passive: false });

        svg.addEventListener('pointerdown', function (e) {
            dragging = true;
            moved = false;
            lastX = e.clientX;
            lastY = e.clientY;
            svg.setPointerCapture(e.pointerId);
            svg.classList.add('is-dragging');
        });
        svg.addEventListener('pointermove', function (e) {
            if (dragging) {
                var rect = svg.getBoundingClientRect();
                var vb = svg.viewBox.baseVal;
                var dx = (e.clientX - lastX) / rect.width * vb.width;
                var dy = (e.clientY - lastY) / rect.height * vb.height;
                if (Math.abs(e.clientX - lastX) > 2 || Math.abs(e.clientY - lastY) > 2) moved = true;
                zoomState.tx += dx;
                zoomState.ty += dy;
                lastX = e.clientX;
                lastY = e.clientY;
                clampPan(vb);
                applyZoomTransform();
                hideTooltip();
                return;
            }
            var dot = e.target.closest('.city-dot');
            if (!dot) { hideTooltip(); return; }
            var name = dot.getAttribute('data-name');
            var lic = dot.getAttribute('data-lic');
            var html = '<strong>' + esc(name) + '</strong>' + (lic ? '<br>Licenciado: ' + esc(lic) : '<br><span class="tooltip-muted">Sem licenciado ainda</span>');
            showTooltip(e, html);
        });
        function endDrag(e) {
            if (!dragging) return;
            dragging = false;
            svg.classList.remove('is-dragging');
            try { svg.releasePointerCapture(e.pointerId); } catch (err) {}
        }
        svg.addEventListener('pointerup', endDrag);
        svg.addEventListener('pointercancel', endDrag);
        svg.addEventListener('mouseleave', hideTooltip);
    }

    function loadState(uf) {
        nationalView.hidden = true;
        stateView.hidden = false;
        titleEl.textContent = 'Carregando...';
        mapEl.innerHTML = '';
        regionsEl.innerHTML = '<p class="state-loading">Carregando equipe...</p>';

        fetch('/painel/desempenho/panorama/estado/' + uf, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                titleEl.textContent = 'Equipe em ' + data.state_name + ' (' + data.uf + ')';
                var svgHtml = buildStateMap(data.path, data.viewbox, data.cities, data.regions);
                var controls = data.path ? (
                    '<div class="state-map-controls">' +
                    '<button type="button" class="state-zoom-btn" data-zoom="in" title="Aumentar zoom">+</button>' +
                    '<button type="button" class="state-zoom-btn" data-zoom="out" title="Diminuir zoom">−</button>' +
                    '<button type="button" class="state-zoom-btn" data-zoom="reset" title="Centralizar">⟲</button>' +
                    '</div>'
                ) : '';
                mapEl.innerHTML = svgHtml + controls;
                if (data.path) {
                    wireStateMapInteractions();
                    mapEl.querySelectorAll('[data-zoom]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var action = btn.getAttribute('data-zoom');
                            if (action === 'in') zoomBy(1.4);
                            else if (action === 'out') zoomBy(1 / 1.4);
                            else resetZoom();
                        });
                    });
                }
                regionsEl.innerHTML = buildRegions(data.regions);
            })
            .catch(function () {
                titleEl.textContent = '';
                regionsEl.innerHTML = '<p class="state-empty">Não deu pra carregar os dados desse estado.</p>';
            });
    }
})();
</script>
