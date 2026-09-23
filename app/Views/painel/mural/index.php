<?php
use App\Core\View;
use App\Models\TeamFeedEntry;
$lastId = $entries ? (int) $entries[0]['id'] : 0;
?>
<div class="page-header">
    <h1>🎉 Mural de Conquistas</h1>
</div>
<p class="section-sub">Vendas fechadas, metas batidas, certificações e indicações ativadas da sua equipe — atualiza sozinho.</p>

<div id="mural-feed" class="cards-grid" style="grid-template-columns:1fr;max-width:640px;">
    <?php if (!$entries): ?>
        <p class="hint-text" id="mural-empty">Nenhuma conquista registrada ainda. Assim que a equipe fechar uma venda, bater uma meta ou ativar uma indicação, aparece aqui.</p>
    <?php endif; ?>
    <?php foreach ($entries as $e): ?>
        <div class="dash-card" style="text-align:left;flex-direction:row;align-items:center;gap:12px;">
            <span style="font-size:1.6rem;"><?= TeamFeedEntry::TYPE_ICONS[$e['type']] ?? '🎉' ?></span>
            <div>
                <strong style="font-size:1rem;"><?= View::e($e['message']) ?></strong>
                <br><span class="hint-text" data-mural-time="<?= View::e($e['created_at']) ?>"><?= View::e(date('d/m/Y H:i', strtotime($e['created_at']))) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var feed = document.getElementById('mural-feed');
    var empty = document.getElementById('mural-empty');
    var lastId = <?= (int) $lastId ?>;
    var icons = { venda: '🎉', meta: '🏆', certificacao: '✅', indicacao: '🎁' };

    function timeLabel(iso) {
        var d = new Date(iso.replace(' ', 'T'));
        var day = String(d.getDate()).padStart(2, '0');
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var hh = String(d.getHours()).padStart(2, '0');
        var mm = String(d.getMinutes()).padStart(2, '0');
        return day + '/' + month + '/' + d.getFullYear() + ' ' + hh + ':' + mm;
    }

    function buildCard(entry) {
        var card = document.createElement('div');
        card.className = 'dash-card';
        card.style.cssText = 'text-align:left;flex-direction:row;align-items:center;gap:12px;opacity:0;transition:opacity .5s;';
        card.innerHTML = '<span style="font-size:1.6rem;">' + (icons[entry.type] || '🎉') + '</span>'
            + '<div><strong style="font-size:1rem;"></strong><br><span class="hint-text"></span></div>';
        card.querySelector('strong').textContent = entry.message;
        card.querySelector('.hint-text').textContent = timeLabel(entry.created_at);
        return card;
    }

    function poll() {
        fetch('/painel/mural/feed?since=' + lastId, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.entries || !data.entries.length) return;
                if (empty) { empty.remove(); empty = null; }
                data.entries.slice().reverse().forEach(function (entry) {
                    var card = buildCard(entry);
                    feed.insertBefore(card, feed.firstChild);
                    requestAnimationFrame(function () { card.style.opacity = '1'; });
                });
                lastId = data.entries[0].id;
            })
            .catch(function () {});
    }

    setInterval(poll, 8000);
})();
</script>
