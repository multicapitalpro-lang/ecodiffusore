<?php
use App\Core\Money;
/** @var string|null $clientName */
/** @var array $result */
/** @var string $sellerName */
/** @var string $currency */
/** @var float $rate */
$fmt = fn (float $brl) => Money::format($brl, $currency ?? 'BRL', $rate ?? 0.0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Calculadora de Locação Ecodiffusore</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    .letterhead { border-bottom: 2px solid #1a7a4c; padding-bottom: 10px; margin-bottom: 18px; }
    .letterhead-brand { font-size: 15px; font-weight: bold; color: #1a7a4c; letter-spacing: .5px; text-transform: uppercase; }
    .letterhead-title { margin: 4px 0; font-size: 20px; }
    .letterhead-meta { color: #666; font-size: 10px; }
    h2 { font-size: 14px; margin: 20px 0 8px; color: #0e0e0e; }
    table.tiers-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.tiers-table th, table.tiers-table td { border: 1px solid #ddd; padding: 6px 8px; text-align: right; }
    table.tiers-table th:first-child, table.tiers-table td:first-child { text-align: left; }
    table.tiers-table th { background: #f0f4f2; font-weight: bold; }
    .highlight-box { background: #eef8f0; border: 1px solid #1a7a4c; border-radius: 6px; padding: 12px 16px; margin-bottom: 14px; font-size: 13px; }
    .highlight-box strong { color: #1a7a4c; }
    .footer-note { margin-top: 18px; font-size: 10px; color: #777; }
    .neg { color: #c53030; }
</style>
</head>
<body>
<div class="letterhead">
    <div class="letterhead-brand">Ecodiffusore Brasil</div>
    <div class="letterhead-title">Calculadora de Locação<?= $clientName ? ' — ' . htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') : '' ?></div>
    <div class="letterhead-meta">Gerado em <?= date('d/m/Y') ?> às <?= date('H:i') ?> por <?= htmlspecialchars($sellerName, ENT_QUOTES, 'UTF-8') ?> · ecodiffusorebrasil.com.br</div>
</div>

<h2>Consumo (por veículo)</h2>
<table class="tiers-table">
    <thead><tr><th>Cenário</th><th>km/l</th><th>Gasto mensal</th></tr></thead>
    <tbody>
        <tr><td>Sem Ecodiffusore</td><td><?= number_format($result['media_atual'], 2, ',', '.') ?></td><td><?= $fmt($result['gasto_mensal_sem_eco']) ?></td></tr>
        <tr><td>Com Ecodiffusore</td><td><?= number_format($result['media_com_eco'], 2, ',', '.') ?></td><td><?= $fmt($result['gasto_mensal_com_eco']) ?></td></tr>
    </tbody>
</table>

<h2>Economia e custo da locação (frota de <?= (int) $result['veiculos'] ?> veículo<?= $result['veiculos'] > 1 ? 's' : '' ?>)</h2>
<table class="tiers-table">
    <thead><tr><th></th><th>Mensal</th><th>Anual</th></tr></thead>
    <tbody>
        <tr><td>Economia em diesel</td><td><?= $fmt($result['economia_mensal']) ?></td><td><?= $fmt($result['economia_anual']) ?></td></tr>
        <tr><td>Custo da locação</td><td><?= $fmt($result['mensalidade_total_frota']) ?></td><td><?= $fmt($result['mensalidade_anual_frota']) ?></td></tr>
    </tbody>
</table>
<p>Adesão total da frota: <strong><?= $fmt($result['adesao_total_frota']) ?></strong><?= $result['parcelas_adesao'] > 1 ? ' (parcelada em ' . (int) $result['parcelas_adesao'] . 'x de ' . $fmt($result['adesao_total_frota'] / $result['parcelas_adesao']) . ')' : ' (à vista)' ?></p>

<div class="highlight-box">
    Resultado financeiro líquido no ano 1: <strong class="<?= $result['resultado_ano1'] < 0 ? 'neg' : '' ?>"><?= $fmt($result['resultado_ano1']) ?></strong><br>
    <?php if ($result['payback_meses'] !== null): ?>
        Retorno do investimento (payback da adesão) em aproximadamente
        <strong><?= round($result['payback_meses'], 1) < 1 ? 'menos de 1 mês' : number_format($result['payback_meses'], 1, ',', '.') . ' meses' ?></strong>.
    <?php endif; ?>
</div>

<h2>Resumo em 5 anos</h2>
<table class="tiers-table">
    <thead><tr><th>Ano</th><th>Economia em diesel</th><th>Custo locação</th><th>Custo adesão</th><th>Resultado do ano</th><th>Acumulado</th></tr></thead>
    <tbody>
        <?php foreach ($result['anos'] as $a): ?>
            <tr>
                <td>Ano <?= (int) $a['ano'] ?></td>
                <td><?= $fmt($a['economia']) ?></td>
                <td><?= $fmt($a['custo_locacao']) ?></td>
                <td><?= $a['custo_adesao'] > 0 ? $fmt($a['custo_adesao']) : '—' ?></td>
                <td class="<?= $a['resultado'] < 0 ? 'neg' : '' ?>"><?= $fmt($a['resultado']) ?></td>
                <td class="<?= $a['acumulado'] < 0 ? 'neg' : '' ?>"><?= $fmt($a['acumulado']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p class="footer-note">Resultado líquido = economia gerada em diesel menos o custo da locação (mensalidade e, no ano 1, a adesão — à vista ou parcelada no cartão). A tabela de 5 anos assume gasto, consumo, preço do diesel, % de economia e mensalidade constantes ao longo do período (sem reajuste ou inflação). Valores contratuais do Ecodiffusore garantem faixa de 5% a 30% de melhora. Simulação sujeita a confirmação com o vendedor.</p>
</body>
</html>
