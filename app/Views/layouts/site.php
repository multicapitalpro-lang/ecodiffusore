<?php
use App\Core\View;
/** @var callable $content */
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ecodiffusore Brasil — Economize Diesel e Eleve a Performance</title>
    <meta name="description" content="Sistema patenteado que reduz o consumo de diesel em até 20% e aumenta a performance de caminhões. Economia real, payback rápido, garantia de 30 dias.">
    <link rel="icon" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/site.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="site-container site-header-inner">
        <img src="/assets/img/logo.svg" alt="Ecodiffusore Brasil" class="site-logo">
        <nav class="site-nav">
            <a href="#beneficios">Benefícios</a>
            <a href="#precos">Modelos</a>
            <a href="#faq">Dúvidas</a>
            <a href="#licenciado">Seja Licenciado</a>
            <a href="/painel/login" class="site-nav-login">Área do Cliente</a>
        </nav>
        <a href="/painel/cadastro" class="btn btn-primary site-header-cta">Criar Conta</a>
    </div>
</header>

<?php $content(); ?>

<a href="https://wa.me/5541988962839" target="_blank" rel="noopener" class="whatsapp-float" aria-label="Falar no WhatsApp">
    <svg viewBox="0 0 32 32" width="30" height="30" fill="#fff"><path d="M16.001 3C9.373 3 4 8.373 4 15c0 2.386.7 4.61 1.905 6.484L4 29l7.716-1.867A11.94 11.94 0 0 0 16 27c6.627 0 12-5.373 12-12S22.628 3 16.001 3zm.001 21.5c-1.94 0-3.76-.53-5.32-1.454l-.382-.226-4.583 1.108 1.13-4.47-.248-.394A9.47 9.47 0 0 1 5.5 15C5.5 9.2 10.201 4.5 16 4.5S26.5 9.2 26.5 15 21.799 24.5 16.002 24.5zm5.46-6.964c-.298-.15-1.766-.872-2.04-.972-.274-.1-.474-.15-.674.15-.2.298-.774.972-.95 1.172-.174.2-.348.224-.646.075-.298-.15-1.258-.464-2.396-1.48-.886-.79-1.484-1.766-1.658-2.064-.174-.298-.02-.46.13-.61.134-.132.298-.348.448-.522.15-.174.2-.298.298-.498.1-.2.05-.374-.025-.524-.075-.15-.674-1.624-.924-2.224-.244-.586-.492-.506-.674-.516l-.574-.01c-.2 0-.524.075-.798.373-.274.298-1.048 1.024-1.048 2.498s1.073 2.898 1.222 3.098c.15.2 2.112 3.224 5.12 4.522.715.309 1.273.494 1.708.632.718.228 1.372.196 1.888.119.576-.086 1.766-.722 2.016-1.42.25-.697.25-1.294.174-1.42-.075-.125-.274-.2-.572-.35z"/></svg>
</a>

<footer class="site-footer">
    <div class="site-container site-footer-inner">
        <div>
            <img src="/assets/img/logo.svg" alt="Ecodiffusore Brasil" class="site-footer-logo">
            <p>R. Albino Kaminski, 886, Bairro Alto, Curitiba/PR</p>
            <p>(41) 3308-6831 · (41) 98896-2839</p>
            <p>contato@ecodiffusorebrasil.com.br</p>
            <p>Instagram: @ecodiffusorebrasil</p>
        </div>
        <div>
            <p><a href="/painel/login">Área do Cliente / Licenciado</a></p>
            <p>&copy; <?= date('Y') ?> Ecodiffusore Brasil. Todos os direitos reservados.</p>
        </div>
    </div>
</footer>

<script src="<?= View::asset('/assets/js/site.js') ?>"></script>
</body>
</html>
