<?php
use App\Core\Csrf;
use App\Core\View;
$status = $user['vendedor_contract_status'] ?? 'nao_aplicavel';
$erro = $_GET['erro'] ?? null;
$sucesso = $_GET['sucesso'] ?? null;
$erroLabels = [
    'csrf' => 'Sessão expirada, tente novamente.',
    'arquivo' => 'Não foi possível enviar o arquivo (verifique o tipo e o tamanho — máximo 5MB, PDF/JPG/PNG/WEBP).',
];
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato do Vendedor — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box" style="max-width:640px;">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Contrato do Vendedor</h1>

    <?php if ($sucesso === '1'): ?>
        <p class="form-msg form-msg-ok">Contrato enviado! Assim que seu Licenciado aprovar, seu acesso completo será liberado.</p>
    <?php elseif ($erro): ?>
        <p class="form-msg form-msg-erro"><?= View::e($erroLabels[$erro] ?? 'Não foi possível concluir a ação.') ?></p>
    <?php endif; ?>

    <?php if ($status === 'aprovado'): ?>
        <p class="auth-hint">✅ Seu contrato já foi aprovado. Você tem acesso completo ao painel.</p>
        <a href="/painel" class="btn btn-primary" style="display:block;text-align:center;margin-top:12px;">Ir pro painel</a>
    <?php else: ?>
        <?php if ($status === 'reprovado'): ?>
            <p class="form-msg form-msg-erro">
                ⚠️ Seu contrato foi reprovado pelo seu Licenciado.
                <?php if (!empty($user['vendedor_contract_rejection_reason'])): ?>
                    <br><strong>Motivo:</strong> <?= View::e($user['vendedor_contract_rejection_reason']) ?>
                <?php endif; ?>
                <br>Baixe o contrato de novo, assine e envie outra vez abaixo.
            </p>
        <?php elseif ($status === 'aguardando_aprovacao'): ?>
            <p class="auth-hint">📤 Seu contrato assinado foi enviado em <?= View::e(date('d/m/Y \à\s H:i', strtotime($user['vendedor_contract_sent_at']))) ?> e está aguardando aprovação do seu Licenciado<?= $licenciado ? ' (' . View::e($licenciado['name']) . ')' : '' ?>. Assim que ele aprovar, seu acesso completo ao painel será liberado automaticamente.</p>
        <?php else: ?>
            <p class="auth-hint">Antes de liberar o acesso completo ao painel, você precisa assinar o contrato de representação comercial com seu Licenciado.</p>
        <?php endif; ?>

        <ol class="auth-hint" style="padding-left:20px;margin:16px 0;">
            <li>Baixe o contrato personalizado abaixo (Word, editável).</li>
            <li>Assine digitalmente pelo <a href="https://www.gov.br/governodigital/pt-br/identidade/assinatura-eletronica" target="_blank" rel="noopener">gov.br</a>.</li>
            <li>Envie o arquivo assinado (PDF) de volta aqui embaixo.</li>
        </ol>

        <a href="/painel/contrato-vendedor/baixar" class="btn btn-outline" style="display:block;text-align:center;margin-bottom:16px;">📄 Baixar contrato (Word)</a>

        <?php if ($status !== 'aguardando_aprovacao'): ?>
            <form method="post" action="/painel/contrato-vendedor/enviar" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <label>Contrato assinado (PDF, JPG ou PNG)
                    <input type="file" name="contract" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                </label>
                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px;">Enviar contrato assinado</button>
            </form>
        <?php else: ?>
            <p class="hint-text" style="text-align:center;">Aguardando aprovação — nada mais a fazer por enquanto.</p>
        <?php endif; ?>
    <?php endif; ?>

    <a class="auth-back" href="/painel/logout">Sair</a>
</div>
</body>
</html>
