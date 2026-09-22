<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$statusLabels = ['pendente' => 'Pendente', 'ativo' => 'Ativo', 'recusado' => 'Recusado'];
$statusBadge = ['pendente' => 'novo', 'ativo' => 'active', 'recusado' => 'inativo'];
?>
<div class="page-header">
    <h1>E-mail Profissional</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Peça um e-mail no domínio da Ecodiffusore pra sua rede, ex: <strong>comercial.cascavel@<?= View::e($domain) ?></strong>. É um benefício de quem tem assinatura ativa — a criação da caixa de verdade é feita pelo Admin depois da aprovação, e você é avisado por WhatsApp.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Pedido enviado! Você recebe um aviso assim que o Admin criar a caixa.</p>
<?php endif; ?>

<?php if (!$hasAccess): ?>
    <div class="dash-card" style="text-align:left;">
        <strong>Disponível pra quem tem assinatura ativa</strong>
        <p class="hint-text">Assine o painel pra desbloquear esse e outros benefícios.</p>
        <a href="/painel/assinatura" class="btn btn-primary" style="margin-top:8px;">Ver planos de assinatura</a>
    </div>
<?php else: ?>
    <div class="dash-card" style="text-align:left;max-width:520px;">
        <strong><?= $available ?> de <?= $quota ?> vaga(s) disponível(is) no momento</strong>
        <p class="hint-text">O total de e-mails personalizados é limitado pelo plano de hospedagem — se não houver vaga agora, tente de novo mais tarde ou fale com o Admin.</p>
    </div>

    <?php if ($available > 0): ?>
        <form method="post" action="/painel/emails-profissionais" class="panel-form panel-form-wide" style="max-width:480px;margin-top:16px;">
            <?= Csrf::field() ?>
            <label for="local_part">Endereço desejado</label>
            <div style="display:flex;align-items:center;gap:6px;">
                <input type="text" id="local_part" name="local_part" placeholder="comercial.cascavel" required style="flex:1;">
                <span>@<?= View::e($domain) ?></span>
            </div>
            <p class="field-error" data-error-for="local_part"><?= !empty($errors['local_part']) ? View::e($errors['local_part']) : '' ?></p>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Solicitar</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<h3 class="section-title" style="margin-top:24px;">Meus pedidos</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Endereço</th><th>Status</th><th>Observação do Admin</th><th>Pedido em</th></tr></thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= View::e($r['full_address']) ?></td>
                    <td><span class="status-badge status-<?= $statusBadge[$r['status']] ?? 'novo' ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
                    <td><?= $r['admin_note'] ? View::e($r['admin_note']) : '—' ?></td>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="4">Nenhum pedido ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
