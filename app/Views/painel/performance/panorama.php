<?php
use App\Core\BrazilStates;
use App\Core\View;
?>
<div class="page-header">
    <h1>Equipe Nacional</h1>
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
    <div id="panorama-state-map" class="state-map-wrap"></div>
    <div id="panorama-state-regions"></div>
</div>

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
    var roleLabels = { gestor: 'Gestor', vendedor: 'Vendedor' };

    grid.addEventListener('click', function (e) {
        var cell = e.target.closest('[data-uf]');
        if (!cell) return;
        loadState(cell.getAttribute('data-uf'));
    });

    backBtn.addEventListener('click', function () {
        stateView.hidden = true;
        nationalView.hidden = false;
    });

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function waLink(phone) {
        var digits = (phone || '').replace(/\D/g, '');
        return digits ? 'https://wa.me/55' + digits : null;
    }

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

    function buildStateMap(pathData, viewbox, cities) {
        if (!pathData) {
            return '<p class="state-empty">Sem contorno disponível pra este estado.</p>';
        }

        // Mesma projecao usada no servidor pra gerar o path (equirretangular com correcao de
        // longitude por cos da latitude media do estado) -- garante que as cidades caiam
        // exatamente sobre o contorno real, e nao num retangulo solto.
        function px(lng) { return (pathData.pad + pathData.xOff + (lng - pathData.minLon) * pathData.cos * pathData.scale).toFixed(1); }
        function py(lat) { return (pathData.pad + pathData.yOff + (pathData.maxLat - lat) * pathData.scale).toFixed(1); }

        var dots = '', highlighted = '';
        cities.forEach(function (c) {
            var cx = px(c.lng), cy = py(c.lat);
            if (c.has_licenciado) {
                highlighted += '<circle cx="' + cx + '" cy="' + cy + '" r="6" fill="#1f7a3d" stroke="#fff" stroke-width="1.5"><title>' + esc(c.name) + ' — tem Licenciado</title></circle>';
            } else {
                dots += '<circle cx="' + cx + '" cy="' + cy + '" r="2" fill="#98a6bd" fill-opacity=".6"><title>' + esc(c.name) + '</title></circle>';
            }
        });

        var outline = '<path d="' + pathData.d + '" fill="#dbe2ee" stroke="#98a6bd" stroke-width="1.5"></path>';
        return '<svg viewBox="' + viewbox + '" xmlns="http://www.w3.org/2000/svg">' + outline + dots + highlighted + '</svg>';
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
                mapEl.innerHTML = buildStateMap(data.path, data.viewbox, data.cities);
                regionsEl.innerHTML = buildRegions(data.regions);
            })
            .catch(function () {
                titleEl.textContent = '';
                regionsEl.innerHTML = '<p class="state-empty">Não deu pra carregar os dados desse estado.</p>';
            });
    }
})();
</script>
