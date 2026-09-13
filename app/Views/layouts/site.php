<?php
use App\Core\Config;
use App\Core\Csrf;
use App\Core\View;
/** @var callable $content */
$showPopup = $showPopup ?? false;

// SEO por pagina -- cada controller pode sobrescrever passando seoTitle/seoDescription/seoPath no
// array de dados do View::render(); sem isso, cai no default da home (mesmo texto que ja existia
// aqui antes, so movido pra variavel). Nunca indexa querystring (?ref=, ?erro= etc): o canonical
// sempre usa so' o path base, pra nao espalhar a autoridade de uma mesma pagina em varias URLs.
$baseUrl = rtrim(Config::get('app_url', 'https://ecodiffusorebrasil.com.br'), '/');
$seoTitle = $seoTitle ?? 'Ecodiffusore Brasil — Economia de Diesel e Combustível | Sistema Patenteado';
$seoDescription = $seoDescription ?? 'Reduza até 20% do consumo de diesel e combustível com o Ecodiffusore, sistema patenteado (INPI) que aumenta a performance de caminhões, máquinas agrícolas e geradores. Economia real, payback rápido.';
$seoPath = $seoPath ?? '/';
$seoImage = $seoImage ?? $baseUrl . View::asset('/assets/img/beneficio-economia.jpg');
$canonicalUrl = $baseUrl . $seoPath;
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($seoTitle) ?></title>
    <meta name="description" content="<?= View::e($seoDescription) ?>">
    <meta name="robots" content="index, follow">
    <meta name="google-site-verification" content="NnB6on6oXgLLdw4SVIE1DX-Xk0B-NBq6egK5Kj1JlVM">
    <link rel="canonical" href="<?= View::e($canonicalUrl) ?>">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="Ecodiffusore Brasil">
    <meta property="og:title" content="<?= View::e($seoTitle) ?>">
    <meta property="og:description" content="<?= View::e($seoDescription) ?>">
    <meta property="og:url" content="<?= View::e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= View::e($seoImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= View::e($seoTitle) ?>">
    <meta name="twitter:description" content="<?= View::e($seoDescription) ?>">
    <meta name="twitter:image" content="<?= View::e($seoImage) ?>">

    <link rel="icon" href="<?= View::asset('/assets/img/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/site.css') ?>">

    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Ecodiffusore Brasil',
        'url' => $baseUrl,
        'logo' => $baseUrl . View::asset('/assets/img/logo-full-navy.png'),
        'description' => 'Distribuidora do sistema patenteado Ecodiffusore, que reduz o consumo de diesel e combustível e aumenta a performance de caminhões, máquinas agrícolas e geradores.',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Rua Goiás, 1530, Bairro Country',
            'addressLocality' => 'Cascavel',
            'addressRegion' => 'PR',
            'addressCountry' => 'BR',
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+55-45-99102-1551',
            'contactType' => 'sales',
            'areaServed' => 'BR',
            'availableLanguage' => 'Portuguese',
        ],
        'sameAs' => [
            'https://www.instagram.com/ecodiffusorebrasil',
            'https://www.facebook.com/EcodiffusoreBrasil',
            'https://www.youtube.com/@EcodiffusoreBrasil',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
</head>
<body>
<header class="site-header">
    <div class="site-container site-header-inner">
        <img src="<?= View::asset('/assets/img/logo-full-white.png') ?>" alt="Ecodiffusore Brasil" class="site-logo">
        <nav class="site-nav">
            <a href="#beneficios">Benefícios</a>
            <a href="#depoimentos">Depoimentos</a>
            <a href="#faq">Dúvidas</a>
            <a href="/blog">Blog</a>
            <a href="#licenciado">Seja Licenciado</a>
            <a href="/painel/login" class="site-nav-login">Meus Pedidos</a>
        </nav>
        <div class="site-social-icons">
            <a href="https://www.instagram.com/ecodiffusorebrasil" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
            </a>
            <a href="https://www.facebook.com/EcodiffusoreBrasil" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 3h-2a5 5 0 0 0-5 5v3H6v4h2v9h4v-9h3l1-4h-4V8a1 1 0 0 1 1-1h3z"/></svg>
            </a>
            <a href="https://www.youtube.com/@EcodiffusoreBrasil" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2.5" y="6" width="19" height="12" rx="4"/><path d="M11 9.7 15 12l-4 2.3z" fill="currentColor" stroke="none"/></svg>
            </a>
        </div>
        <a href="/comprar" class="btn btn-primary site-header-cta">Ver Mais Detalhes</a>
    </div>
</header>

<?php $content(); ?>

<?php if ($showPopup): ?>
<dialog class="modal" id="modal-lead-popup" data-autoopen="1">
    <div class="modal-header">
        <h2>Antes de continuar</h2>
    </div>
    <div class="modal-body">
        <p class="modal-lead-text">Deixe seu contato pra gente não perder você — em seguida você vê todos os detalhes técnicos e as formas de pagamento.</p>
        <?php if (!empty($_GET['erro'])): ?>
            <p class="form-msg form-msg-erro"><?= $_GET['erro'] === 'cidade' ? 'Selecione uma cidade válida da lista.' : 'Preencha nome e WhatsApp.' ?></p>
        <?php endif; ?>
        <form action="/comprar/iniciar" method="post" class="contato-form">
            <?= Csrf::field() ?>
            <input type="text" name="name" placeholder="Seu nome" required>
            <input type="text" name="whatsapp" placeholder="WhatsApp com DDD" required>
            <div class="city-autocomplete-wrap">
                <input type="text" name="city" placeholder="Cidade" autocomplete="off" data-city-autocomplete>
                <div class="autocomplete-results" hidden></div>
            </div>
            <button type="submit" class="btn btn-primary">Continuar</button>
        </form>
        <a href="/" class="modal-lead-back">← Voltar pro site</a>
    </div>
</dialog>
<?php endif; ?>

<a href="https://wa.me/5545991021551?text=<?= rawurlencode('Olá tenho interesse no produto Ecodiffusore, poderia me tirar dúvidas?') ?>" target="_blank" rel="noopener" class="whatsapp-float" aria-label="Falar no WhatsApp">
    <svg viewBox="0 0 32 32" width="30" height="30" fill="#fff"><path d="M16.001 3C9.373 3 4 8.373 4 15c0 2.386.7 4.61 1.905 6.484L4 29l7.716-1.867A11.94 11.94 0 0 0 16 27c6.627 0 12-5.373 12-12S22.628 3 16.001 3zm.001 21.5c-1.94 0-3.76-.53-5.32-1.454l-.382-.226-4.583 1.108 1.13-4.47-.248-.394A9.47 9.47 0 0 1 5.5 15C5.5 9.2 10.201 4.5 16 4.5S26.5 9.2 26.5 15 21.799 24.5 16.002 24.5zm5.46-6.964c-.298-.15-1.766-.872-2.04-.972-.274-.1-.474-.15-.674.15-.2.298-.774.972-.95 1.172-.174.2-.348.224-.646.075-.298-.15-1.258-.464-2.396-1.48-.886-.79-1.484-1.766-1.658-2.064-.174-.298-.02-.46.13-.61.134-.132.298-.348.448-.522.15-.174.2-.298.298-.498.1-.2.05-.374-.025-.524-.075-.15-.674-1.624-.924-2.224-.244-.586-.492-.506-.674-.516l-.574-.01c-.2 0-.524.075-.798.373-.274.298-1.048 1.024-1.048 2.498s1.073 2.898 1.222 3.098c.15.2 2.112 3.224 5.12 4.522.715.309 1.273.494 1.708.632.718.228 1.372.196 1.888.119.576-.086 1.766-.722 2.016-1.42.25-.697.25-1.294.174-1.42-.075-.125-.274-.2-.572-.35z"/></svg>
</a>

<footer class="site-footer">
    <div class="site-container site-footer-inner">
        <div>
            <img src="<?= View::asset('/assets/img/logo-full-white.png') ?>" alt="Ecodiffusore Brasil" class="site-footer-logo">
            <p>Rua Goiás, 1530 — Bairro Country, Cascavel/PR</p>
            <p>CNPJ: 57.512.044/0001-18</p>
            <p>(45) 99102-1551</p>
            <p>comercialecodiffusorebrasil@gmail.com</p>
            <p>Instagram: @ecodiffusorebrasil</p>
        </div>
        <div>
            <a href="/painel/login" class="btn btn-outline site-footer-login">Acessar o Painel</a>
            <p>&copy; <?= date('Y') ?> Ecodiffusore Brasil. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>

<script src="<?= View::asset('/assets/js/site.js') ?>"></script>
</body>
</html>
