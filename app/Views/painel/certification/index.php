<?php
use App\Core\View;
$erro = isset($_GET['erro']);
$certified = !empty($user['training_completed_at']);
?>
<div class="page-header">
    <h1>🏅 Minha Certificação</h1>
</div>
<p class="section-sub">Depois de concluir o treinamento, você vira Vendedor Certificado Ecodiffusore Brasil — um selo de confiança pra mostrar pros seus clientes.</p>

<?php if ($erro): ?>
    <p class="form-msg form-msg-erro">Termine o treinamento primeiro pra liberar o certificado.</p>
<?php endif; ?>

<div class="dash-card" style="text-align:left;max-width:480px;">
    <?php if ($certified): ?>
        <span>✅ Certificado desde <?= View::e(date('d/m/Y', strtotime($user['training_completed_at']))) ?></span>
        <strong style="font-size:1.3rem;">Vendedor Certificado</strong>
        <a href="/painel/certificacao/pdf" class="btn btn-primary" style="margin-top:10px;display:inline-block;">📄 Baixar certificado (PDF)</a>
    <?php else: ?>
        <span>Treinamento em andamento</span>
        <strong style="font-size:1.3rem;">Ainda não certificado</strong>
        <a href="/painel/treinamento" class="btn btn-outline" style="margin-top:10px;display:inline-block;">Continuar treinamento</a>
    <?php endif; ?>
</div>

<?php if ($certified): ?>
    <p class="hint-text" style="margin-top:16px;max-width:480px;">
        <?php if (!empty($user['public_slug'])): ?>
            O selo "✅ Vendedor Certificado" já aparece automaticamente na sua página pessoal (<strong>ecodiffusorebrasil.com.br/v/<?= View::e($user['public_slug']) ?></strong>) — é isso que seus clientes veem quando você compartilha o link.
        <?php else: ?>
            Gere seu <a href="/painel/meu-link">link de vendas pessoal</a> pra que o selo "✅ Vendedor Certificado" apareça automaticamente pros seus clientes.
        <?php endif; ?>
    </p>
<?php endif; ?>
