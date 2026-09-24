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

<div id="site-chat-widget" data-csrf="<?= Csrf::token() ?>">
    <button type="button" id="site-chat-bubble" class="site-chat-bubble" aria-label="Abrir chat">
        <svg class="site-chat-bubble-icon-chat" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <svg class="site-chat-bubble-icon-close" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
    <div id="site-chat-panel" class="site-chat-panel" hidden>
        <div class="site-chat-header">
            <img src="<?= View::asset('/assets/img/logo-full-white.png') ?>" alt="" class="site-chat-header-logo">
            <span>Fale com a gente</span>
            <button type="button" id="site-chat-close" class="site-chat-close" aria-label="Fechar">&times;</button>
        </div>
        <div class="site-chat-messages" id="site-chat-messages"></div>
        <div class="site-chat-input-area" id="site-chat-input-area"></div>
    </div>
</div>

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
            <div class="site-lgpd-badge">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
                <span>Seus dados protegidos — em conformidade com a LGPD</span>
            </div>
            <p>&copy; <?= date('Y') ?> Ecodiffusore Brasil. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>

<script src="<?= View::asset('/assets/js/site.js') ?>"></script>
</body>
</html>
