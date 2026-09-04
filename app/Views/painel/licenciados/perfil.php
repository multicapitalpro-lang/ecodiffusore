<?php
use App\Core\ContractTemplateFiller;
use App\Core\Csrf;
use App\Core\View;

$l = $licenciado;
$onboardingLabels = [
    'nao_aplicavel' => 'Não aplicável',
    'aguardando_perfil' => 'Aguardando perfil',
    'aguardando_assinatura' => 'Aguardando assinatura',
    'aguardando_aprovacao' => 'Aguardando aprovação',
    'ativo' => 'Ativo',
    'assinatura_recusada' => 'Assinatura recusada',
    'kyc_recusado' => 'KYC recusado',
];
$status = $l['licenciado_onboarding_status'] ?? 'nao_aplicavel';
?>
<div class="page-header">
    <h1><?= View::e($l['razao_social'] ?: $l['name']) ?></h1>
    <a href="/painel/licenciados" class="btn btn-outline">← Voltar</a>
</div>

<p class="hint-text">Status do cadastro: <strong><?= View::e($onboardingLabels[$status] ?? $status) ?></strong></p>

<?php if (!empty($l['licenciado_rejection_reason'])): ?>
    <p class="form-msg form-msg-erro"><strong>Motivo da última reprovação:</strong> <?= View::e($l['licenciado_rejection_reason']) ?></p>
<?php endif; ?>

<div class="buy-checkout-box" style="max-width:none;">
    <?php if (empty($l['razao_social'])): ?>
        <p class="hint-text">O Licenciado ainda não preencheu o formulário de perfil completo.</p>
    <?php else: ?>
        <div class="form-grid-2">
            <div>
                <p><strong>Nome do representante:</strong> <?= View::e($l['name']) ?></p>
                <p><strong>E-mail:</strong> <?= View::e($l['email']) ?></p>
                <p><strong>Celular:</strong> <?= View::e($l['celular'] ?: '—') ?></p>
                <p><strong>Telefone fixo:</strong> <?= View::e($l['telefone_fixo'] ?: '—') ?></p>
                <p><strong>CPF:</strong> <?= View::e($l['cpf_representante'] ? ContractTemplateFiller::formatCpf($l['cpf_representante']) : '—') ?></p>
                <p><strong>RG:</strong> <?= View::e($l['rg_representante'] ?: '—') ?></p>
                <p><strong>Estado civil:</strong> <?= View::e($l['estado_civil'] ?: '—') ?></p>
                <p><strong>Profissão:</strong> <?= View::e($l['profissao'] ?: '—') ?></p>
            </div>
            <div>
                <p><strong>Razão social:</strong> <?= View::e($l['razao_social']) ?></p>
                <p><strong>CNPJ:</strong> <?= View::e($l['cnpj'] ? ContractTemplateFiller::formatCnpj($l['cnpj']) : '—') ?></p>
                <p><strong>Endereço da empresa:</strong> <?= View::e($l['endereco_logradouro'] ? ContractTemplateFiller::formatEndereco($l) : '—') ?></p>
                <p><strong>Cidade/UF de atuação:</strong> <?= View::e($l['city'] ?: '—') ?>/<?= View::e($l['state'] ?: '—') ?></p>
                <p><strong>Comissão:</strong> <?= View::e($l['commission_pct'] !== null ? number_format((float) $l['commission_pct'], 2, ',', '.') . '%' : '—') ?></p>
            </div>
        </div>

        <h3>Documentos enviados</h3>
        <div style="margin:16px 0;display:flex;gap:10px;flex-wrap:wrap;">
            <?php
            $docs = [
                'comprovante_residencia' => ['📄 Comprovante de residência', $l['comprovante_residencia_path'] ?? null],
                'documento_identidade' => ['📄 Documento de identidade', $l['documento_identidade_path'] ?? null],
                'contrato_social' => ['📄 Contrato social', $l['contrato_social_path'] ?? null],
                'cartao_cnpj' => ['📄 Cartão CNPJ', $l['cartao_cnpj_path'] ?? null],
            ];
            ?>
            <?php foreach ($docs as $tipo => [$label, $path]): ?>
                <?php if ($path): ?>
                    <a href="/painel/licenciados/documento/<?= (int) $l['id'] ?>/<?= $tipo ?>" target="_blank" rel="noopener" class="btn btn-outline"><?= View::e($label) ?></a>
                <?php else: ?>
                    <span class="hint-text"><?= View::e($label) ?>: não enviado</span>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!empty($envelope['signed_document_path'])): ?>
                <a href="/painel/licenciados/contrato/<?= (int) $envelope['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline">📄 Contrato assinado</a>
            <?php elseif (!empty($envelope['clicksign_envelope_id'])): ?>
                <span class="hint-text">Contrato ainda não assinado/baixado. Envelope ClickSign: <code><?= View::e($envelope['clicksign_envelope_id']) ?></code></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($status === 'aguardando_aprovacao'): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start;margin-top:16px;">
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
    <?php endif; ?>
</div>
