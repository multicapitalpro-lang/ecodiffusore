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
<title>Comprovante de Pós-venda de Instalação</title>
<style>
    @page { margin: 100px 42px 70px 42px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #2b2b2b; line-height: 1.45; }

    header { position: fixed; top: -80px; left: 0px; right: 0px; height: 80px; }
    footer { position: fixed; bottom: -55px; left: 0px; right: 0px; height: 40px; text-align: center; }

    .brand-bar { background: #0e2a1c; height: 6px; width: 100%; }
    .letterhead-inner { padding: 14px 0 10px; border-bottom: 2px solid #8dc63f; }
    .letterhead-brand { font-size: 17px; font-weight: bold; color: #0e2a1c; letter-spacing: .4px; }
    .letterhead-tag { font-size: 8.5px; color: #6ea62c; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
    .letterhead-doc { float: right; text-align: right; font-size: 9px; color: #777; }
    .letterhead-doc strong { display: block; font-size: 13px; color: #0e2a1c; }

    .footer-line { border-top: 1px solid #ddd; padding-top: 6px; font-size: 8px; color: #999; }

    h1.doc-title { font-size: 19px; color: #0e2a1c; margin: 0 0 3px; text-align: center; text-transform: uppercase; letter-spacing: .5px; }
    p.doc-subtitle { text-align: center; font-size: 9.5px; color: #777; margin: 0 0 18px; }

    .section { margin-bottom: 14px; page-break-inside: avoid; }
    .section-title {
        background: #f0f7e6; color: #4a7a1e; font-size: 10px; font-weight: bold; text-transform: uppercase;
        letter-spacing: .4px; padding: 5px 10px; margin: 0 0 6px; border-left: 3px solid #8dc63f;
    }
    table.info-table { width: 100%; border-collapse: collapse; }
    table.info-table td { padding: 5px 10px; border-bottom: 1px solid #ececec; vertical-align: top; }
    table.info-table td.label { width: 34%; color: #888; font-size: 9.5px; }
    table.info-table td.value { font-weight: bold; color: #222; }

    .terms-block { margin-top: 4px; }
    p.terms-text { font-size: 9.7px; line-height: 1.65; text-align: justify; margin: 0 0 10px; page-break-inside: avoid; }
    p.terms-text strong { color: #0e2a1c; }
    p.terms-highlight {
        background: #fbf6e8; border-left: 3px solid #d6a92c; padding: 8px 12px; font-size: 9.7px;
        line-height: 1.6; margin: 0 0 10px; page-break-inside: avoid;
    }
    p.terms-highlight strong { color: #8a6710; }

    .signature-area { margin-top: 30px; page-break-inside: avoid; }
    .signature-row { display: table; width: 100%; }
    .signature-col { display: table-cell; width: 50%; text-align: center; padding: 0 14px; }
    .signature-line { border-top: 1px solid #444; margin: 40px 10px 5px; }
    .signature-name { font-size: 9.5px; font-weight: bold; color: #222; }
    .signature-role { font-size: 8.5px; color: #888; }

    .stamp {
        display: inline-block; margin-top: 16px; padding: 4px 12px; border: 1px solid #8dc63f; border-radius: 3px;
        color: #4a7a1e; font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px;
    }
</style>
</head>
<body>

<header>
    <div class="brand-bar"></div>
    <div class="letterhead-inner">
        <div class="letterhead-doc"><strong>Comprovante #<?= (int) $warranty['id'] ?></strong>Pedido #<?= (int) $warranty['order_id'] ?></div>
        <div class="letterhead-brand"><?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?></div>
        <div class="letterhead-tag">Pós-venda de Instalação Obrigatório</div>
    </div>
</header>

<footer>
    <div class="footer-line">
        <?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?> · CNPJ <?= $e($company['cnpj'] ?? null) ?> · Documento gerado eletronicamente — ecodiffusorebrasil.com.br
    </div>
</footer>

<h1 class="doc-title">Comprovante de Pós-venda de Instalação</h1>
<p class="doc-subtitle">Emitido em <?= date('d/m/Y') ?> · Válido em todo o território nacional</p>

<div class="section">
    <p class="section-title">Dados da empresa emissora</p>
    <table class="info-table">
        <tr><td class="label">Razão social</td><td class="value"><?= $e($company['razao_social'] ?? null) ?></td></tr>
        <tr><td class="label">CNPJ</td><td class="value"><?= $e($company['cnpj'] ?? null) ?></td></tr>
        <tr><td class="label">Endereço</td><td class="value"><?= $e($company['endereco'] ?? null) ?></td></tr>
    </table>
</div>

<div class="section">
    <p class="section-title">Dados do comprador</p>
    <table class="info-table">
        <tr><td class="label">Nome / Razão social</td><td class="value"><?= $e($warranty['client_name'] ?? null) ?></td></tr>
        <tr><td class="label">CPF/CNPJ</td><td class="value"><?= $e($warranty['client_document'] ?? null) ?></td></tr>
        <tr><td class="label">Endereço</td><td class="value"><?= $e(trim(($warranty['client_address'] ?? '') . (!empty($warranty['client_city']) ? ', ' . $warranty['client_city'] : '') . (!empty($warranty['client_state']) ? '/' . $warranty['client_state'] : ''))) ?></td></tr>
    </table>
</div>

<div class="section">
    <p class="section-title">Dados do motorista</p>
    <table class="info-table">
        <tr><td class="label">Nome completo</td><td class="value"><?= $e($warranty['driver_name'] ?? null) ?></td></tr>
        <tr><td class="label">CPF</td><td class="value"><?= $e($warranty['driver_document'] ?? null) ?></td></tr>
    </table>
</div>

<div class="section">
    <p class="section-title">Dados do pedido e do veículo</p>
    <table class="info-table">
        <tr><td class="label">Nº do pedido</td><td class="value">#<?= (int) $warranty['order_id'] ?></td></tr>
        <tr><td class="label">Nº da nota fiscal</td><td class="value"><?= $e($warranty['nfe_number'] ?? null) ?></td></tr>
        <tr><td class="label">Data do pedido</td><td class="value"><?= $e(date('d/m/Y', strtotime($warranty['order_date']))) ?></td></tr>
        <tr><td class="label">Produto</td><td class="value"><?= $e($produtos) ?></td></tr>
        <tr><td class="label">Veículo</td><td class="value"><?= $e($vehicleLine ?: null) ?></td></tr>
        <tr><td class="label">Placa</td><td class="value"><?= $e($vehicle['plate'] ?? null) ?></td></tr>
        <tr><td class="label">Instalação confirmada em</td><td class="value"><?= $warranty['resolved_at'] ? $e(date('d/m/Y', strtotime($warranty['resolved_at']))) : '—' ?></td></tr>
    </table>
</div>

<div class="section terms-block">
    <p class="section-title">Confirmação de pós-venda de instalação</p>
    <p class="terms-text">
        A <strong><?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?></strong> certifica que o processo
        obrigatório de pós-venda de instalação referente ao pedido acima foi analisado e <strong>confirmado</strong>,
        conforme os documentos e informações apresentados pelo comprador (dados do motorista, documento do veículo e
        registro fotográfico/telemétrico da instalação). Este comprovante atesta que o equipamento foi instalado
        seguindo o processo exigido pela fabricante para o correto funcionamento do produto.
    </p>
    <p class="terms-text">
        O acompanhamento de consumo/telemetria informado nesta etapa é usado pela equipe técnica para validar que a
        instalação foi realizada corretamente e para dar suporte ao comprador em caso de dúvida técnica sobre o
        funcionamento do equipamento.
    </p>
    <?php if (!empty($warranty['resolution_note'])): ?>
    <p class="terms-text"><strong>Observações da análise:</strong> <?= nl2br($e($warranty['resolution_note'])) ?></p>
    <?php endif; ?>
</div>

<div class="signature-area">
    <div class="signature-row">
        <div class="signature-col">
            <div class="signature-line"></div>
            <div class="signature-name"><?= $e($company['razao_social'] ?? 'Ecodiffusore Brasil') ?></div>
            <div class="signature-role"><?= !empty($warranty['resolved_by_name']) ? $e($warranty['resolved_by_name']) : 'Responsável pela aprovação' ?></div>
        </div>
        <div class="signature-col">
            <div class="signature-line"></div>
            <div class="signature-name"><?= $e($warranty['client_name'] ?? null) ?></div>
            <div class="signature-role">Comprador</div>
        </div>
    </div>
    <div style="text-align:center;">
        <span class="stamp">✔ Instalação confirmada</span>
    </div>
</div>

</body>
</html>
