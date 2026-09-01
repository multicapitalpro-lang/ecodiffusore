<?php
use App\Core\View;
/** @var array $result */
$hasSeller = !empty($result['seller_whatsapp']);
$whatsappNumber = $hasSeller ? '55' . preg_replace('/\D/', '', $result['seller_whatsapp']) : '5545991021551';
$whatsappLabel = $hasSeller ? 'Falar com ' . $result['seller_name'] : 'Falar com o Atendimento Ecodiffusore';

$message = "Olá! Meu nome é " . ($result['name'] ?? '') . ", pedi um orçamento do Ecodiffusore pelo site.\n"
    . "Veículo: {$result['brand']} {$result['year']}, placa {$result['plate']}, potência {$result['power']}.\n"
    . 'Motor: ' . ($result['ecu_status'] === 'original' ? 'Original de fábrica' : 'Reprogramado (chip)') . '.';
?>
<section class="buy-hero">
    <div class="site-container">
        <h1>Seu orçamento está pronto</h1>
        <p>Confira os dados e fale com a gente pra fechar sua compra.</p>
    </div>
</section>

<section class="buy-section">
    <div class="site-container">
        <div class="buy-checkout-box">
            <h3 style="margin-top:0;">Seu veículo</h3>
            <table class="orcamento-table">
                <tr><td>Placa</td><td><strong><?= View::e($result['plate']) ?></strong></td></tr>
                <tr><td>Ano modelo</td><td><?= View::e($result['year']) ?></td></tr>
                <tr><td>Marca</td><td><?= View::e($result['brand']) ?></td></tr>
                <tr><td>Potência</td><td><?= View::e($result['power'] ?: '—') ?></td></tr>
                <tr><td>Motor</td><td><?= $result['ecu_status'] === 'original' ? 'Original de fábrica' : 'Reprogramado (chip)' ?></td></tr>
            </table>

            <?php if ($result['product_price']): ?>
                <h3>Investimento estimado</h3>
                <p class="buy-price-summary">
                    <?= $result['product_is_exact_match'] ? '' : 'A partir de ' ?>
                    <strong>R$ <?= number_format((float) $result['product_price'], 2, ',', '.') ?></strong>
                    <?= $result['product_name'] ? '— ' . View::e($result['product_name']) : '' ?>
                </p>
                <p class="hint-text">À vista (Pix ou Boleto) ou parcelado em até 12x no cartão. Condições finais confirmadas com o vendedor.</p>
            <?php endif; ?>

            <h3><?= $hasSeller ? 'Encontramos um Licenciado perto de você' : 'Fale com nosso atendimento' ?></h3>
            <p><?= $hasSeller ? 'Ele já vai te atender considerando os dados que você informou.' : 'Não encontramos um Licenciado na sua região ainda, mas nosso atendimento geral cuida de você.' ?></p>
            <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= rawurlencode($message) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp" style="width:100%;text-align:center;display:block;">💬 <?= View::e($whatsappLabel) ?></a>
        </div>
    </div>
</section>
