<?php
use App\Core\Csrf;
use App\Core\Money;
use App\Core\View;
/** @var array $products */
/** @var array|null $result */
/** @var array $values */
/** @var array $errors */
/** @var ?string $secondaryCurrency Fase 111: so' preenchido pro Licenciado/rede com operacao fora do Brasil */
/** @var float $rate */
$v = fn (string $k, string $default = '') => View::e((string) ($values[$k] ?? $default));
$currency = $values['currency'] ?? 'BRL';
$fmt = fn (float $brl) => Money::format($brl, $currency, $rate ?? 0.0);
?>
<div class="page-header">
    <h1>Simulador de economia</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Preencha na frente do cliente (call, WhatsApp, visita) pra mostrar a economia estimada com o Ecodiffusore na hora.</p>

<?php if (!empty($errors['geral'])): ?>
    <p class="field-error"><?= View::e($errors['geral']) ?></p>
<?php endif; ?>

<form method="post" action="/painel/simulador" class="panel-form-wide" style="max-width:640px;">
    <?= Csrf::field() ?>

    <div class="form-grid-2">
        <div>
            <label for="client_name">Nome do cliente (opcional)</label>
            <input type="text" id="client_name" name="client_name" value="<?= $v('client_name') ?>">
        </div>
        <div>
            <label for="client_whatsapp">WhatsApp do cliente (opcional)</label>
            <input type="text" id="client_whatsapp" name="client_whatsapp" value="<?= $v('client_whatsapp') ?>" placeholder="(45) 99999-0000">
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="product_id">Produto</label>
            <select id="product_id" name="product_id">
                <option value="">— Selecionar produto —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (string) ($values['product_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>>
                        <?= View::e($p['name']) ?> — R$ <?= number_format((float) $p['price_cash'], 2, ',', '.') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="manual_price">Ou valor manual (R$)</label>
            <input type="text" id="manual_price" name="manual_price" value="<?= $v('manual_price') ?>" placeholder="Preenche só se não selecionar produto">
            <p class="field-error"><?= View::e($errors['product_id'] ?? '') ?></p>
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="km_mensal">Km rodados por mês</label>
            <input type="text" id="km_mensal" name="km_mensal" value="<?= $v('km_mensal') ?>" placeholder="Ex: 12000">
            <p class="field-error"><?= View::e($errors['km_mensal'] ?? '') ?></p>
        </div>
        <div>
            <label for="km_litro">Consumo (km/litro)</label>
            <input type="text" id="km_litro" name="km_litro" value="<?= $v('km_litro') ?>" placeholder="Ex: 2,8">
            <p class="field-error"><?= View::e($errors['km_litro'] ?? '') ?></p>
        </div>
    </div>

    <?php if ($secondaryCurrency): ?>
        <div class="form-grid-2">
            <div>
                <label for="currency">Moeda</label>
                <select id="currency" name="currency">
                    <option value="BRL" <?= $currency === 'BRL' ? 'selected' : '' ?>>Real (R$)</option>
                    <option value="<?= View::e($secondaryCurrency) ?>" <?= $currency === $secondaryCurrency ? 'selected' : '' ?>><?= View::e(Money::label($secondaryCurrency)) ?></option>
                </select>
            </div>
            <div>
                <label for="preco_diesel">Preço do diesel (<span id="diesel-unit-label"><?= $currency === 'BRL' ? 'R$' : Money::symbol($secondaryCurrency) ?></span>/litro)</label>
                <input type="text" id="preco_diesel" name="preco_diesel" value="<?= $v('preco_diesel') ?>" placeholder="Ex: 6,10">
                <p class="field-error"><?= View::e($errors['preco_diesel'] ?? '') ?></p>
            </div>
        </div>
        <script>
        (function () {
            var select = document.getElementById('currency');
            var label = document.getElementById('diesel-unit-label');
            if (!select || !label) return;
            select.addEventListener('change', function () {
                label.textContent = select.value === 'BRL' ? 'R$' : <?= json_encode(Money::symbol($secondaryCurrency)) ?>;
            });
        })();
        </script>
    <?php else: ?>
        <label for="preco_diesel">Preço do diesel (R$/litro)</label>
        <input type="text" id="preco_diesel" name="preco_diesel" value="<?= $v('preco_diesel') ?>" placeholder="Ex: 6,10" style="max-width:220px;">
        <p class="field-error"><?= View::e($errors['preco_diesel'] ?? '') ?></p>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary" style="margin-top:16px;">Calcular economia</button>
</form>

<?php if ($result && $result['tiers']['avg']['monthly'] > 0): ?>
    <h3 class="section-title" style="margin-top:32px;">Economia estimada<?= !empty($values['client_name']) ? ' — ' . View::e($values['client_name']) : '' ?></h3>
    <?php if ($productName): ?>
        <p class="hint-text" style="margin-top:-8px;">Produto: <?= View::e($productName) ?> — <?= $fmt($productPrice) ?></p>
    <?php endif; ?>

    <?php if (!empty($chartSvg)): ?>
        <div class="chart-box" style="max-width:900px; margin-bottom:20px;">
            <div class="chart-bar-wrap"><?= $chartSvg ?></div>
        </div>
    <?php endif; ?>

    <div class="proposta-tiers" style="max-width:900px;">
        <div class="proposta-tier">
            <span class="proposta-tier-title">🛡️ 5% — Mínimo garantido</span>
            <strong><?= $fmt($result['tiers']['min']['monthly']) ?></strong>
            <small>por mês</small>
            <small><?= $fmt($result['tiers']['min']['five_year']) ?> em 5 anos</small>
        </div>
        <div class="proposta-tier is-avg">
            <span class="proposta-tier-badge">MAIS COMUM</span>
            <span class="proposta-tier-title">📈 8% — Média real</span>
            <strong><?= $fmt($result['tiers']['avg']['monthly']) ?></strong>
            <small>por mês</small>
            <small><?= $fmt($result['tiers']['avg']['five_year']) ?> em 5 anos</small>
        </div>
        <div class="proposta-tier">
            <span class="proposta-tier-title">🚀 12% — Potencial máximo</span>
            <strong><?= $fmt($result['tiers']['max']['monthly']) ?></strong>
            <small>por mês</small>
            <small><?= $fmt($result['tiers']['max']['five_year']) ?> em 5 anos</small>
        </div>
    </div>

    <?php if ($result['payback_months']): ?>
        <div class="proposta-payback" style="max-width:900px;">
            💰 Com a economia média, o investimento se paga em aproximadamente
            <strong><?= $result['payback_months'] < 1 ? 'menos de 1 mês' : ceil($result['payback_months']) . ' meses' ?></strong>
        </div>
    <?php endif; ?>

    <div class="table-scroll" style="max-width:900px; margin-top:20px;">
        <table class="data-table">
            <thead><tr><th>Ano</th><th>Economia acumulada</th><th>Lucro líquido acumulado</th></tr></thead>
            <tbody>
                <?php foreach ($result['yearly_breakdown'] as $row): ?>
                    <tr>
                        <td>Ano <?= (int) $row['year'] ?></td>
                        <td><?= $fmt($row['cumulative_savings']) ?></td>
                        <td>
                            <?php if ($row['net_gain'] >= 0): ?>
                                <span class="text-green">+ <?= $fmt($row['net_gain']) ?></span>
                            <?php else: ?>
                                Faltam <?= $fmt(abs($row['net_gain'])) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; max-width:900px;">
        <form method="post" action="/painel/simulador/pdf">
            <?= Csrf::field() ?>
            <?php foreach (['client_name', 'client_whatsapp', 'product_id', 'manual_price', 'km_mensal', 'km_litro', 'preco_diesel', 'currency'] as $field): ?>
                <input type="hidden" name="<?= $field ?>" value="<?= $v($field) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn btn-outline">📄 Baixar PDF</button>
        </form>

        <?php if (!empty($values['client_whatsapp'])): ?>
            <?php
            $waNumber = '55' . preg_replace('/\D/', '', $values['client_whatsapp']);
            $waMessage = 'Olá' . (!empty($values['client_name']) ? ', ' . $values['client_name'] : '') . '! Simulei sua economia com o Ecodiffusore: '
                . 'em média ' . $fmt($result['tiers']['avg']['monthly']) . '/mês de economia no diesel'
                . ($result['payback_months'] ? ', com retorno do investimento em cerca de ' . ceil($result['payback_months']) . ' meses.' : '.');
            ?>
            <a href="https://wa.me/<?= $waNumber ?>?text=<?= rawurlencode($waMessage) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp">💬 Enviar por WhatsApp</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
