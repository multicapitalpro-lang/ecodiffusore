<?php
/** @var string $sellerName */
/** @var string $certifiedAt */
/** @var string $certNumber */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Certificado de Vendedor Ecodiffusore</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; color: #222; }
    .frame { border: 3px solid #1a7a4c; padding: 40px; text-align: center; margin: 10px; }
    .brand { font-size: 16px; font-weight: bold; color: #1a7a4c; letter-spacing: 1px; text-transform: uppercase; }
    .title { font-size: 32px; margin: 22px 0 6px; color: #0e0e0e; }
    .subtitle { font-size: 13px; color: #666; margin-bottom: 30px; }
    .name { font-size: 28px; font-weight: bold; color: #1a7a4c; margin: 18px 0; border-bottom: 1px solid #ccc; display: inline-block; padding-bottom: 8px; }
    .text { font-size: 13px; color: #444; max-width: 560px; margin: 0 auto 26px; line-height: 1.6; }
    .meta { font-size: 10px; color: #888; margin-top: 34px; }
</style>
</head>
<body>
<div class="frame">
    <div class="brand">Ecodiffusore Brasil</div>
    <div class="title">Certificado de Vendedor</div>
    <div class="subtitle">Este certificado atesta que</div>
    <div class="name"><?= htmlspecialchars($sellerName, ENT_QUOTES, 'UTF-8') ?></div>
    <p class="text">concluiu integralmente o treinamento oficial de produto e vendas da Ecodiffusore Brasil, estando apto(a) a representar a marca e orientar clientes sobre o sistema Ecodiffusore com conhecimento técnico e comercial completo.</p>
    <div class="meta">Certificado nº <?= htmlspecialchars($certNumber, ENT_QUOTES, 'UTF-8') ?> · Emitido em <?= date('d/m/Y', strtotime($certifiedAt)) ?> · ecodiffusorebrasil.com.br</div>
</div>
</body>
</html>
