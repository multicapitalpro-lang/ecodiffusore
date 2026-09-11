<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\SellerTrainingProgress;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Treinamento obrigatório — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box" style="max-width:760px;">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Treinamento obrigatório</h1>
    <p class="auth-hint">Antes de liberar o acesso completo ao painel, assista aos vídeos abaixo até o fim. É obrigatório pra garantir que você sabe vender e atender o cliente do jeito certo.</p>

    <div id="training-videos">
        <?php foreach ($videos as $v): ?>
            <?php
            $p = $progress[$v['id']] ?? null;
            $pct = $p ? (float) $p['max_percent_watched'] : 0.0;
            $done = $p && !empty($p['completed_at']);
            ?>
            <div class="dash-card" style="text-align:left;margin-bottom:16px;" data-video-card="<?= (int) $v['id'] ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                    <strong><?= View::e($v['title']) ?></strong>
                    <span class="status-badge status-<?= $done ? 'active' : 'novo' ?>" data-video-status>
                        <?= $done ? '✔ Concluído' : 'Pendente' ?>
                    </span>
                </div>
                <video
                    src="<?= View::e($v['video_url']) ?>"
                    controls
                    style="width:100%;margin-top:10px;border-radius:8px;background:#000;"
                    data-video-id="<?= (int) $v['id'] ?>"
                    data-start-percent="<?= $pct ?>"
                ></video>
                <div class="hint-text" style="margin-top:6px;" data-video-pct>Assistido: <?= number_format($pct, 0) ?>%</div>
            </div>
        <?php endforeach; ?>
        <?php if (!$videos): ?>
            <p class="hint-text">Nenhum vídeo de treinamento cadastrado ainda — fale com o administrador.</p>
        <?php endif; ?>
    </div>

    <button type="button" class="btn btn-primary" id="training-continue" style="width:100%;margin-top:8px;" disabled>
        Assista todos os vídeos até o fim (mín. 90%) pra continuar
    </button>

    <a class="auth-back" href="/painel/logout">Sair</a>
</div>

<script>
(function () {
    var csrfToken = <?= json_encode(Csrf::token()) ?>;
    var threshold = <?= SellerTrainingProgress::COMPLETION_THRESHOLD_PCT ?>;
    var continueBtn = document.getElementById('training-continue');
    var videos = document.querySelectorAll('video[data-video-id]');
    var reported = {};

    videos.forEach(function (video) {
        var startPct = parseFloat(video.getAttribute('data-start-percent')) || 0;
        video.addEventListener('loadedmetadata', function () {
            if (startPct > 0 && video.duration) {
                video.currentTime = Math.min(video.duration * (startPct / 100), video.duration - 1);
            }
        });

        var lastSent = -1;
        video.addEventListener('timeupdate', function () {
            if (!video.duration) return;
            var pct = Math.min(100, (video.currentTime / video.duration) * 100);
            if (pct - lastSent < 2 && pct < 99) return;
            lastSent = pct;
            sendProgress(video, pct);
        });
        video.addEventListener('ended', function () { sendProgress(video, 100); });
    });

    function sendProgress(video, pct) {
        var videoId = video.getAttribute('data-video-id');
        var card = document.querySelector('[data-video-card="' + videoId + '"]');
        if (card) {
            card.querySelector('[data-video-pct]').textContent = 'Assistido: ' + Math.round(pct) + '%';
        }

        var body = new URLSearchParams();
        body.set('csrf_token', csrfToken);
        body.set('video_id', videoId);
        body.set('percent', pct.toFixed(2));

        fetch('/painel/treinamento/progresso', { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (pct >= threshold && card) {
                    var badge = card.querySelector('[data-video-status]');
                    badge.textContent = '✔ Concluído';
                    badge.className = 'status-badge status-active';
                }
                if (data.allCompleted) {
                    continueBtn.disabled = false;
                    continueBtn.textContent = 'Ir para o painel';
                    continueBtn.onclick = function () { window.location.href = '/painel'; };
                }
            })
            .catch(function () {});
    }
})();
</script>
</body>
</html>
