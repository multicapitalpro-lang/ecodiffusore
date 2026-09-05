<?php
use App\Core\View;
/** @var array $result */
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
<h1>Proposta para <?= View::e($result['name']) ?></h1>
<p class="hint-text">Gerada agora — pronta pra compartilhar com o cliente.</p>

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

    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Parcelas</th>
                    <th>Valor da parcela</th>
                    <th>Total</th>
                    <?php if ($hasPayback): ?><th>Economia média no mês</th><th>Diferença</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['installments'] as $row): ?>
                    <?php $diff = $hasPayback ? $payback['tiers']['avg']['monthly'] - $row['parcela'] : null; ?>
                    <tr>
                        <td><?= $row['n'] ?>x</td>
                        <td>R$ <?= number_format($row['parcela'], 2, ',', '.') ?></td>
                        <td>R$ <?= number_format($row['total'], 2, ',', '.') ?></td>
                        <?php if ($hasPayback): ?>
                            <td>R$ <?= number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($diff >= 0): ?>
                                    <strong style="color:var(--green-dark);">Sobra R$ <?= number_format($diff, 2, ',', '.') ?></strong>
                                <?php else: ?>
                                    Falta R$ <?= number_format(abs($diff), 2, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="hint-text">"Diferença" compara a parcela do produto com a economia média mensal estimada de diesel — quando positiva, o produto se paga sozinho todo mês.</p>
<?php endif; ?>

<?php if ($hasPayback): ?>
    <h3>Retorno do investimento, ano a ano</h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Ano</th><th>Economia acumulada</th><th>Resultado líquido</th></tr></thead>
            <tbody>
                <?php foreach ($payback['yearly_breakdown'] as $row): ?>
                    <tr>
                        <td>Ano <?= (int) $row['year'] ?></td>
                        <td>R$ <?= number_format($row['cumulative_savings'], 2, ',', '.') ?></td>
                        <td>
                            <?php if ($row['net_gain'] >= 0): ?>
                                <strong style="color:var(--green-dark);">+ R$ <?= number_format($row['net_gain'], 2, ',', '.') ?></strong>
                            <?php else: ?>
                                Faltam R$ <?= number_format(abs($row['net_gain']), 2, ',', '.') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="proposta-actions">
    <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= rawurlencode($message) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp">💬 Compartilhar por WhatsApp</a>
    <a href="/painel/proposta-facil/pdf" class="btn btn-outline">📄 Baixar PDF</a>
    <a href="/painel/proposta-facil" class="btn btn-outline">+ Nova proposta</a>
    <?php if ($result['quote_id']): ?>
        <a href="/painel/orcamentos/<?= (int) $result['quote_id'] ?>" class="btn btn-outline">Ver no CRM</a>
    <?php endif; ?>
</div>
