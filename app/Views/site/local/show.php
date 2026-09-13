<?php use App\Core\View; ?>
<section class="buy-hero">
    <div class="site-container">
        <h1>Economia de Diesel em <?= View::e($page['stateName']) ?></h1>
        <p>O Ecodiffusore reduz de 5% a 20% o consumo de diesel de caminhões, máquinas agrícolas e geradores — sistema patenteado (INPI), fabricado no Brasil, com rede de licenciados autorizados em <?= View::e($page['stateName']) ?>.</p>
    </div>
</section>

<section class="tecnologia">
    <div class="site-container">
        <span class="badge">Presença em <?= View::e($page['stateName']) ?></span>
        <h2>Rede de licenciados autorizados no estado</h2>
        <?php if ($cities): ?>
            <p class="section-sub">A Ecodiffusore Brasil já tem licenciados autorizados atendendo <?= View::e(implode(', ', $cities)) ?>, e em expansão constante por <?= View::e($page['stateName']) ?>.</p>
        <?php else: ?>
            <p class="section-sub">A Ecodiffusore Brasil está expandindo a rede de licenciados autorizados em <?= View::e($page['stateName']) ?>. Fale com a gente pelo WhatsApp que atendemos qualquer cidade do estado.</p>
        <?php endif; ?>
        <div class="grid-2 tecnologia-grid">
            <div class="tecnologia-card">
                <h3>Por que comprar da Ecodiffusore Brasil</h3>
                <ul class="check-list">
                    <li>Patente de produto e de marca registradas no INPI</li>
                    <li>Fabricação 100% nacional — sem espera de importação</li>
                    <li>Garantia de devolução se o mínimo de 5% de economia não for atingido</li>
                    <li>Nota fiscal e suporte técnico em português</li>
                </ul>
            </div>
            <div class="tecnologia-card">
                <h3>Atendimento em <?= View::e($page['stateName']) ?></h3>
                <p>Caminhões, máquinas agrícolas e geradores a diesel — compatível com a grande maioria dos modelos em uso no estado. Peça um orçamento personalizado informando sua cidade.</p>
                <a href="/comprar" class="btn btn-primary">Pedir orçamento</a>
            </div>
        </div>
    </div>
</section>

<section id="faq" class="faq">
    <div class="site-container">
        <h2>Perguntas frequentes sobre o Ecodiffusore em <?= View::e($page['stateName']) ?></h2>
        <div class="faq-list">
            <details>
                <summary>Tem licenciado Ecodiffusore em <?= View::e($page['stateName']) ?>?</summary>
                <p><?= $cities ? 'Sim — já atendemos ' . View::e(implode(', ', $cities)) . ', com rede em expansão pelo estado.' : 'A rede está em expansão no estado. Fale conosco pelo WhatsApp que confirmamos o atendimento pra sua cidade.' ?></p>
            </details>
            <details>
                <summary>O Ecodiffusore funciona em qualquer cidade de <?= View::e($page['stateName']) ?>?</summary>
                <p>Sim. Mesmo fora das cidades com licenciado já estabelecido, a Ecodiffusore Brasil atende qualquer cidade do estado — o envio e o suporte funcionam normalmente.</p>
            </details>
            <details>
                <summary>Quanto custa o Ecodiffusore?</summary>
                <p>O valor varia conforme o tipo de veículo ou máquina. <a href="/comprar">Peça um orçamento personalizado</a> — é rápido e sem compromisso.</p>
            </details>
        </div>
    </div>
</section>

<div class="site-container" style="padding:20px 20px 60px;">
    <div class="blog-cta">
        <h3>Quer economizar diesel em <?= View::e($page['stateName']) ?>?</h3>
        <p>Peça um orçamento personalizado e veja quanto você pode economizar no seu veículo, máquina agrícola ou gerador.</p>
        <a href="/comprar" class="btn btn-primary">Pedir orçamento</a>
    </div>
</div>
