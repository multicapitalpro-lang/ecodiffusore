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

<?php $payback = $result['payback'] ?? null; ?>
<?php if ($payback && $payback['tiers']['avg']['monthly'] > 0): ?>
<section class="buy-section alt">
    <div class="site-container">
        <h2 style="text-align:center;">Sua economia estimada com o Ecodiffusore</h2>
        <p class="section-sub" style="text-align:center;">Calculado com base nos dados que você informou.</p>

        <div class="calc-results" style="max-width:920px;margin:0 auto;">
            <div class="calc-result calc-min">
                <span class="calc-result-icon">🛡️</span>
                <span class="calc-result-title">5% – Mínimo Garantido</span>
                <span class="calc-result-label">Economia Mensal</span>
                <strong>R$ <?= number_format($payback['tiers']['min']['monthly'], 2, ',', '.') ?></strong>
                <small>R$ <?= number_format($payback['tiers']['min']['yearly'], 2, ',', '.') ?>/ano</small>
                <small>R$ <?= number_format($payback['tiers']['min']['five_year'], 2, ',', '.') ?> em 5 anos</small>
            </div>
            <div class="calc-result calc-avg">
                <span class="calc-badge">MAIS COMUM</span>
                <span class="calc-result-icon">📈</span>
                <span class="calc-result-title">10% – Média Real</span>
                <span class="calc-result-label">Economia Mensal</span>
                <strong>R$ <?= number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') ?></strong>
                <small>R$ <?= number_format($payback['tiers']['avg']['yearly'], 2, ',', '.') ?>/ano</small>
                <small>R$ <?= number_format($payback['tiers']['avg']['five_year'], 2, ',', '.') ?> em 5 anos</small>
            </div>
            <div class="calc-result calc-max">
                <span class="calc-result-icon">🚀</span>
                <span class="calc-result-title">20% – Potencial Máximo</span>
                <span class="calc-result-label">Economia Mensal</span>
                <strong>R$ <?= number_format($payback['tiers']['max']['monthly'], 2, ',', '.') ?></strong>
                <small>R$ <?= number_format($payback['tiers']['max']['yearly'], 2, ',', '.') ?>/ano</small>
                <small>R$ <?= number_format($payback['tiers']['max']['five_year'], 2, ',', '.') ?> em 5 anos</small>
            </div>
        </div>

        <?php if ($payback['payback_months']): ?>
            <p class="buy-price-summary" style="max-width:560px;margin:24px auto 0;text-align:center;">
                💰 Com a economia média, seu investimento se paga em aproximadamente
                <strong><?= $payback['payback_months'] < 1 ? 'menos de 1 mês' : ceil($payback['payback_months']) . ' meses' ?></strong>.
            </p>
        <?php endif; ?>

        <div class="table-scroll" style="max-width:700px;margin:24px auto 0;">
            <table class="payback-table">
                <thead><tr><th>Ano</th><th>Economia acumulada</th><th>Situação</th></tr></thead>
                <tbody>
                    <?php foreach ($payback['yearly_breakdown'] as $row): ?>
                        <tr>
                            <td>Ano <?= (int) $row['year'] ?></td>
                            <td>R$ <?= number_format($row['cumulative_savings'], 2, ',', '.') ?></td>
                            <td><?= $row['payback_reached'] ? '✅ Investimento recuperado' : 'Ainda recuperando o investimento' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="calc-disclaimer" style="text-align:center;">A economia varia de acordo com estilo de direção, tipo de carga e condições da estrada.</p>

        <div style="max-width:560px;margin:20px auto 0;">
            <a href="/comprar/orcamento/pdf" class="btn btn-outline" style="width:100%;text-align:center;display:block;">📄 Baixar PDF do orçamento</a>
        </div>
    </div>
</section>
<?php endif; ?>
