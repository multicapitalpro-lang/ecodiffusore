<?php
use App\Core\View;
/** @var array $materials */
?>
<div class="page-header">
    <h1>Materiais de venda</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Documentos institucionais pra mandar pro cliente na hora certa — mesmos arquivos já publicados no site.</p>

<div class="cards-grid">
    <?php foreach ($materials as $m): ?>
        <div class="dash-card">
            <span><?= $m['icon'] ?> <?= View::e($m['title']) ?></span>
            <p class="hint-text" style="margin:6px 0 12px;"><?= View::e($m['description']) ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="<?= View::e($m['file']) ?>" target="_blank" rel="noopener" class="link-small">Abrir</a>
                <a href="<?= View::e($m['file']) ?>" download class="link-small">Baixar</a>
                <a href="https://wa.me/?text=<?= rawurlencode('Segue o material: https://ecodiffusorebrasil.com.br' . $m['file']) ?>" target="_blank" rel="noopener" class="link-small">💬 Enviar por WhatsApp</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
