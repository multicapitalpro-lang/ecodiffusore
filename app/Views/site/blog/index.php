<?php use App\Core\View; ?>
<section class="blog-hero">
    <div class="site-container">
        <h1>Blog Ecodiffusore Brasil</h1>
        <p>Artigos sobre economia de diesel, redução de consumo de combustível e tecnologia para caminhões, máquinas agrícolas e geradores.</p>
    </div>
</section>

<section class="blog-list-section">
    <div class="site-container">
        <div class="blog-grid">
            <?php foreach ($posts as $post): ?>
                <article class="blog-card">
                    <time datetime="<?= View::e($post['publishedAt']) ?>"><?= View::e(date('d/m/Y', strtotime($post['publishedAt']))) ?></time>
                    <h2><a href="/blog/<?= View::e($post['slug']) ?>"><?= View::e($post['title']) ?></a></h2>
                    <p><?= View::e($post['description']) ?></p>
                    <a href="/blog/<?= View::e($post['slug']) ?>" class="link-small">Ler artigo →</a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
