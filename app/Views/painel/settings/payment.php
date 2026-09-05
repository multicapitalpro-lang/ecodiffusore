<?php
use App\Core\Csrf;
use App\Core\View;
$values = $settings;
$sucesso = isset($_GET['sucesso']);
?>
<h1>Configurações de Pagamento</h1>
<p class="hint-text" style="margin-top:0;">Taxas do cartão e da antecipação repassadas ao cliente na hora da venda — o preço do produto (tabela por quantidade) fica em <a href="/painel/tabela-precos">Tabela de preços</a>. O cliente nunca vê essas taxas separadas, só o valor final da parcela.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso — os novos valores já valem pra próxima cobrança gerada.</p>
<?php endif; ?>

<div class="proposta-installments-note">
    ⚠️ Valores padrão são as taxas <strong>cheias/regulares</strong> da Asaas. A Asaas costuma oferecer
    desconto promocional por 3 meses (ex: cartão à vista 1,99% em vez de 2,99%) — se quiser repassar
    esse desconto agora, edite os campos abaixo, mas lembre de revisar quando a promoção expirar.
</div>

<form action="/painel/configuracoes/pagamento" method="post" class="panel-form-wide">
    <?= Csrf::field() ?>

    <h3 style="margin-top:0;">Cartão de crédito à vista (1x)</h3>
    <div class="form-grid-2">
        <div>
            <label for="card_fee_avista_pct">Taxa da Asaas (%)</label>
            <input type="number" id="card_fee_avista_pct" name="card_fee_avista_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['card_fee_avista_pct'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="card_fee_avista_pct"><?= View::e($errors['card_fee_avista_pct'] ?? '') ?></p>
        </div>
        <div>
            <label for="antecipacao_avista_mensal_pct">Antecipação (% ao mês, 1 mês fixo)</label>
            <input type="number" id="antecipacao_avista_mensal_pct" name="antecipacao_avista_mensal_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['antecipacao_avista_mensal_pct'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="antecipacao_avista_mensal_pct"><?= View::e($errors['antecipacao_avista_mensal_pct'] ?? '') ?></p>
        </div>
    </div>

    <h3>Cartão de crédito parcelado (2x até o máximo abaixo)</h3>
    <div class="form-grid-2">
        <div>
            <label for="card_fee_parcelado_pct">Taxa da Asaas (%)</label>
            <input type="number" id="card_fee_parcelado_pct" name="card_fee_parcelado_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['card_fee_parcelado_pct'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="card_fee_parcelado_pct"><?= View::e($errors['card_fee_parcelado_pct'] ?? '') ?></p>
        </div>
        <div>
            <label for="antecipacao_parcelado_mensal_pct">Antecipação (% ao mês, média por parcela)</label>
            <input type="number" id="antecipacao_parcelado_mensal_pct" name="antecipacao_parcelado_mensal_pct" step="0.01" min="0" max="100" value="<?= View::e((string) ($values['antecipacao_parcelado_mensal_pct'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="antecipacao_parcelado_mensal_pct"><?= View::e($errors['antecipacao_parcelado_mensal_pct'] ?? '') ?></p>
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="card_fixed_fee">Taxa fixa por venda no cartão (R$)</label>
            <input type="number" id="card_fixed_fee" name="card_fixed_fee" step="0.01" min="0" value="<?= View::e((string) ($values['card_fixed_fee'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="card_fixed_fee"><?= View::e($errors['card_fixed_fee'] ?? '') ?></p>
        </div>
        <div>
            <label for="max_installments">Máximo de parcelas no cartão</label>
            <input type="number" id="max_installments" name="max_installments" step="1" min="1" max="21" value="<?= View::e((string) ($values['max_installments'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="max_installments"><?= View::e($errors['max_installments'] ?? '') ?></p>
        </div>
    </div>

    <h3>Pix e Boleto</h3>
    <p class="hint-text" style="margin-top:0;">Taxa fixa por transação da Asaas — fica por conta da empresa, não é repassada ao cliente (Pix/Boleto continuam com o preço de tabela puro). Só pra referência de custo.</p>
    <div class="form-grid-2">
        <div>
            <label for="pix_fee">Taxa Pix (R$)</label>
            <input type="number" id="pix_fee" name="pix_fee" step="0.01" min="0" value="<?= View::e((string) ($values['pix_fee'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="pix_fee"><?= View::e($errors['pix_fee'] ?? '') ?></p>
        </div>
        <div>
            <label for="boleto_fee">Taxa Boleto (R$)</label>
            <input type="number" id="boleto_fee" name="boleto_fee" step="0.01" min="0" value="<?= View::e((string) ($values['boleto_fee'] ?? '')) ?>" required>
            <p class="field-error" data-error-for="boleto_fee"><?= View::e($errors['boleto_fee'] ?? '') ?></p>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Salvar</button>
</form>
