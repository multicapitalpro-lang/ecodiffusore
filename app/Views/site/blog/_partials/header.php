<?php
use App\Core\Config;
use App\Core\View;
/** @var array $post */
$baseUrl = rtrim(Config::get('app_url', 'https://ecodiffusorebrasil.com.br'), '/');
?>
<nav class="blog-breadcrumb">
    <div class="site-container"><a href="/">Início</a> › <a href="/blog">Blog</a> › <?= View::e($post['title']) ?></div>
</nav>
<header class="blog-article-header">
    <div class="site-container">
        <time datetime="<?= View::e($post['publishedAt']) ?>"><?= View::e(date('d/m/Y', strtotime($post['publishedAt']))) ?></time>
        <h1><?= View::e($post['title']) ?></h1>
        <?php if (!empty($post['image'])): ?>
            <img src="<?= View::asset($post['image']) ?>" alt="<?= View::e($post['imageAlt'] ?? $post['title']) ?>" class="blog-article-image" loading="eager">
        <?php endif; ?>
    </div>
</header>
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post['title'],
    'description' => $post['description'],
    'datePublished' => $post['publishedAt'],
    'inLanguage' => 'pt-BR',
    'author' => ['@type' => 'Organization', 'name' => 'Ecodiffusore Brasil'],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Ecodiffusore Brasil',
        'logo' => ['@type' => 'ImageObject', 'url' => $baseUrl . View::asset('/assets/img/logo-full-navy.png')],
    ],
    'mainEntityOfPage' => $baseUrl . '/blog/' . $post['slug'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
