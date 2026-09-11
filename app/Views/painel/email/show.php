<?php
use App\Core\Csrf;
use App\Core\View;
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1><?= View::e($message['subject']) ?></h1>
    <a href="/painel/email" class="btn btn-outline">← Caixa de Entrada</a>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Resposta enviada com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Não foi possível enviar a resposta agora.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Escreva uma mensagem antes de enviar.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>De:</strong> <?= View::e($message['from']) ?></p>
    <p><strong>Para:</strong> <?= View::e($message['to']) ?></p>
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y H:i', strtotime($message['date']))) ?></p>
</div>

<div class="buy-checkout-box" style="max-width:none;">
    <?php if ($message['body_html']): ?>
        <iframe srcdoc="<?= View::e($message['body_html']) ?>" style="width:100%;min-height:400px;border:0;" sandbox=""></iframe>
    <?php else: ?>
        <pre style="white-space:pre-wrap;font-family:inherit;"><?= View::e($message['body_plain']) ?></pre>
    <?php endif; ?>
</div>

<?php if ($message['attachments']): ?>
    <h3 class="section-title">Anexos</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php foreach ($message['attachments'] as $a): ?>
            <a href="/painel/email/<?= (int) $message['uid'] ?>/anexo/<?= View::e($a['part_num']) ?>" target="_blank" rel="noopener" class="btn btn-outline">📎 <?= View::e($a['filename']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h3 class="section-title">Responder</h3>
<form method="post" action="/painel/email/<?= (int) $message['uid'] ?>/responder" class="panel-form">
    <?= Csrf::field() ?>
    <textarea name="body" rows="6" placeholder="Escreva sua resposta..." required></textarea>
    <button type="submit" class="btn btn-primary" style="margin-top:12px">Enviar resposta</button>
</form>
