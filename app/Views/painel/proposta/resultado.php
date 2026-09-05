<?php
use App\Core\View;
/** @var array $result */
$isModal = $isModal ?? false;
$payback = $result['payback'] ?? null;
$hasPayback = $payback && $payback['tiers']['avg']['monthly'] > 0;
$whatsappNumber = '55' . preg_replace('/\D/', '', $result['whatsapp']);

$economyLine = $hasPayback
    ? 'Com a economia média (R$ ' . number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') . '/mês), o Ecodiffusore se paga em '
        . ($payback['payback_months'] < 1 ? 'menos de 1 mês' : ceil($payback['payback_months']) . ' meses') . '.'
    : '';

$message = "Olá, {$result['name']}! Segue a proposta do Ecodiffusore que preparei pra você.\n"
    . "Veículo: {$result['brand']}" . ($result['model'] ? " {$result['model']}" : '') . ", {$result['year']}.\n"
    . ($result['product_name'] ? 'Produto: ' . $result['product_name'] . "\n" : '')
    . ($result['product_price'] ? 'Investimento: ' . ($result['product_is_exact_match'] ? '' : 'a partir de ') . 'R$ ' . number_format((float) $result['product_price'], 2, ',', '.') . "\n" : '')
    . $economyLine . "\n"
    . 'Qualquer dúvida, me chama por aqui — ' . $result['seller_name'];
?>
<?php if ($isModal): ?>
<div class="modal-header">
    <h2>Proposta para <?= View::e($result['name']) ?></h2>
    <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
</div>
<div class="modal-body">
<?php else: ?>
<h1>Proposta para <?= View::e($result['name']) ?></h1>
<?php endif; ?>
<p class="hint-text" style="margin-top:0;">Gerada agora — pronta pra compartilhar com o cliente.</p>

<?php if ($hasPayback): ?>
    <h3>Quanto ele vai economizar</h3>
    <div class="proposta-tiers">
        <div class="proposta-tier">
            <span class="proposta-tier-title">🛡️ 5% — Mínimo garantido</span>
            <strong>R$ <?= number_format($payback['tiers']['min']['monthly'], 2, ',', '.') ?></strong>
            <small>por mês</small>
            <small>R$ <?= number_format($payback['tiers']['min']['five_year'], 2, ',', '.') ?> em 5 anos</small>
        </div>
        <div class="proposta-tier is-avg">
            <span class="proposta-tier-badge">MAIS COMUM</span>
            <span class="proposta-tier-title">📈 8% — Média real</span>
            <strong>R$ <?= number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') ?></strong>
            <small>por mês</small>
            <small>R$ <?= number_format($payback['tiers']['avg']['five_year'], 2, ',', '.') ?> em 5 anos</small>
        </div>
        <div class="proposta-tier">
            <span class="proposta-tier-title">🚀 12% — Potencial máximo</span>
            <strong>R$ <?= number_format($payback['tiers']['max']['monthly'], 2, ',', '.') ?></strong>
            <small>por mês</small>
            <small>R$ <?= number_format($payback['tiers']['max']['five_year'], 2, ',', '.') ?> em 5 anos</small>
        </div>
    </div>

    <?php if ($payback['payback_months']): ?>
        <div class="proposta-payback">
            💰 Com a economia média, o investimento se paga em aproximadamente
            <strong><?= $payback['payback_months'] < 1 ? 'menos de 1 mês' : ceil($payback['payback_months']) . ' meses' ?></strong>.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($result['product_price']): ?>
    <h3>Investimento</h3>
    <p style="font-size:1.1rem;">
        <?= $result['product_is_exact_match'] ? '' : 'A partir de ' ?>
        <strong style="font-size:1.3rem;color:var(--green-dark);">R$ <?= number_format((float) $result['product_price'], 2, ',', '.') ?></strong>
        <?= $result['product_name'] ? '— ' . View::e($result['product_name']) : '' ?>
    </p>
    <p class="hint-text">Preço padrão do produto — em breve será ajustado pra um valor único de tabela.</p>

    <h3>Formas de pagamento</h3>
    <p>Pix ou cartão à vista pelo preço acima. Parcelado no cartão:</p>

    <div class="proposta-installments-note">
        ⚠️ Tabela provisória, calculada com a taxa de cartão (2,99% + antecipação). A tabela de juros
        própria da Ecodiffusore pro parcelamento ainda será enviada e vai substituir estes valores.
    </div>

    <?php if ($hasPayback): ?>
        <p class="hint-text" style="margin-top:0;">Economia média estimada de diesel: <strong style="color:var(--green-dark);">R$ <?= number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') ?>/mês</strong> — comparada com a parcela em "Diferença" abaixo.</p>
    <?php endif; ?>

    <div class="proposta-list">
        <?php foreach ($result['installments'] as $row): ?>
            <?php $diff = $hasPayback ? $payback['tiers']['avg']['monthly'] - $row['parcela'] : null; ?>
            <div class="proposta-list-row">
                <span class="proposta-list-n"><?= $row['n'] ?>x</span>
                <span class="proposta-list-main">
                    <strong>R$ <?= number_format($row['parcela'], 2, ',', '.') ?></strong>
                    <small>Total R$ <?= number_format($row['total'], 2, ',', '.') ?></small>
                </span>
                <?php if ($hasPayback): ?>
                    <span class="proposta-list-tag <?= $diff >= 0 ? 'is-positive' : 'is-negative' ?>">
                        <?= $diff >= 0 ? 'Sobra R$ ' . number_format($diff, 2, ',', '.') : 'Falta R$ ' . number_format(abs($diff), 2, ',', '.') ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="hint-text">"Diferença" compara a parcela do produto com a economia média mensal estimada de diesel — quando positiva, o produto se paga sozinho todo mês.</p>
<?php endif; ?>

<?php if ($hasPayback): ?>
    <h3>Retorno do investimento, ano a ano</h3>
    <div class="proposta-list">
        <?php foreach ($payback['yearly_breakdown'] as $row): ?>
            <div class="proposta-list-row">
                <span class="proposta-list-n">Ano <?= (int) $row['year'] ?></span>
                <span class="proposta-list-main">
                    <small>Economia acumulada</small>
                    R$ <?= number_format($row['cumulative_savings'], 2, ',', '.') ?>
                </span>
                <span class="proposta-list-tag <?= $row['net_gain'] >= 0 ? 'is-positive' : 'is-negative' ?>">
                    <?= $row['net_gain'] >= 0 ? '+ R$ ' . number_format($row['net_gain'], 2, ',', '.') : '− R$ ' . number_format(abs($row['net_gain']), 2, ',', '.') ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="proposta-actions">
    <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= rawurlencode($message) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp">💬 Compartilhar por WhatsApp</a>
    <a href="/painel/proposta-facil/pdf" target="_blank" class="btn btn-outline">📄 Baixar PDF</a>
    <?php if ($isModal): ?>
        <button type="button" id="btn-proposta-nova" class="btn btn-outline">+ Nova proposta</button>
    <?php else: ?>
        <a href="/painel/proposta-facil" class="btn btn-outline">+ Nova proposta</a>
    <?php endif; ?>
    <?php if ($result['quote_id']): ?>
        <a href="/painel/orcamentos/<?= (int) $result['quote_id'] ?>" class="btn btn-outline">Ver no CRM</a>
    <?php endif; ?>
</div>
<?php if ($isModal): ?>
</div>
<?php endif; ?>
