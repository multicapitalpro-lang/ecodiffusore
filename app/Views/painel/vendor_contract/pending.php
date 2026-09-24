<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
$erroLabels = [
    '1' => 'Não foi possível concluir a ação.',
    'csrf' => 'Sessão expirada, tente novamente.',
    'motivo' => 'Informe o motivo da reprovação.',
];
?>
<div class="page-header">
    <h1>✍️ Aprovar Vendedores</h1>
</div>
<p class="section-sub">Vendedores que já assinaram o contrato de representação comercial (gov.br) e enviaram de volta — confira o arquivo antes de aprovar. Só depois disso o acesso completo ao painel é liberado pra eles.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Ação concluída.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro"><?= View::e($erroLabels[$erro] ?? $erroLabels['1']) ?></p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($vendedores as $v): ?>
        <div class="dash-card" style="text-align:left;">
            <span>Enviado em <?= View::e(date('d/m/Y \à\s H:i', strtotime($v['vendedor_contract_sent_at']))) ?></span>
            <strong style="font-size:1rem;"><?= View::e($v['name']) ?></strong>
            <span class="hint-inline"><?= View::e($v['email']) ?></span>
            <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;align-items:center;">
                <a href="/painel/contrato-vendedor/<?= (int) $v['id'] ?>/arquivo" target="_blank" rel="noopener" class="link-button">📄 Ver contrato assinado</a>
            </div>
            <form action="/painel/contrato-vendedor/<?= (int) $v['id'] ?>/aprovar" method="post" class="inline-form" style="margin-top:8px;">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary btn-sm">✅ Aprovar</button>
            </form>
            <form action="/painel/contrato-vendedor/<?= (int) $v['id'] ?>/reprovar" method="post" class="inline-form" style="margin-top:6px;display:flex;gap:6px;">
                <?= Csrf::field() ?>
                <input type="text" name="reason" placeholder="Motivo da reprovação" required style="flex:1;">
                <button type="submit" class="btn btn-outline btn-sm" data-confirm="Reprovar o contrato de <?= View::e($v['name']) ?>? Ele vai precisar assinar e enviar de novo.">❌ Reprovar</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (!$vendedores): ?>
        <p class="hint-text">Nenhum contrato de vendedor esperando aprovação no momento.</p>
    <?php endif; ?>
</div>
