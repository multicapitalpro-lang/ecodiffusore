<?php
use App\Core\Csrf;
use App\Core\Money;
use App\Core\View;
/** @var array|null $result */
/** @var array $values */
/** @var array $errors */
/** @var float $rate */
$v = fn (string $k, string $default = '') => View::e((string) ($values[$k] ?? $default));
$currency = $values['currency'] ?? 'BRL';
$fmt = fn (float $brl) => Money::format($brl, $currency, $rate ?? 0.0);
$modo = $values['modo'] ?? 'km_rodados';
$pct = $values['pct_economia'] ?? '10';
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Calculadora de Locação — Ecodiffusore Brasil</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
<style>
    body { background: #f3f5f4; }
    .public-calc-wrap { max-width: 940px; margin: 0 auto; padding: 24px 16px 48px; }
    .public-calc-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
    .public-calc-header img { height: 36px; }
</style>
</head>
<body>
<div class="public-calc-wrap">
    <div class="public-calc-header">
        <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil">
    </div>

    <div class="page-header">
        <h1>Calculadora de Locação</h1>
    </div>
    <p class="hint-text" style="margin-top:-6px;">Informe o consumo atual da frota para calcular a média de km/l, projetar a economia com o Ecodiffusore e o retorno financeiro da locação.</p>

    <?php if (!empty($errors['geral'])): ?>
        <p class="field-error"><?= View::e($errors['geral']) ?></p>
    <?php endif; ?>

    <form method="post" action="/calculadora-locacao" class="panel-form-wide" style="max-width:900px;">
        <?= Csrf::field() ?>

        <div class="form-grid-2">
            <div>
                <label for="client_name">Nome do cliente (opcional)</label>
                <input type="text" id="client_name" name="client_name" value="<?= $v('client_name') ?>">
            </div>
            <div>
                <label for="currency">Moeda</label>
                <select id="currency" name="currency">
                    <option value="BRL" <?= $currency === 'BRL' ? 'selected' : '' ?>>Real (R$)</option>
                    <option value="PYG" <?= $currency === 'PYG' ? 'selected' : '' ?>>Guarani paraguaio (₲)</option>
                </select>
            </div>
        </div>

        <h3 class="section-title" style="margin-top:24px;">1. Consumo atual da frota (por veículo/mês)</h3>
        <div class="form-grid-2">
            <div>
                <label for="gasto_diesel">Gasto com diesel (<span id="cl-currency-symbol"><?= $currency === 'BRL' ? 'R$' : Money::symbol($currency) ?></span>/mês)</label>
                <input type="text" id="gasto_diesel" name="gasto_diesel" value="<?= $v('gasto_diesel') ?>" placeholder="Ex: 45.000,00">
                <p class="field-error"><?= View::e($errors['gasto_diesel'] ?? '') ?></p>
            </div>
            <div>
                <label for="preco_diesel">Preço do diesel (<span id="cl-currency-symbol-2"><?= $currency === 'BRL' ? 'R$' : Money::symbol($currency) ?></span>/L)</label>
                <input type="text" id="preco_diesel" name="preco_diesel" value="<?= $v('preco_diesel') ?>" placeholder="Ex: 6,200">
                <p class="field-error"><?= View::e($errors['preco_diesel'] ?? '') ?></p>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label class="checkbox-inline"><input type="radio" name="modo" value="km_rodados" <?= $modo === 'km_rodados' ? 'checked' : '' ?> data-modo-radio> Sei os km rodados/mês</label>
                <label class="checkbox-inline"><input type="radio" name="modo" value="media" <?= $modo === 'media' ? 'checked' : '' ?> data-modo-radio> Sei a média km/l</label>
            </div>
        </div>
        <div class="form-grid-2">
            <div data-modo-field="km_rodados" style="<?= $modo === 'media' ? 'display:none;' : '' ?>">
                <label for="km_rodados">Km rodados/mês</label>
                <input type="text" id="km_rodados" name="km_rodados" value="<?= $v('km_rodados') ?>" placeholder="Ex: 12000">
                <p class="field-error"><?= View::e($errors['km_rodados'] ?? '') ?></p>
            </div>
            <div data-modo-field="media" style="<?= $modo === 'km_rodados' ? 'display:none;' : '' ?>">
                <label for="media_kml">Média km/l</label>
                <input type="text" id="media_kml" name="media_kml" value="<?= $v('media_kml') ?>" placeholder="Ex: 2,8">
                <p class="field-error"><?= View::e($errors['media_kml'] ?? '') ?></p>
            </div>
        </div>

        <h3 class="section-title">2. Economia esperada com Ecodiffusore</h3>
        <label for="pct_economia">Percentual de economia de diesel (contratual: 5% a 30%)</label>
        <div style="display:flex; align-items:center; gap:12px; max-width:500px;">
            <input type="range" id="pct_economia" name="pct_economia" min="5" max="30" step="1" value="<?= View::e((string) $pct) ?>" style="flex:1;" oninput="document.getElementById('pct-economia-label').textContent = this.value + '%'">
            <strong id="pct-economia-label" style="min-width:48px; text-align:right;"><?= View::e((string) $pct) ?>%</strong>
        </div>
        <p class="field-error"><?= View::e($errors['pct_economia'] ?? '') ?></p>

        <h3 class="section-title">3. Condições comerciais da locação</h3>
        <div class="form-grid-2">
            <div>
                <label for="valor_adesao">Valor de adesão (único, por veículo)</label>
                <input type="text" id="valor_adesao" name="valor_adesao" value="<?= $v('valor_adesao') ?>" placeholder="Ex: 4.490,00">
                <p class="field-error"><?= View::e($errors['valor_adesao'] ?? '') ?></p>
            </div>
            <div>
                <label for="mensalidade">Mensalidade da locação (por veículo)</label>
                <input type="text" id="mensalidade" name="mensalidade" value="<?= $v('mensalidade') ?>" placeholder="Ex: 890,00">
                <p class="field-error"><?= View::e($errors['mensalidade'] ?? '') ?></p>
            </div>
        </div>
        <label class="checkbox-inline"><input type="checkbox" id="parcelar_adesao" name="parcelar_adesao" value="1" <?= !empty($values['parcelar_adesao']) ? 'checked' : '' ?> data-parcelar-toggle> Parcelar a adesão no cartão de crédito (opcional)</label>
        <div data-parcelas-field style="<?= empty($values['parcelar_adesao']) ? 'display:none;' : '' ?> margin-top:8px; max-width:220px;">
            <label for="parcelas_adesao">Em quantas vezes</label>
            <select id="parcelas_adesao" name="parcelas_adesao">
                <?php for ($n = 2; $n <= 12; $n++): ?>
                    <option value="<?= $n ?>" <?= (int) ($values['parcelas_adesao'] ?? 0) === $n ? 'selected' : '' ?>><?= $n ?>x</option>
                <?php endfor; ?>
            </select>
        </div>

        <h3 class="section-title">4. Quantidade de veículos da frota</h3>
        <label for="veiculos">Veículos com Ecodiffusore instalado</label>
        <input type="number" id="veiculos" name="veiculos" min="1" value="<?= $v('veiculos', '1') ?>" style="max-width:160px;">
        <p class="hint-text" style="margin-top:4px;">A economia, o resultado líquido e as projeções abaixo já são calculados para o total da frota informada aqui.</p>

        <button type="submit" class="btn btn-primary" style="margin-top:20px;">Calcular</button>
    </form>

    <script>
    (function () {
        document.querySelectorAll('[data-modo-radio]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.querySelectorAll('[data-modo-field]').forEach(function (field) {
                    field.style.display = field.getAttribute('data-modo-field') === radio.value ? '' : 'none';
                });
            });
        });
        var parcelarCheckbox = document.getElementById('parcelar_adesao');
        var parcelasField = document.querySelector('[data-parcelas-field]');
        if (parcelarCheckbox && parcelasField) {
            parcelarCheckbox.addEventListener('change', function () {
                parcelasField.style.display = parcelarCheckbox.checked ? '' : 'none';
            });
        }
    })();
    </script>

    <?php if ($result && $result['economia_mensal'] > 0): ?>
        <h3 class="section-title" style="margin-top:32px;">Sua economia estimada<?= !empty($values['client_name']) ? ' — ' . View::e($values['client_name']) : '' ?></h3>

        <div class="cards-grid" style="max-width:900px;">
            <div class="dash-card">
                <span>Economia em diesel / mês</span>
                <strong class="text-green"><?= $fmt($result['economia_mensal']) ?></strong>
            </div>
            <div class="dash-card">
                <span>Economia em diesel / ano</span>
                <strong class="text-green"><?= $fmt($result['economia_anual']) ?></strong>
            </div>
            <div class="dash-card">
                <span>Resultado líquido / mês</span>
                <strong class="<?= $result['resultado_mensal'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $fmt($result['resultado_mensal']) ?></strong>
                <small class="hint-inline">a partir do mês <?= (int) $result['parcelas_adesao'] + 1 ?></small>
            </div>
            <div class="dash-card">
                <span>Payback da adesão</span>
                <strong><?= $result['payback_meses'] !== null ? (round($result['payback_meses'], 1) < 1 ? 'menos de 1 mês' : number_format($result['payback_meses'], 1, ',', '.') . ' meses') : '—' ?></strong>
            </div>
        </div>

        <div class="table-scroll" style="max-width:900px; margin-top:20px;">
            <table class="data-table">
                <thead><tr><th></th><th>Consumo (km/l)</th><th>Gasto mensal</th></tr></thead>
                <tbody>
                    <tr><td>Sem Ecodiffusore</td><td><?= number_format($result['media_atual'], 2, ',', '.') ?> km/l</td><td><?= $fmt($result['gasto_mensal_sem_eco']) ?> <small class="hint-text">(por veículo)</small></td></tr>
                    <tr><td>Com Ecodiffusore</td><td><?= number_format($result['media_com_eco'], 2, ',', '.') ?> km/l</td><td><?= $fmt($result['gasto_mensal_com_eco']) ?> <small class="hint-text">(por veículo)</small></td></tr>
                </tbody>
            </table>
        </div>

        <div class="table-scroll" style="max-width:900px; margin-top:20px;">
            <table class="data-table">
                <thead><tr><th></th><th>Economia em diesel (frota)</th><th>Custo da locação (frota)</th></tr></thead>
                <tbody>
                    <tr><td>Litros economizados / mês</td><td><?= number_format($result['litros_economizados_mes'], 1, ',', '.') ?> L</td><td rowspan="5" style="vertical-align:top;">
                        Mensalidade total da frota: <strong><?= $fmt($result['mensalidade_total_frota']) ?></strong><br>
                        Mensalidade acumulada / ano: <strong><?= $fmt($result['mensalidade_anual_frota']) ?></strong><br>
                        Adesão total da frota: <strong><?= $fmt($result['adesao_total_frota']) ?></strong>
                        <?php if ($result['parcelas_adesao'] > 1): ?><br><small class="hint-text">parcelada em <?= (int) $result['parcelas_adesao'] ?>x de <?= $fmt($result['adesao_total_frota'] / $result['parcelas_adesao']) ?></small><?php endif; ?>
                    </td></tr>
                    <tr><td>Km extras com o diesel economizado</td><td><?= number_format($result['km_extras_mes'], 0, ',', '.') ?> km</td></tr>
                    <tr><td>Litros economizados / ano</td><td><?= number_format($result['litros_economizados_ano'], 1, ',', '.') ?> L</td></tr>
                    <tr><td>Km extras / ano</td><td><?= number_format($result['km_extras_ano'], 0, ',', '.') ?> km</td></tr>
                    <tr><td>Veículos na frota</td><td><?= (int) $result['veiculos'] ?></td></tr>
                </tbody>
            </table>
        </div>

        <h4 class="section-title" style="margin-top:20px;">Resultado financeiro líquido no ano 1</h4>
        <p style="font-size:1.4rem; font-weight:700;" class="<?= $result['resultado_ano1'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $fmt($result['resultado_ano1']) ?></p>

        <details class="inline-details" style="max-width:900px;">
            <summary>Comparativo mês a mês — 12 primeiros meses</summary>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Mês</th><th>Economia em diesel</th><th>Mensalidade</th><th>Parcela adesão</th><th>Total pago</th><th>Resultado do mês</th><th>Acumulado</th></tr></thead>
                    <tbody>
                        <?php foreach ($result['meses'] as $m): ?>
                            <tr>
                                <td>Mês <?= (int) $m['mes'] ?></td>
                                <td><?= $fmt($m['economia']) ?></td>
                                <td><?= $fmt($m['mensalidade']) ?></td>
                                <td><?= $m['parcela_adesao'] !== null ? $fmt($m['parcela_adesao']) : '—' ?></td>
                                <td><?= $fmt($m['total_pago']) ?></td>
                                <td class="<?= $m['resultado'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $fmt($m['resultado']) ?></td>
                                <td class="<?= $m['acumulado'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $fmt($m['acumulado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <details class="inline-details" style="max-width:900px; margin-top:12px;">
            <summary>Resumo em 5 anos</summary>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Ano</th><th>Economia em diesel</th><th>Custo locação</th><th>Custo adesão</th><th>Resultado do ano</th><th>Acumulado 5 anos</th></tr></thead>
                    <tbody>
                        <?php foreach ($result['anos'] as $a): ?>
                            <tr>
                                <td>Ano <?= (int) $a['ano'] ?></td>
                                <td><?= $fmt($a['economia']) ?></td>
                                <td><?= $fmt($a['custo_locacao']) ?></td>
                                <td><?= $a['custo_adesao'] > 0 ? $fmt($a['custo_adesao']) : '—' ?></td>
                                <td class="<?= $a['resultado'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $fmt($a['resultado']) ?></td>
                                <td class="<?= $a['acumulado'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $fmt($a['acumulado']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>

        <p class="hint-text" style="max-width:900px; margin-top:16px;">Resultado líquido = economia gerada em diesel menos o custo da locação (mensalidade e, no ano 1, a adesão — à vista ou parcelada no cartão). A tabela de 5 anos assume gasto, consumo, preço do diesel, % de economia e mensalidade constantes ao longo do período (sem reajuste ou inflação). Valores contratuais do Ecodiffusore garantem faixa de 5% a 30% de melhora.</p>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; max-width:900px;">
            <form method="post" action="/calculadora-locacao/pdf">
                <?= Csrf::field() ?>
                <?php foreach (['client_name', 'currency', 'modo', 'gasto_diesel', 'km_rodados', 'media_kml', 'preco_diesel', 'pct_economia', 'valor_adesao', 'mensalidade', 'veiculos', 'parcelar_adesao', 'parcelas_adesao'] as $field): ?>
                    <input type="hidden" name="<?= $field ?>" value="<?= $v($field) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-outline">📄 Baixar PDF</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
