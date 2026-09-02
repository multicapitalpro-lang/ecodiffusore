<?php
use App\Core\ContractTemplateFiller;
use App\Core\Csrf;
use App\Core\View;
$sucesso = $_GET['sucesso'] ?? null;
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Aprovação de Cadastros de Licenciado</h1>
</div>

<?php if ($sucesso === '1'): ?>
    <p class="form-msg form-msg-ok">Ação registrada com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Informe o motivo da reprovação.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir a ação.</p>
<?php endif; ?>

<?php if (!$pending): ?>
    <p class="hint-text">Nenhum cadastro pendente de aprovação no momento.</p>
<?php endif; ?>

<?php foreach ($pending as $l): ?>
    <div class="buy-checkout-box" style="max-width:none;margin-bottom:24px;">
        <h3 style="margin-top:0;"><?= View::e($l['razao_social'] ?: $l['name']) ?></h3>
        <p class="hint-text">Assinado e verificado pelo ClickSign em <?= View::e($l['updated_at']) ?> — aguardando decisão.</p>

        <div class="form-grid-2">
            <div>
                <p><strong>Nome do representante:</strong> <?= View::e($l['name']) ?></p>
                <p><strong>E-mail:</strong> <?= View::e($l['email']) ?></p>
                <p><strong>Celular:</strong> <?= View::e($l['celular']) ?></p>
                <p><strong>Telefone fixo:</strong> <?= View::e($l['telefone_fixo'] ?: '—') ?></p>
                <p><strong>CPF:</strong> <?= View::e(ContractTemplateFiller::formatCpf($l['cpf_representante'])) ?></p>
                <p><strong>RG:</strong> <?= View::e($l['rg_representante']) ?></p>
                <p><strong>Estado civil:</strong> <?= View::e($l['estado_civil']) ?></p>
                <p><strong>Profissão:</strong> <?= View::e($l['profissao']) ?></p>
            </div>
            <div>
                <p><strong>Razão social:</strong> <?= View::e($l['razao_social']) ?></p>
                <p><strong>CNPJ:</strong> <?= View::e(ContractTemplateFiller::formatCnpj($l['cnpj'])) ?></p>
                <p><strong>Endereço da empresa:</strong> <?= View::e(ContractTemplateFiller::formatEndereco($l)) ?></p>
                <p><strong>Cidade/UF de atuação:</strong> <?= View::e($l['city'] ?: '—') ?>/<?= View::e($l['state'] ?: '—') ?></p>
            </div>
        </div>

        <div style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="/painel/licenciados/comprovante/<?= (int) $l['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline">📄 Comprovante de residência</a>
            <?php if (!empty($l['envelope']['signed_document_path'])): ?>
                <a href="/painel/licenciados/contrato/<?= (int) $l['envelope']['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline">📄 Contrato assinado</a>
            <?php else: ?>
                <span class="hint-text">Contrato assinado ainda não baixado do ClickSign.</span>
            <?php endif; ?>
            <?php if (!empty($l['envelope']['clicksign_envelope_id'])): ?>
                <span class="hint-text">Documentos de verificação (selfie/KYC): confira no painel do ClickSign, envelope <code><?= View::e($l['envelope']['clicksign_envelope_id']) ?></code>.</span>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;">
            <form action="/painel/licenciados/<?= (int) $l['id'] ?>/aprovar" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary">Aprovar cadastro</button>
            </form>
            <form action="/painel/licenciados/<?= (int) $l['id'] ?>/reprovar" method="post" class="inline-form" style="display:flex;gap:8px;align-items:flex-start;">
                <?= Csrf::field() ?>
                <input type="text" name="reason" placeholder="Motivo da reprovação" style="min-width:260px;">
                <button type="submit" class="btn btn-danger">Reprovar</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
