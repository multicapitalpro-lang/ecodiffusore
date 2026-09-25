<?php
use App\Core\Csrf;
use App\Core\Money;
use App\Core\View;
/** @var array|null $result */
/** @var array $values */
/** @var array $errors */
/** @var ?string $secondaryCurrency Fase 122: so' preenchido pro Licenciado/rede com operacao fora do Brasil */
/** @var float $rate */
$v = fn (string $k, string $default = '') => View::e((string) ($values[$k] ?? $default));
$currency = $values['currency'] ?? 'BRL';
$fmt = fn (float $brl) => Money::format($brl, $currency, $rate ?? 0.0);
$modo = $values['modo'] ?? 'km_rodados';
$pct = $values['pct_economia'] ?? '10';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= View::asset('/assets/css/calculadora-locacao.css') ?>">

<div class="dc-page dc-embedded" data-rate="<?= (float) ($rate ?? 0) ?>">

    <div class="dc-hero">
        <div class="dc-hero-brand">
            <img src="<?= View::asset('/assets/img/logo-full-white.png') ?>" alt="Ecodiffusore Brasil">
        </div>
        <h1>Calculadora de Economia de Diesel</h1>
        <p>Informe o consumo atual da frota para calcular a média de km/l, projetar a economia com o Ecodiffusore e o retorno financeiro da locação.</p>
    </div>
    <div class="dc-strip">
        <strong>Sua economia estimada</strong>
        <span>preencha os dados abaixo para calcular →</span>
    </div>

    <div class="dc-body">
        <?php if (!empty($errors['geral'])): ?>
            <p class="field-error"><?= View::e($errors['geral']) ?></p>
        <?php endif; ?>

        <form method="post" action="/painel/calculadora-locacao">
            <?= Csrf::field() ?>

            <div class="dc-card">
                <div class="dc-card-title">
                    <div class="dc-badge">·</div>
                    <h3>Nome do cliente<?= $secondaryCurrency ? ' & moeda' : '' ?></h3>
                </div>
                <div class="dc-field">
                    <label for="client_name">Nome do cliente (opcional)</label>
                    <input type="text" id="client_name" name="client_name" value="<?= $v('client_name') ?>">
                </div>
                <?php if ($secondaryCurrency): ?>
                    <div class="dc-field">
                        <label for="currency">Moeda</label>
                        <select id="currency" name="currency">
                            <option value="BRL" <?= $currency === 'BRL' ? 'selected' : '' ?>>Real (R$)</option>
                            <option value="<?= View::e($secondaryCurrency) ?>" <?= $currency === $secondaryCurrency ? 'selected' : '' ?>><?= View::e(Money::label($secondaryCurrency)) ?></option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dc-card">
                <div class="dc-card-title">
                    <div class="dc-badge">1</div>
                    <h3>Consumo atual da frota (por veículo/mês)</h3>
                </div>

                <div class="dc-field">
                    <label for="gasto_diesel">Gasto com diesel</label>
                    <div class="dc-field-wrap">
                        <input type="text" id="gasto_diesel" name="gasto_diesel" value="<?= $v('gasto_diesel') ?>" placeholder="Ex: 45.000,00">
                        <span class="dc-unit" id="cl-currency-symbol"><?= $currency === 'BRL' ? 'R$' : Money::symbol($currency) ?>/mês</span>
                    </div>
                    <p class="field-error"><?= View::e($errors['gasto_diesel'] ?? '') ?></p>
                </div>

                <div class="dc-radio-row">
                    <label><input type="radio" name="modo" value="km_rodados" <?= $modo === 'km_rodados' ? 'checked' : '' ?> data-modo-radio> Km rodados</label>
                    <label><input type="radio" name="modo" value="media" <?= $modo === 'media' ? 'checked' : '' ?> data-modo-radio> Média km/l</label>
                </div>
                <div data-modo-field="km_rodados" class="dc-field" style="<?= $modo === 'media' ? 'display:none;' : '' ?>">
                    <div class="dc-field-wrap">
                        <input type="text" id="km_rodados" name="km_rodados" value="<?= $v('km_rodados') ?>" placeholder="Ex: 12000">
                        <span class="dc-unit">km/mês</span>
                    </div>
                    <p class="field-error"><?= View::e($errors['km_rodados'] ?? '') ?></p>
                </div>
                <div data-modo-field="media" class="dc-field" style="<?= $modo === 'km_rodados' ? 'display:none;' : '' ?>">
                    <div class="dc-field-wrap">
                        <input type="text" id="media_kml" name="media_kml" value="<?= $v('media_kml') ?>" placeholder="Ex: 2,8">
                        <span class="dc-unit">km/l</span>
                    </div>
                    <p class="field-error"><?= View::e($errors['media_kml'] ?? '') ?></p>
                </div>

                <div class="dc-field">
                    <label for="preco_diesel">Preço do diesel</label>
                    <div class="dc-field-wrap">
                        <input type="text" id="preco_diesel" name="preco_diesel" value="<?= $v('preco_diesel') ?>" placeholder="Ex: 6,200">
                        <span class="dc-unit" id="cl-currency-symbol-2"><?= $currency === 'BRL' ? 'R$' : Money::symbol($currency) ?>/L</span>
                    </div>
                    <p class="field-error"><?= View::e($errors['preco_diesel'] ?? '') ?></p>
                </div>
            </div>

            <div class="dc-result-card">
                <span>Média atual</span>
                <strong id="dc-media-atual">— km/l</strong>
            </div>
            <div class="dc-result-below">
                <div class="dc-result-row">
                    <span>Gasto mensal</span>
                    <strong id="dc-gasto-mensal">R$ —</strong>
                </div>
                <div class="dc-result-row">
                    <span>Gasto anual</span>
                    <strong id="dc-gasto-anual">R$ —</strong>
                </div>
            </div>

            <div class="dc-card">
                <div class="dc-card-title">
                    <div class="dc-badge">2</div>
                    <h3>Economia esperada com Ecodiffusore</h3>
                </div>
                <label for="pct_economia" style="font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--dc-gray);">Percentual de economia de diesel (contratual: 5% a 30%)</label>
                <div class="dc-slider-row">
                    <input type="range" id="pct_economia" name="pct_economia" min="5" max="30" step="1" value="<?= View::e((string) $pct) ?>" oninput="document.getElementById('pct-economia-label').textContent = this.value + '%'">
                    <strong id="pct-economia-label"><?= View::e((string) $pct) ?>%</strong>
                </div>
                <p class="field-error"><?= View::e($errors['pct_economia'] ?? '') ?></p>

                <div class="dc-compare-row">
                    <label>Antes</label>
                    <div class="dc-readonly" id="dc-antes-kml">—</div>
                    <span class="dc-unit-inline">km/l</span>
                </div>
                <div class="dc-compare-row">
                    <label>Com Ecodiffusore</label>
                    <div class="dc-readonly" id="dc-depois-kml">—</div>
                    <span class="dc-unit-inline">km/l</span>
                </div>

                <div class="dc-gasto-compare">
                    <div class="dc-gasto-box dc-before">
                        <span>Gasto mensal sem Ecodiffusore</span>
                        <strong id="dc-gasto-sem">R$ —</strong>
                    </div>
                    <div class="dc-gasto-arrow">→</div>
                    <div class="dc-gasto-box dc-after">
                        <span>Gasto mensal com Ecodiffusore</span>
                        <strong id="dc-gasto-com">R$ —</strong>
                    </div>
                </div>
            </div>

            <div class="dc-card">
                <div class="dc-card-title">
                    <div class="dc-badge">3</div>
                    <h3>Condições comerciais da locação (opcional)</h3>
                </div>
                <p style="font-size:.8rem; color:var(--dc-gray); margin:-6px 0 14px;">Só preencha se a negociação envolver locação. Sem adesão/mensalidade, o resultado mostra apenas a economia de diesel.</p>
                <div class="dc-field">
                    <label for="valor_adesao">Valor de adesão (único, por veículo)</label>
                    <div class="dc-field-wrap">
                        <input type="text" id="valor_adesao" name="valor_adesao" value="<?= $v('valor_adesao') ?>" placeholder="Ex: 4.490,00">
                        <span class="dc-unit">único</span>
                    </div>
                    <p class="field-error"><?= View::e($errors['valor_adesao'] ?? '') ?></p>
                </div>
                <div class="dc-field">
                    <label for="mensalidade">Mensalidade da locação (por veículo)</label>
                    <div class="dc-field-wrap">
                        <input type="text" id="mensalidade" name="mensalidade" value="<?= $v('mensalidade') ?>" placeholder="Ex: 890,00">
                        <span class="dc-unit"><?= $currency === 'BRL' ? 'R$' : Money::symbol($currency) ?>/mês</span>
                    </div>
                    <p class="field-error"><?= View::e($errors['mensalidade'] ?? '') ?></p>
                </div>
                <label class="dc-check-row"><input type="checkbox" id="parcelar_adesao" name="parcelar_adesao" value="1" <?= !empty($values['parcelar_adesao']) ? 'checked' : '' ?> data-parcelar-toggle> Parcelar a adesão no cartão de crédito (opcional)</label>
                <div data-parcelas-field class="dc-field" style="<?= empty($values['parcelar_adesao']) ? 'display:none;' : '' ?> margin-top:10px; max-width:220px;">
                    <label for="parcelas_adesao">Em quantas vezes</label>
                    <select id="parcelas_adesao" name="parcelas_adesao">
                        <?php for ($n = 2; $n <= 12; $n++): ?>
                            <option value="<?= $n ?>" <?= (int) ($values['parcelas_adesao'] ?? 0) === $n ? 'selected' : '' ?>><?= $n ?>x</option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="dc-card">
                <div class="dc-card-title">
                    <div class="dc-badge">4</div>
                    <h3>Quantidade de veículos da frota</h3>
                </div>
                <div class="dc-field">
                    <label for="veiculos">Veículos com Ecodiffusore instalado</label>
                    <div class="dc-field-wrap">
                        <input type="number" id="veiculos" name="veiculos" min="1" value="<?= $v('veiculos', '1') ?>">
                        <span class="dc-unit">veículos</span>
                    </div>
                </div>
                <p style="font-size:.8rem; color:var(--dc-gray); margin:0;">A economia, o resultado líquido e as projeções abaixo já são calculados para o total da frota informada aqui.</p>
            </div>

            <?php if (!$result): ?>
                <div class="dc-hint-box">Preencha os dados acima e clique em Calcular para ver a projeção completa da economia da frota.</div>
            <?php endif; ?>

            <button type="submit" class="dc-btn">Calcular</button>
        </form>
    </div>

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
        var currencySelect = document.getElementById('currency');
        if (currencySelect) {
            currencySelect.addEventListener('change', function () {
                var symbol = currencySelect.value === 'PYG' ? '₲' : 'R$';
                document.getElementById('cl-currency-symbol').textContent = symbol + '/mês';
                document.getElementById('cl-currency-symbol-2').textContent = symbol + '/L';
            });
        }
    })();
    </script>
    <script src="<?= View::asset('/assets/js/calculadora-locacao.js') ?>"></script>

    <div class="dc-body">
    <?php if ($result && $result['economia_mensal'] > 0): ?>
        <h3 class="section-title" style="margin-top:8px;">Projeção da frota<?= !empty($values['client_name']) ? ' — ' . View::e($values['client_name']) : '' ?></h3>

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

        <div class="table-scroll dc-table-wrap" style="max-width:900px; margin-top:20px;">
            <table class="data-table">
                <thead><tr><th></th><th>Consumo (km/l)</th><th>Gasto mensal</th></tr></thead>
                <tbody>
                    <tr><td>Sem Ecodiffusore</td><td><?= number_format($result['media_atual'], 2, ',', '.') ?> km/l</td><td><?= $fmt($result['gasto_mensal_sem_eco']) ?> <small class="hint-text">(por veículo)</small></td></tr>
                    <tr><td>Com Ecodiffusore</td><td><?= number_format($result['media_com_eco'], 2, ',', '.') ?> km/l</td><td><?= $fmt($result['gasto_mensal_com_eco']) ?> <small class="hint-text">(por veículo)</small></td></tr>
                </tbody>
            </table>
        </div>

        <div class="table-scroll dc-table-wrap" style="max-width:900px; margin-top:20px;">
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

        <div class="dc-disclaimer" style="max-width:900px; margin-top:16px;">Resultado líquido = economia gerada em diesel menos o custo da locação (mensalidade e, no ano 1, a adesão — à vista ou parcelada no cartão). A tabela de 5 anos assume gasto, consumo, preço do diesel, % de economia e mensalidade constantes ao longo do período (sem reajuste ou inflação). Valores contratuais do Ecodiffusore garantem faixa de 5% a 30% de melhora.</div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; max-width:900px;">
            <form method="post" action="/painel/calculadora-locacao/pdf">
                <?= Csrf::field() ?>
                <?php foreach (['client_name', 'currency', 'modo', 'gasto_diesel', 'km_rodados', 'media_kml', 'preco_diesel', 'pct_economia', 'valor_adesao', 'mensalidade', 'veiculos', 'parcelar_adesao', 'parcelas_adesao'] as $field): ?>
                    <input type="hidden" name="<?= $field ?>" value="<?= $v($field) ?>">
                <?php endforeach; ?>
                <button type="submit" class="dc-btn dc-btn-outline">📄 Baixar PDF</button>
            </form>
        </div>
    <?php endif; ?>
    </div>

    <p class="dc-footer">Calculadora de Economia de Diesel — Ecodiffusore Brasil</p>
</div>
