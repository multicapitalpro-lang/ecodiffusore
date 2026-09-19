<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Approval;
use App\Models\PricingTier;
/** @var array $result */
/** @var array|null $pendingApproval */
$isModal = $isModal ?? false;
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

<?php if (!empty($pendingApproval)): ?>
    <!-- Fase 57c: pedido do usuario -- preco abaixo do piso do Vendedor nem chega a mostrar a
         proposta completa (economia/parcelas/PDF/WhatsApp com o preco baixo pro cliente) antes
         de aprovado. So essa confirmacao curta ate o Gestor/Licenciado decidir. -->
    <?php $statusLabel = Approval::statusLabel($pendingApproval); ?>
    <div class="form-msg" style="background:#fff4dc;color:#b7791f;max-width:560px;">
        <strong>⏳ <?= View::e($statusLabel) ?></strong>
        <p style="margin:6px 0 0;">
            Você pediu <strong>R$ <?= number_format((float) $pendingApproval['requested_price'], 2, ',', '.') ?></strong> pro cliente <?= View::e($result['name']) ?>
            — abaixo do preço padrão de R$ <?= number_format(PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?>.
        </p>
        <p style="margin:6px 0 0;">Você recebe um aviso no WhatsApp a cada etapa decidida. Recarregue esta mesma página (ou abra o orçamento pelo link "Ver no CRM" abaixo, ou confira em "Liberação de Preço" no menu) pra ver o status mais atual. Assim que a liberação for concluída, a proposta completa libera — <strong>não crie uma proposta nova</strong>, senão vira outro pedido de liberação do zero.</p>
    </div>

    <div class="proposta-actions" style="margin-top:18px;">
        <?php if ($isModal): ?>
            <button type="button" id="btn-proposta-nova" class="btn btn-outline">+ Nova proposta</button>
        <?php else: ?>
            <a href="/painel/proposta-facil" class="btn btn-outline">+ Nova proposta</a>
        <?php endif; ?>
        <?php if ($result['quote_id']): ?>
            <a href="/painel/orcamentos/<?= (int) $result['quote_id'] ?>" class="btn btn-outline">Ver no CRM</a>
        <?php endif; ?>
        <a href="/painel/liberacoes" class="btn btn-outline">Ver status da liberação</a>
    </div>
<?php else: ?>
    <?php
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
        . ($result['product_price'] ? 'Investimento: R$ ' . number_format((float) $result['product_price'], 2, ',', '.') . "\n" : '')
        . $economyLine . "\n"
        . 'Qualquer dúvida, me chama por aqui — ' . $result['seller_name'];

    // Fase 68: Pedido ja registrado -- link publico do checkout (/pedido/{token}) pronto pra
    // mandar, em vez do resumo informal acima. O comprador escolhe a forma de pagamento sozinho
    // por la (Fase 63/64), nao mais o vendedor aqui.
    $isConcluded = !empty($result['order_id']);
    $publicLink = $isConcluded && !empty($result['public_token']) ? 'https://ecodiffusorebrasil.com.br/pedido/' . $result['public_token'] : null;
    $concludedMessage = $publicLink
        ? "Olá, {$result['name']}! Sua proposta do Ecodiffusore ficou pronta. Pra confirmar e escolher a forma de pagamento (Pix, Boleto ou Cartão), acesse: {$publicLink}"
        : $message;
    ?>
    <p class="hint-text" style="margin-top:0;">Gerada agora — pronta pra compartilhar com o cliente.</p>

    <?php if (!empty($_GET['erro_concluir'])): ?>
        <p class="form-msg form-msg-erro">Não foi possível concluir o pedido: <?= View::e($_GET['erro_concluir']) ?></p>
    <?php endif; ?>

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
            <strong style="font-size:1.3rem;color:var(--green-dark);">R$ <?= number_format((float) $result['product_price'], 2, ',', '.') ?></strong>
            <?= $result['product_name'] ? '— ' . View::e($result['product_name']) : '' ?>
            <span class="tag-default"><?= (int) $result['quantidade'] ?>x R$ <?= number_format((float) $result['unit_price'], 2, ',', '.') ?></span>
        </p>

        <h3>Formas de pagamento</h3>
        <p>Pix, boleto ou cartão à vista pelo preço acima. Parcelado no cartão:</p>

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

    <?php if ($isConcluded): ?>
        <div class="form-msg" style="background:#e5f6e0;color:#1f7a33;max-width:640px;">
            <strong>✅ Pedido #<?= (int) $result['order_id'] ?> criado!</strong>
            <p style="margin:6px 0 0;">Agora é só mandar o link abaixo pro cliente — ele escolhe a forma de pagamento (Pix, Boleto ou Cartão) e paga direto por lá, sem precisar de login.</p>
            <?php if ($publicLink): ?>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:12px;">
                    <input type="text" id="proposta-public-link" readonly value="<?= View::e($publicLink) ?>" style="flex:1; min-width:220px; font-size:.82rem; padding:8px 10px; border-radius:6px; border:1px solid var(--border);">
                    <button type="button" class="btn btn-outline btn-sm" id="proposta-public-link-copy">Copiar link</button>
                </div>
            <?php endif; ?>
        </div>
        <script>
        document.getElementById('proposta-public-link-copy')?.addEventListener('click', function () {
            var input = document.getElementById('proposta-public-link');
            input.select();
            navigator.clipboard?.writeText(input.value);
            this.textContent = 'Copiado!';
            setTimeout(() => { this.textContent = 'Copiar link'; }, 2000);
        });
        </script>
    <?php elseif ($result['quote_id'] && $result['product_price']): ?>
        <h3>Concluir pedido</h3>
        <p class="hint-text" style="margin-top:0;">Registra o Pedido de verdade e libera o link de pagamento pra você mandar pro cliente — ele escolhe Pix, Boleto ou Cartão sozinho, no checkout dele.</p>
        <p class="hint-text" style="margin-top:0;">CPF/CNPJ é opcional aqui — se não informar agora, o cliente completa depois em "Meus Dados" ou direto no checkout, no painel dele.</p>
        <form action="/painel/proposta-facil/concluir" method="post" class="panel-form-wide">
            <?= Csrf::field() ?>
            <label for="proposta-document">CPF ou CNPJ do cliente (opcional)</label>
            <input type="text" id="proposta-document" name="document" placeholder="Só números — pode deixar em branco">
            <button type="submit" class="btn btn-primary" style="margin-top:14px;">Concluir pedido</button>
        </form>
    <?php endif; ?>

    <div class="proposta-actions">
        <a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= rawurlencode($concludedMessage) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp"><?= $isConcluded ? '💬 Enviar link por WhatsApp' : '💬 Compartilhar por WhatsApp' ?></a>
        <a href="/painel/proposta-facil/pdf" target="_blank" class="btn btn-outline">📄 Baixar PDF</a>
        <?php if ($isModal): ?>
            <button type="button" id="btn-proposta-nova" class="btn btn-outline">+ Nova proposta</button>
        <?php else: ?>
            <a href="/painel/proposta-facil" class="btn btn-outline">+ Nova proposta</a>
        <?php endif; ?>
        <?php if ($isConcluded): ?>
            <a href="/painel/pedidos/<?= (int) $result['order_id'] ?>" class="btn btn-outline">Ver no CRM</a>
        <?php elseif ($result['quote_id']): ?>
            <a href="/painel/orcamentos/<?= (int) $result['quote_id'] ?>" class="btn btn-outline">Ver no CRM</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if ($isModal): ?>
</div>
<?php endif; ?>
