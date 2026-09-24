<?php
use App\Core\Money;
use App\Core\View;
$fmtBRL = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
/** @var ?string $secondaryCurrency Fase 111 -- so' preenchido pro Licenciado/rede com operacao fora do Brasil */
/** @var float $rate */
?>
<div class="page-header">
    <h1>💰 Simulador de Comissão</h1>
</div>
<p class="section-sub">Veja quanto você ganharia numa venda hipotética, e compare as faixas de preço — quanto mais alto o preço negociado, maior a faixa de comissão.</p>

<form method="get" action="/painel/simulador-comissao" class="panel-form panel-form-wide" style="max-width:420px;">
    <div class="form-grid-2">
        <div>
            <label for="sim-price">Preço unitário (R$)</label>
            <input type="text" id="sim-price" name="unit_price" value="<?= $unitPrice > 0 ? number_format($unitPrice, 2, ',', '.') : '' ?>" placeholder="Ex: 4500,00">
        </div>
        <div>
            <label for="sim-qty">Quantidade</label>
            <input type="number" id="sim-qty" name="quantity" min="1" value="<?= (int) $quantity ?>">
        </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:12px;">Simular</button>
</form>

<?php if ($simulation): ?>
    <div class="dash-card <?= $simulation['tier'] ? '' : 'dash-card-danger' ?>" style="text-align:left;max-width:480px;margin-top:20px;">
        <?php if (!$simulation['tier']): ?>
            <span>Preço abaixo do piso mínimo negociável</span>
            <strong style="color:#c53030;">Sem faixa aplicável</strong>
        <?php else: ?>
            <span>Total da venda: <?= $fmtBRL($simulation['total']) ?> (<?= (int) $simulation['quantity'] ?> un. × <?= $fmtBRL($simulation['unit_price']) ?>)</span>
            <strong style="font-size:1.6rem;"><?= $fmtBRL($simulation['amount']) ?></strong>
            <?php if ($secondaryCurrency): ?>
                <strong style="font-size:1.1rem;color:var(--gray-text);">≈ <?= Money::format((float) $simulation['amount'], $secondaryCurrency, $rate) ?></strong>
            <?php endif; ?>
            <span class="hint-inline">sua comissão estimada nessa faixa (R$ <?= number_format((float) $simulation['tier']['min_price'], 2, ',', '.') ?><?= $simulation['tier']['max_price'] ? ' a R$ ' . number_format((float) $simulation['tier']['max_price'], 2, ',', '.') : ' acima' ?>)</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h3 class="section-title" style="margin-top:28px;">Comparativo por faixa</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Faixa de preço</th><th>Sua comissão</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>R$ <?= number_format((float) $r['tier']['min_price'], 2, ',', '.') ?><?= $r['tier']['max_price'] !== null ? ' a R$ ' . number_format((float) $r['tier']['max_price'], 2, ',', '.') : ' acima' ?></td>
                    <td><?= $r['pct'] !== null ? number_format($r['pct'], 2, ',', '.') . '%' : $fmtBRL($r['value']) . ' fixo' ?> <span class="hint-text">(<?= View::e($r['note']) ?>)</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="hint-text" style="margin-top:8px;">Valores baseados na sua configuração atual de comissão. Se você é Licenciado, o valor mostrado é o bruto do pool — o que sobra pra você depois de repassar Gestor/Vendedor pode ser menor.</p>
