<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
$waUrl = $_GET['wa'] ?? null;
?>

<section class="hero">
    <div class="site-container hero-inner">
        <div class="hero-text">
            <span class="badge"><?= !empty($seller['training_completed_at']) ? '✅ Vendedor Certificado Ecodiffusore Brasil' : 'Vendedor Autorizado Ecodiffusore Brasil' ?></span>
            <h1>Fale direto com <span><?= View::e($seller['name']) ?></span></h1>
            <p class="hero-sub">Economize de 5% a 20% no consumo de diesel com o Ecodiffusore — sistema patenteado (INPI) e fabricado no Brasil, com garantia e suporte em português. Deixe seus dados que <?= View::e(explode(' ', $seller['name'])[0]) ?> te chama no WhatsApp.</p>
            <div class="hero-ctas">
                <a href="#contato" class="btn btn-primary">Quero Economizar Agora</a>
                <?php if (!empty($seller['whatsapp'])): ?>
                    <a href="https://wa.me/55<?= preg_replace('/\D/', '', $seller['whatsapp']) ?>" target="_blank" rel="noopener" class="btn btn-outline">Falar direto no WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="esg">
    <div class="site-container esg-inner">
        <div>
            <h2>Por que a Ecodiffusore Brasil</h2>
            <p>Patente de produto e de marca registradas no INPI, fabricação 100% nacional (sem espera de importação), nota fiscal, garantia de devolução e suporte técnico em português — com uma rede de licenciados autorizados pelo Brasil todo.</p>
        </div>
    </div>
</section>

<section id="contato" class="contato">
    <div class="site-container contato-inner">
        <div>
            <h2>Deixe seus dados</h2>
            <p><?= View::e($seller['name']) ?> entra em contato direto pelo WhatsApp assim que você enviar.</p>
            <?php if ($sucesso): ?>
                <p class="form-msg form-msg-ok">Recebemos seu contato! <?= View::e($seller['name']) ?> vai te chamar no WhatsApp em breve.</p>
                <?php if ($waUrl): ?>
                    <a href="<?= View::e($waUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary" style="margin-top:10px;">💬 Já chamar no WhatsApp agora</a>
                <?php endif; ?>
            <?php elseif ($erro): ?>
                <p class="form-msg form-msg-erro"><?= $erro === 'cidade' ? 'Selecione uma cidade válida da lista.' : 'Preencha ao menos nome e WhatsApp para enviar.' ?></p>
            <?php endif; ?>
            <form action="/v/<?= View::e($seller['public_slug']) ?>" method="post" class="contato-form">
                <?= Csrf::field() ?>
                <input type="text" name="name" placeholder="Seu nome" required>
                <input type="text" name="whatsapp" placeholder="Seu WhatsApp" required>
                <div class="city-autocomplete-wrap">
                    <input type="text" name="city" placeholder="Cidade" autocomplete="off" data-city-autocomplete>
                    <div class="autocomplete-results" hidden></div>
                </div>
                <button type="submit" class="btn btn-primary">Quero Economizar Agora</button>
            </form>
        </div>
    </div>
</section>
