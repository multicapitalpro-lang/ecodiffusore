<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Dados da Empresa</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Usados no Comprovante de Pós-venda de Instalação e em outros documentos oficiais gerados pelo sistema.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php endif; ?>

<form method="post" action="/painel/configuracoes/empresa" class="panel-form panel-form-wide" style="max-width:640px">
    <?= Csrf::field() ?>
    <label for="razao_social">Razão social</label>
    <input type="text" id="razao_social" name="razao_social" value="<?= View::e($settings['razao_social'] ?? '') ?>" required>

    <label for="cnpj">CNPJ</label>
    <input type="text" id="cnpj" name="cnpj" value="<?= View::e($settings['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00" required>

    <label for="endereco">Endereço completo</label>
    <textarea id="endereco" name="endereco" rows="2" required><?= View::e($settings['endereco'] ?? '') ?></textarea>

    <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
</form>

<div class="page-header" style="margin-top:32px;">
    <h2>Termos de Compra</h2>
</div>
<p class="hint-text" style="margin-top:-6px;">O cliente precisa ler e aceitar esse texto, no próprio painel dele, antes de conseguir pagar um Pedido. Editar aqui só vale pra próximas compras — o aceite de cada cliente guarda uma cópia do texto de quando ele aceitou.</p>

<form method="post" action="/painel/configuracoes/empresa/termos" class="panel-form panel-form-wide" style="max-width:640px">
    <?= Csrf::field() ?>
    <label for="terms_text">Texto dos Termos de Compra</label>
    <textarea id="terms_text" name="terms_text" rows="14"><?= View::e($settings['terms_text'] ?? '') ?></textarea>
    <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar termos</button>
</form>

<div class="page-header" style="margin-top:32px;">
    <h2>E-mails Profissionais (@ecodiffusorebrasil.com.br)</h2>
</div>
<p class="hint-text" style="margin-top:-6px;">A Hostinger limita a quantidade de caixas de e-mail por plano de hospedagem. Defina aqui quantos e-mails personalizados (ex: comercial.cascavel@ecodiffusorebrasil.com.br) o sistema aceita ter pedidos em aberto/ativos ao mesmo tempo — ajuste conforme o plano contratado. Os pedidos ficam em <a href="/painel/emails-profissionais">Emails Profissionais</a>.</p>

<form method="post" action="/painel/configuracoes/empresa/cota-emails" class="panel-form panel-form-wide" style="max-width:320px">
    <?= Csrf::field() ?>
    <label for="custom_email_quota">Quantidade máxima de e-mails</label>
    <input type="number" id="custom_email_quota" name="custom_email_quota" min="0" value="<?= (int) ($settings['custom_email_quota'] ?? 0) ?>" required>
    <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar cota</button>
</form>
