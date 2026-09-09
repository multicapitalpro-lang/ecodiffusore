<?php
/** @var array $warranty */
/** @var array $items */
/** @var array $company */
/** @var array $vehicle */
$produtos = $items ? implode(', ', array_map(fn ($i) => $i['product_name'] . ' (x' . (int) $i['quantity'] . ')', $items)) : '—';
$e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8') ?: '—';

$vehicleLine = trim(implode(' ', array_filter([
    $vehicle['brand'] ?? null,
    $vehicle['model'] ?? null,
    $vehicle['year'] ?? null,
])));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Termo de Garantia</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #222; }
    .letterhead { border-bottom: 3px solid #1a7a4c; padding-bottom: 12px; margin-bottom: 20px; }
    .letterhead-brand { font-size: 16px; font-weight: bold; color: #1a7a4c; letter-spacing: .5px; text-transform: uppercase; }
    .letterhead-title { margin: 4px 0; font-size: 21px; }
    .letterhead-meta { color: #666; font-size: 10px; }
    h2 { font-size: 13px; margin: 18px 0 8px; color: #0e0e0e; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
    table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data-table td { border: 1px solid #ddd; padding: 6px 9px; vertical-align: top; }
    table.data-table td.label { width: 30%; color: #666; background: #fafafa; }
    .terms-text { font-size: 10px; line-height: 1.6; text-align: justify; margin: 0 0 10px; }
    .terms-text strong { color: #0e0e0e; }
    .signature-box { margin-top: 44px; text-align: center; }
    .signature-line { border-top: 1px solid #333; width: 280px; margin: 0 auto 4px; padding-top: 4px; }
    .footer-note { margin-top: 20px; font-size: 9px; color: #777; }
</style>
</head>
<body>
<div class="letterhead">
    <div class="letterhead-brand"><?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?></div>
    <div class="letterhead-title">Termo de Garantia</div>
    <div class="letterhead-meta">Pedido #<?= (int) $warranty['order_id'] ?> · Emitido em <?= date('d/m/Y') ?></div>
</div>

<h2>Dados da empresa</h2>
<table class="data-table">
    <tr><td class="label">Razão social</td><td><?= $e($company['razao_social'] ?? null) ?></td></tr>
    <tr><td class="label">CNPJ</td><td><?= $e($company['cnpj'] ?? null) ?></td></tr>
    <tr><td class="label">Endereço</td><td><?= $e($company['endereco'] ?? null) ?></td></tr>
</table>

<h2>Dados do comprador</h2>
<table class="data-table">
    <tr><td class="label">Nome / Razão social</td><td><?= $e($warranty['client_name'] ?? null) ?></td></tr>
    <tr><td class="label">CPF/CNPJ</td><td><?= $e($warranty['client_document'] ?? null) ?></td></tr>
    <tr><td class="label">Endereço</td><td><?= $e(trim(($warranty['client_address'] ?? '') . (!empty($warranty['client_city']) ? ', ' . $warranty['client_city'] : '') . (!empty($warranty['client_state']) ? '/' . $warranty['client_state'] : ''))) ?></td></tr>
</table>

<h2>Dados do motorista</h2>
<table class="data-table">
    <tr><td class="label">Nome completo</td><td><?= $e($warranty['driver_name'] ?? null) ?></td></tr>
    <tr><td class="label">CPF</td><td><?= $e($warranty['driver_document'] ?? null) ?></td></tr>
</table>

<h2>Dados do pedido e do veículo</h2>
<table class="data-table">
    <tr><td class="label">Nº do pedido</td><td>#<?= (int) $warranty['order_id'] ?></td></tr>
    <tr><td class="label">Nº da nota fiscal</td><td><?= $e($warranty['nfe_number'] ?? null) ?></td></tr>
    <tr><td class="label">Data do pedido</td><td><?= $e(date('d/m/Y', strtotime($warranty['order_date']))) ?></td></tr>
    <tr><td class="label">Produto</td><td><?= $e($produtos) ?></td></tr>
    <tr><td class="label">Veículo</td><td><?= $e($vehicleLine ?: null) ?></td></tr>
    <tr><td class="label">Placa</td><td><?= $e($vehicle['plate'] ?? null) ?></td></tr>
    <tr><td class="label">Garantia aprovada em</td><td><?= $warranty['resolved_at'] ? $e(date('d/m/Y', strtotime($warranty['resolved_at']))) : '—' ?></td></tr>
</table>

<h2>Termo</h2>
<p class="terms-text">
    A <?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?> certifica que a solicitação de
    garantia referente ao pedido acima foi analisada e <strong>aprovada</strong>, conforme os
    documentos e informações apresentados pelo comprador. Este termo comprova a cobertura de
    Garantia Estendida do produto adquirido, nos termos e condições estabelecidos abaixo.
</p>
<p class="terms-text">
    <strong>Prazo de garantia:</strong> 90 (noventa) dias para casos de arrependimento de compra,
    caso comprovadamente não haja economia do produto no veículo.
</p>
<p class="terms-text">
    Para efeitos de eventual pedido de revisão da economia, o motorista ou transportador deverá
    obrigatoriamente ter anexado e comprovado na hora da compra o relatório de consumo e média ou
    telemetria dos últimos 180 (cento e oitenta) dias antes da data da compra, e também comprovar
    que, a partir da instalação do equipamento, o veículo manteve os mesmos trajetos, peso de carga
    e motorista. O cliente somente poderá ser reembolsado caso, na aferição, seja comprovado que a
    economia foi impreterivelmente menor do que 5% (cinco por cento) na média mensal da frota.
</p>
<?php if (!empty($warranty['resolution_note'])): ?>
<p class="terms-text"><strong>Observações da análise:</strong> <?= nl2br($e($warranty['resolution_note'])) ?></p>
<?php endif; ?>

<div class="signature-box">
    <div class="signature-line"><?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?><?= !empty($warranty['resolved_by_name']) ? ' — ' . $e($warranty['resolved_by_name']) : '' ?></div>
</div>

<p class="footer-note">Documento gerado eletronicamente pelo sistema Ecodiffusore Brasil — ecodiffusorebrasil.com.br</p>
</body>
</html>
