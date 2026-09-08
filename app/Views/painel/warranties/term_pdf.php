<?php
/** @var array $warranty */
/** @var array $items */
$produtos = $items ? implode(', ', array_map(fn ($i) => $i['product_name'] . ' (x' . (int) $i['quantity'] . ')', $items)) : '—';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Termo de Garantia</title>
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
    .terms-text { font-size: 10.5px; line-height: 1.6; text-align: justify; }
    .signature-box { margin-top: 40px; text-align: center; }
    .signature-line { border-top: 1px solid #333; width: 260px; margin: 0 auto 4px; padding-top: 4px; }
    .footer-note { margin-top: 18px; font-size: 9.5px; color: #777; }
</style>
</head>
<body>
<div class="letterhead">
    <div class="letterhead-brand">Ecodiffusore Brasil</div>
    <div class="letterhead-title">Termo de Garantia</div>
    <div class="letterhead-meta">Pedido #<?= (int) $warranty['order_id'] ?> · Emitido em <?= date('d/m/Y') ?></div>
</div>

<h2>Dados do comprador</h2>
<table class="data-table">
    <tr><td>Cliente</td><td><?= htmlspecialchars($warranty['client_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>

<h2>Dados do produto</h2>
<table class="data-table">
    <tr><td>Produto</td><td><?= htmlspecialchars($produtos, ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Data do pedido</td><td><?= htmlspecialchars(date('d/m/Y', strtotime($warranty['order_date'])), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <tr><td>Garantia aprovada em</td><td><?= $warranty['resolved_at'] ? htmlspecialchars(date('d/m/Y', strtotime($warranty['resolved_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
</table>

<h2>Termo</h2>
<p class="terms-text">
    A Ecodiffusore Brasil certifica que a solicitação de garantia referente ao pedido acima foi
    analisada e <strong>aprovada</strong>, conforme os documentos e informações apresentados pelo
    comprador. Este termo comprova a cobertura de garantia do produto adquirido, nos termos e
    condições estabelecidos no momento da compra.
</p>
<?php if (!empty($warranty['resolution_note'])): ?>
<p class="terms-text"><strong>Observações da análise:</strong> <?= nl2br(htmlspecialchars($warranty['resolution_note'], ENT_QUOTES, 'UTF-8')) ?></p>
<?php endif; ?>

<div class="signature-box">
    <div class="signature-line">Ecodiffusore Brasil<?= !empty($warranty['resolved_by_name']) ? ' — ' . htmlspecialchars($warranty['resolved_by_name'], ENT_QUOTES, 'UTF-8') : '' ?></div>
</div>

<p class="footer-note">Documento gerado eletronicamente pelo sistema Ecodiffusore Brasil — ecodiffusorebrasil.com.br</p>
</body>
</html>
