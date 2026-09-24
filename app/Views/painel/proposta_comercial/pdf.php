<?php
/** @var string $clientName */
/** @var string $city */
/** @var string $responsible */
/** @var array $items ['produto','quantity','unit_price','subtotal'] */
/** @var float $total */
/** @var string $paymentTerms */
/** @var string $validity */
/** @var string $notes */
$imgB64 = function (string $relative): string {
    $path = BASE_PATH . '/public_html/assets/img/' . $relative;
    if (!is_file($path)) {
        return '';
    }
    return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($path));
};
$fmt = fn (float $v) => 'R$ ' . number_format($v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Proposta Comercial Ecodiffusore</title>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #222; font-size: 10px; margin: 0; }
    .hero { width: 100%; display: block; margin-bottom: 14px; }
    .title { text-align: center; font-size: 22px; font-weight: bold; color: #003254; margin: 0 0 2px; }
    .title strong { color: #1a7a4c; }
    .subtitle { text-align: center; font-size: 9px; font-weight: bold; color: #1a7a4c; letter-spacing: 1px; margin: 0 0 14px; }
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .info-table td { width: 33.33%; border: 1px solid #ddd; padding: 6px 8px; vertical-align: top; }
    .info-label { color: #1a7a4c; font-weight: bold; font-size: 9px; display: block; margin-bottom: 3px; }
    .info-value { font-size: 10px; }
    .intro { font-size: 10px; line-height: 1.5; margin-bottom: 14px; text-align: justify; }
    .benefits { width: 100%; display: block; margin-bottom: 14px; }
    .bar { background: #003254; color: #fff; font-weight: bold; font-size: 12px; padding: 7px 10px; margin-bottom: 0; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    table.items th { background: #003254; color: #fff; font-size: 9px; padding: 7px 8px; text-align: right; }
    table.items th:first-child { text-align: left; }
    table.items td { padding: 6px 8px; font-size: 9.5px; border-bottom: 1px solid #eee; text-align: right; }
    table.items td:first-child { text-align: left; }
    table.items tr:nth-child(even) td { background: #f6f6f6; }
    .total-row td { background: #003254; color: #fff; font-weight: bold; font-size: 11px; padding: 8px; }
    .total-row .total-value { background: #2c8f09; text-align: right; }
    .cond-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .cond-table td { border: 1px solid #ddd; padding: 6px 8px; font-size: 10px; }
    .cond-table td.label { color: #1a7a4c; font-weight: bold; width: 32%; }
    .notes-box { border: 1px solid #ddd; padding: 8px; min-height: 24px; font-size: 10px; margin-bottom: 14px; }
    .agree { text-align: center; font-style: italic; font-size: 9.5px; margin-bottom: 26px; }
    table.sign { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    table.sign td { width: 50%; text-align: center; font-size: 9.5px; padding-top: 18px; border-top: 1px solid #333; }
    table.sign .sign-name { font-weight: bold; color: #1a7a4c; margin-bottom: 14px; display: block; }
    .footer { background: #003254; color: #fff; font-size: 8.5px; padding: 8px 10px; margin-top: 8px; }
</style>
</head>
<body>

<?php $hero = $imgB64('proposta-comercial-hero.jpg'); ?>
<?php if ($hero): ?><img class="hero" src="<?= $hero ?>"><?php endif; ?>

<p class="title">PROPOSTA <strong>COMERCIAL</strong></p>
<p class="subtitle">ECODIFFUSORE BRASIL &nbsp;&bull;&nbsp; TECNOLOGIA PARA EFICIÊNCIA DE FROTAS</p>

<table class="info-table">
    <tr>
        <td><span class="info-label">Nome do Cliente</span><span class="info-value"><?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><span class="info-label">Cidade</span><span class="info-value"><?= htmlspecialchars($city ?: '—', ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><span class="info-label">Responsável</span><span class="info-value"><?= htmlspecialchars($responsible ?: '—', ENT_QUOTES, 'UTF-8') ?></span></td>
    </tr>
</table>

<p class="intro">A <strong>Ecodiffusore Brasil</strong> apresenta uma solução exclusiva voltada à eficiência operacional de frotas, com tecnologia desenvolvida para potencializar a combustão, melhorar o rendimento do motor e contribuir para a redução do consumo de combustível e da emissão de poluentes, dimensionada de forma personalizada para atender as mais diversas necessidades.</p>

<?php $beneficios = $imgB64('proposta-comercial-beneficios.jpg'); ?>
<?php if ($beneficios): ?><img class="benefits" src="<?= $beneficios ?>"><?php endif; ?>

<div class="bar">PRODUTOS E INVESTIMENTO</div>
<table class="items">
    <thead>
        <tr><th>Produto</th><th>Quantidade</th><th>Valor Unitário</th><th>Valor Total</th></tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['produto'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $item['quantity'] ?></td>
                <td><?= $fmt($item['unit_price']) ?></td>
                <td><?= $fmt($item['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="3">VALOR TOTAL DA PROPOSTA</td>
            <td class="total-value"><?= $fmt($total) ?></td>
        </tr>
    </tbody>
</table>
<div style="margin-bottom:14px;"></div>

<div class="bar">CONDIÇÕES COMERCIAIS</div>
<table class="cond-table">
    <tr><td class="label">Condições de Pagamento</td><td><?= nl2br(htmlspecialchars($paymentTerms ?: '—', ENT_QUOTES, 'UTF-8')) ?></td></tr>
    <tr><td class="label">Validade da Proposta</td><td><?= htmlspecialchars($validity ?: '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>

<div class="bar">OBSERVAÇÕES</div>
<div class="notes-box"><?= nl2br(htmlspecialchars($notes ?: '—', ENT_QUOTES, 'UTF-8')) ?></div>

<p class="agree">De acordo com as condições comerciais apresentadas nesta proposta.</p>

<table class="sign">
    <tr>
        <td><span class="sign-name">ECODIFFUSORE BRASIL</span>Nome / Cargo: ______________________________</td>
        <td><span class="sign-name">CLIENTE</span>Nome / Cargo: ______________________________</td>
    </tr>
</table>

<div class="footer">@ecodiffusorebrasil &nbsp;&nbsp;|&nbsp;&nbsp; www.ecodiffusorebrasil.com.br &nbsp;&nbsp;|&nbsp;&nbsp; O futuro do transporte começa agora. Faça parte dessa transformação.</div>

</body>
</html>
