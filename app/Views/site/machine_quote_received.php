<?php
use App\Core\View;
/** @var array $result */
?>
<section class="buy-hero">
    <div class="site-container">
        <h1>Solicitação recebida!</h1>
        <p>Recebemos os dados da sua máquina — nossa equipe vai analisar e te retornar com o valor.</p>
    </div>
</section>

<section class="buy-section">
    <div class="site-container" style="max-width:560px;text-align:center;">
        <div class="buy-price-summary" style="text-align:left;">
            <p style="margin-top:0;">✅ Obrigado, <strong><?= View::e($result['name'] ?? '') ?></strong>! Sua solicitação de cotação pra <strong><?= View::e($result['machine_type'] ?? 'sua máquina') ?></strong> foi enviada.</p>
            <p>Como cada máquina tem particularidades de custo (ex: mangueira), nossa equipe vai analisar as fotos e informações que você mandou e entrar em contato pelo WhatsApp com o valor certo — geralmente em até 1 dia útil.</p>
        </div>
        <a href="/" class="btn btn-outline" style="margin-top:16px;display:inline-block;">← Voltar pro site</a>
    </div>
</section>
