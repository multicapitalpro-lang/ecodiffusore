<?php
use App\Core\Csrf;
use App\Core\View;
$values = $old ?? [];
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Complete seu cadastro — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body class="auth-body">
<div class="auth-box auth-box-wide">
    <img src="<?= View::asset('/assets/img/logo-full-navy.png') ?>" alt="Ecodiffusore Brasil" class="auth-logo">
    <h1>Complete seu cadastro de Licenciado</h1>
    <p class="auth-hint">Precisamos desses dados pra gerar seu contrato e confirmar sua identidade antes de liberar o painel completo.</p>

    <?php if (!empty($errors['geral'])): ?>
        <p class="form-msg form-msg-erro"><?= View::e($errors['geral']) ?></p>
    <?php endif; ?>

    <?php if (!empty($rejectionReason)): ?>
        <p class="form-msg form-msg-erro"><strong>Seu cadastro anterior foi reprovado:</strong> <?= View::e($rejectionReason) ?><br>Revise os dados abaixo e reenvie.</p>
    <?php endif; ?>

    <form action="/painel/licenciados/completar-perfil" method="post" class="auth-form" enctype="multipart/form-data">
        <?= Csrf::field() ?>

        <label for="razao_social">Razão social</label>
        <input type="text" id="razao_social" name="razao_social" value="<?= View::e($values['razao_social'] ?? '') ?>" required>
        <?php if (!empty($errors['razao_social'])): ?><p class="field-error"><?= View::e($errors['razao_social']) ?></p><?php endif; ?>

        <div class="form-grid-2">
            <div>
                <label for="cnpj">CNPJ</label>
                <input type="text" id="cnpj" name="cnpj" value="<?= View::e($values['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00" required>
                <?php if (!empty($errors['cnpj'])): ?><p class="field-error"><?= View::e($errors['cnpj']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="endereco_cep">CEP</label>
                <input type="text" id="endereco_cep" name="endereco_cep" value="<?= View::e($values['endereco_cep'] ?? '') ?>" placeholder="00000-000" required>
                <?php if (!empty($errors['endereco_cep'])): ?><p class="field-error"><?= View::e($errors['endereco_cep']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="endereco_logradouro">Logradouro (rua/avenida)</label>
                <input type="text" id="endereco_logradouro" name="endereco_logradouro" value="<?= View::e($values['endereco_logradouro'] ?? '') ?>" required>
                <?php if (!empty($errors['endereco_logradouro'])): ?><p class="field-error"><?= View::e($errors['endereco_logradouro']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="endereco_numero">Número</label>
                <input type="text" id="endereco_numero" name="endereco_numero" value="<?= View::e($values['endereco_numero'] ?? '') ?>" required>
                <?php if (!empty($errors['endereco_numero'])): ?><p class="field-error"><?= View::e($errors['endereco_numero']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="endereco_complemento">Complemento (opcional)</label>
                <input type="text" id="endereco_complemento" name="endereco_complemento" value="<?= View::e($values['endereco_complemento'] ?? '') ?>">
            </div>
            <div>
                <label for="endereco_bairro">Bairro</label>
                <input type="text" id="endereco_bairro" name="endereco_bairro" value="<?= View::e($values['endereco_bairro'] ?? '') ?>" required>
                <?php if (!empty($errors['endereco_bairro'])): ?><p class="field-error"><?= View::e($errors['endereco_bairro']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="endereco_cidade">Cidade</label>
                <input type="text" id="endereco_cidade" name="endereco_cidade" value="<?= View::e($values['endereco_cidade'] ?? '') ?>" required>
                <?php if (!empty($errors['endereco_cidade'])): ?><p class="field-error"><?= View::e($errors['endereco_cidade']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="endereco_uf">UF</label>
                <input type="text" id="endereco_uf" name="endereco_uf" maxlength="2" style="text-transform:uppercase" value="<?= View::e($values['endereco_uf'] ?? '') ?>" required>
                <?php if (!empty($errors['endereco_uf'])): ?><p class="field-error"><?= View::e($errors['endereco_uf']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="cpf_representante">Seu CPF (representante)</label>
                <input type="text" id="cpf_representante" name="cpf_representante" value="<?= View::e($values['cpf_representante'] ?? '') ?>" placeholder="000.000.000-00" required>
                <?php if (!empty($errors['cpf_representante'])): ?><p class="field-error"><?= View::e($errors['cpf_representante']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="rg_representante">Seu RG (representante)</label>
                <input type="text" id="rg_representante" name="rg_representante" value="<?= View::e($values['rg_representante'] ?? '') ?>" required>
                <?php if (!empty($errors['rg_representante'])): ?><p class="field-error"><?= View::e($errors['rg_representante']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="estado_civil">Estado civil</label>
                <select id="estado_civil" name="estado_civil" required>
                    <option value="">Selecione...</option>
                    <?php foreach (['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Viúvo(a)', 'União estável'] as $opt): ?>
                        <option value="<?= View::e($opt) ?>" <?= ($values['estado_civil'] ?? '') === $opt ? 'selected' : '' ?>><?= View::e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['estado_civil'])): ?><p class="field-error"><?= View::e($errors['estado_civil']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="profissao">Profissão atual</label>
                <input type="text" id="profissao" name="profissao" value="<?= View::e($values['profissao'] ?? '') ?>" required>
                <?php if (!empty($errors['profissao'])): ?><p class="field-error"><?= View::e($errors['profissao']) ?></p><?php endif; ?>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label for="celular">Celular (com DDD)</label>
                <input type="text" id="celular" name="celular" value="<?= View::e($values['celular'] ?? '') ?>" placeholder="(45) 99999-9999" required>
                <?php if (!empty($errors['celular'])): ?><p class="field-error"><?= View::e($errors['celular']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="telefone_fixo">Telefone fixo (opcional)</label>
                <input type="text" id="telefone_fixo" name="telefone_fixo" value="<?= View::e($values['telefone_fixo'] ?? '') ?>" placeholder="(45) 3333-3333">
                <?php if (!empty($errors['telefone_fixo'])): ?><p class="field-error"><?= View::e($errors['telefone_fixo']) ?></p><?php endif; ?>
            </div>
        </div>

        <label for="comprovante_residencia">Comprovante de residência (PDF, JPG, PNG ou WEBP)</label>
        <input type="file" id="comprovante_residencia" name="comprovante_residencia" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
        <?php if (!empty($errors['comprovante_residencia'])): ?><p class="field-error"><?= View::e($errors['comprovante_residencia']) ?></p><?php endif; ?>

        <button type="submit" class="btn btn-primary">Continuar pra assinatura do contrato</button>
    </form>

    <a class="auth-back" href="/painel/logout">Sair</a>
</div>
</body>
</html>
