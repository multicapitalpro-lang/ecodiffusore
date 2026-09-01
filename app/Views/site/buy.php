<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $products */
/** @var string $checkoutName */
/** @var string $checkoutWhatsapp */
/** @var string $checkoutCity */
/** @var int|null $ref */
$erroCobranca = $_GET['erro_cobranca'] ?? null;
?>
<section class="buy-hero">
    <div class="site-container">
        <h1>Ecodiffusore — economia real de combustível</h1>
        <p>Dispositivo patenteado (INPI) que reduz o consumo de diesel e aumenta a performance do seu veículo, máquina ou gerador.</p>
    </div>
</section>

<section class="buy-section">
    <div class="site-container">
        <h2>Laudo técnico e detalhes de instalação</h2>
        <div class="buy-laudo-pending">
            <p><strong>Laudo técnico completo em breve nesta seção.</strong></p>
            <p>Enquanto isso, saiba que o Ecodiffusore é instalado entre o TBI e o filtro de ar, usando lâminas magnetizadas que aumentam a sucção de ar e melhoram a combustão — resultando em economia real de diesel e mais performance, sem alterar a garantia de fábrica do motor.</p>
        </div>
    </div>
</section>

<section class="buy-section alt" id="finalizar">
    <div class="site-container">
        <h2 style="text-align:center;">Finalizar compra</h2>

        <?php if (isset($_GET['erro']) && $_GET['erro'] === 'csrf'): ?>
            <p class="form-msg" style="background:#fdeaea;color:#b3261e;max-width:560px;margin:0 auto 16px;">Sessão expirada, tente novamente.</p>
        <?php elseif (isset($_GET['erro'])): ?>
            <p class="form-msg" style="background:#fdeaea;color:#b3261e;max-width:560px;margin:0 auto 16px;">Preencha todos os campos obrigatórios.</p>
        <?php endif; ?>
        <?php if ($erroCobranca): ?>
            <p class="form-msg" style="background:#fdeaea;color:#b3261e;max-width:560px;margin:0 auto 16px;">Não foi possível gerar o pagamento: <?= View::e($erroCobranca) ?></p>
        <?php endif; ?>

        <form action="/comprar/pagamento" method="post" class="buy-checkout-box" id="buy-form">
            <?= Csrf::field() ?>

            <label for="buy-name">Nome completo</label>
            <input type="text" id="buy-name" name="name" value="<?= View::e($checkoutName) ?>" required>

            <label for="buy-whatsapp">WhatsApp</label>
            <input type="text" id="buy-whatsapp" name="whatsapp" value="<?= View::e($checkoutWhatsapp) ?>" required>

            <label for="buy-city">Cidade</label>
            <input type="text" id="buy-city" name="city" value="<?= View::e($checkoutCity) ?>">

            <label for="buy-email">E-mail</label>
            <input type="email" id="buy-email" name="email">

            <label for="buy-document">CPF ou CNPJ</label>
            <input type="text" id="buy-document" name="document" required>

            <label for="buy-product">Produto</label>
            <select id="buy-product" name="product_id" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" data-price="<?= (float) $p['price_cash'] ?>">
                        <?= View::e($p['name']) ?> — R$ <?= number_format((float) $p['price_cash'], 2, ',', '.') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Forma de pagamento</label>
            <div class="buy-payment-methods">
                <label><input type="radio" name="billing_type" value="PIX" checked> Pix à vista</label>
                <label><input type="radio" name="billing_type" value="BOLETO"> Boleto</label>
                <label><input type="radio" name="billing_type" value="CREDIT_CARD"> Cartão</label>
            </div>

            <div class="buy-installments-row" id="buy-installments-row">
                <label for="buy-installments">Parcelas</label>
                <select id="buy-installments" name="installments">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?>x</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="buy-price-summary" id="buy-price-summary"></div>

            <button type="submit" class="btn btn-primary">Confirmar e pagar</button>
        </form>
    </div>
</section>
