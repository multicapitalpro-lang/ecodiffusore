<?php
use App\Core\View;
?>
<div class="page-header">
    <h1>Vídeos Tutoriais</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Instalação do produto, teste da chave e outras dúvidas comuns — direto aqui, sem precisar procurar em outro lugar.</p>

<div class="cards-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
    <?php foreach ($videos as $v): ?>
        <div class="dash-card" style="gap:10px;">
            <span><?= View::e($v['title']) ?></span>
            <?php
            $url = $v['video_url'];
            $isFile = (bool) preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $url);
            ?>
            <div style="position:relative; width:100%; aspect-ratio:16/9; background:#000; border-radius:8px; overflow:hidden;">
                <?php if ($isFile): ?>
                    <video src="<?= View::e($url) ?>" controls style="width:100%; height:100%;"></video>
                <?php else: ?>
                    <iframe src="<?= View::e($url) ?>" style="width:100%; height:100%; border:0;" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
                <?php endif; ?>
            </div>
            <?php if (!empty($v['description'])): ?>
                <p class="hint-text" style="margin:0;"><?= View::e($v['description']) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$videos): ?>
        <p class="hint-text">Nenhum vídeo tutorial disponível ainda.</p>
    <?php endif; ?>
</div>
