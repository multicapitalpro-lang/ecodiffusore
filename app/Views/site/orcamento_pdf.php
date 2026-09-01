<?php
/** @var array $result */
$hasSeller = !empty($result['seller_whatsapp']);
$whatsappNumber = $hasSeller ? '55' . preg_replace('/\D/', '', $result['seller_whatsapp']) : '5545991021551';
$whatsappDisplay = $hasSeller ? $result['seller_whatsapp'] : '45991021551';
$payback = $result['payback'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Orçamento Ecodiffusore</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    .letterhead { border-bottom: 2px solid #1a7a4c; padding-bottom: 10px; margin-bottom: 18px; }
    .letterhead-brand { font-size: 15px; font-weight: bold; color: #1a7a4c; letter-spacing: .5px; text-transform: uppercase; }
    .letterhead-title { margin: 4px 0; font-size: 20px; }
    .letterhead-meta { color: #666; font-size: 10px; }
    h2 { font-size: 14px; margin: 20px 0 8px; color: #0e0e0e; }
    table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.data-table td { border: 1px solid #ddd; padding: 6px 9px; }
    table.data-table td:first-child { width: 35%; color: #666; }
    table.tiers-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.tiers-table th, table.tiers-table td { border: 1px solid #ddd; padding: 7px 9px; text-align: right; }
    table.tiers-table th:first-child, table.tiers-table td:first-child { text-align: left; }
    table.tiers-table th { background: #f0f4f2; font-weight: bold; }
    .highlight-box { background: #eef8f0; border: 1px solid #1a7a4c; border-radius: 6px; padding: 12px 16px; margin-bottom: 14px; font-size: 13px; }
    .highlight-box strong { color: #1a7a4c; }
    .price-box { background: #f7f7f7; border-radius: 6px; padding: 12px 16px; margin-bottom: 14px; }
    .price-box strong { font-size: 16px; color: #1a7a4c; }
    .footer-note { margin-top: 18px; font-size: 10px; color: #777; }
</style>
</head>
<body>
<div class="letterhead">
    <div class="letterhead-brand">Ecodiffusore Brasil</div>
    <div class="letterhead-title">Orçamento — Placa <?= htmlspecialchars($result['plate'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="letterhead-meta">Gerado em <?= date('d/m/Y') ?> às <?= date('H:i') ?> · ecodiffusorebrasil.com.br</div>
</div>

<h2>Dados do veículo</h2>
<table class="data-table">
    <tr><td>Nome</td><td><?= htmlspecialchars($result['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Placa</td><td><?= htmlspecialchars($result['plate'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Ano modelo</td><td><?= htmlspecialchars($result['year'], ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Marca / Modelo</td><td><?= htmlspecialchars($result['brand'] . ($result['model'] ? ' / ' . $result['model'] : ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Potência</td><td><?= htmlspecialchars($result['power'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Motor</td><td><?= $result['ecu_status'] === 'original' ? 'Original de fábrica' : 'Reprogramado (chip)' ?></td></tr>
</table>

<?php if (!empty($result['product_price'])): ?>
<h2>Investimento estimado</h2>
<div class="price-box">
    <?= $result['product_is_exact_match'] ? '' : 'A partir de ' ?>
    <strong>R$ <?= number_format((float) $result['product_price'], 2, ',', '.') ?></strong>
    <?= $result['product_name'] ? '— ' . htmlspecialchars($result['product_name'], ENT_QUOTES, 'UTF-8') : '' ?><br>
    À vista (Pix ou Boleto) ou parcelado em até 12x no cartão. Condições finais confirmadas com o vendedor.
</div>
<?php endif; ?>

<?php if ($payback && $payback['tiers']['avg']['monthly'] > 0): ?>
<h2>Sua economia estimada</h2>
<table class="tiers-table">
    <thead><tr><th>Cenário</th><th>Economia mensal</th><th>Economia anual</th><th>Economia em 5 anos</th></tr></thead>
    <tbody>
        <tr><td>5% — Mínimo garantido</td><td>R$ <?= number_format($payback['tiers']['min']['monthly'], 2, ',', '.') ?></td><td>R$ <?= number_format($payback['tiers']['min']['yearly'], 2, ',', '.') ?></td><td>R$ <?= number_format($payback['tiers']['min']['five_year'], 2, ',', '.') ?></td></tr>
        <tr><td>10% — Média real</td><td>R$ <?= number_format($payback['tiers']['avg']['monthly'], 2, ',', '.') ?></td><td>R$ <?= number_format($payback['tiers']['avg']['yearly'], 2, ',', '.') ?></td><td>R$ <?= number_format($payback['tiers']['avg']['five_year'], 2, ',', '.') ?></td></tr>
        <tr><td>20% — Potencial máximo</td><td>R$ <?= number_format($payback['tiers']['max']['monthly'], 2, ',', '.') ?></td><td>R$ <?= number_format($payback['tiers']['max']['yearly'], 2, ',', '.') ?></td><td>R$ <?= number_format($payback['tiers']['max']['five_year'], 2, ',', '.') ?></td></tr>
    </tbody>
</table>

<?php if ($payback['payback_months']): ?>
<div class="highlight-box">
    Com a economia média, seu investimento se paga em aproximadamente
    <strong><?= $payback['payback_months'] < 1 ? 'menos de 1 mês' : ceil($payback['payback_months']) . ' meses' ?></strong>.
</div>
<?php endif; ?>

<h2>Retorno do investimento, ano a ano</h2>
<table class="tiers-table">
    <thead><tr><th>Ano</th><th>Economia acumulada</th><th>Lucro líquido acumulado</th></tr></thead>
    <tbody>
        <?php foreach ($payback['yearly_breakdown'] as $row): ?>
            <tr>
                <td>Ano <?= (int) $row['year'] ?></td>
                <td>R$ <?= number_format($row['cumulative_savings'], 2, ',', '.') ?></td>
                <td><?= $row['net_gain'] >= 0 ? '+ R$ ' . number_format($row['net_gain'], 2, ',', '.') : 'Faltam R$ ' . number_format(abs($row['net_gain']), 2, ',', '.') . ' pra recuperar o investimento' ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Fale com a gente</h2>
<p><?= $hasSeller ? 'Licenciado responsável pela sua região: ' . htmlspecialchars($result['seller_name'], ENT_QUOTES, 'UTF-8') : 'Atendimento Ecodiffusore Brasil' ?> — WhatsApp <?= htmlspecialchars($whatsappDisplay, ENT_QUOTES, 'UTF-8') ?></p>

<p class="footer-note">A economia varia de acordo com estilo de direção, tipo de carga e condições da estrada. Orçamento sujeito a confirmação com o vendedor.</p>
</body>
</html>
