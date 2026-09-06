<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $settings */
/** @var string $preview */
/** @var array $errors */
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<h1>Configurações de E-mail</h1>
<p class="hint-text" style="margin-top:0;">Ajusta a identidade visual dos e-mails automáticos (lead roteado, pedido/orçamento registrado, pedido aprovado, cadastro aprovado). O conteúdo de cada evento continua fixo — aqui é só o visual (logo, cores, rodapé).</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso — já vale pros próximos e-mails enviados.</p>
<?php endif; ?>
<?php if ($erro): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php endif; ?>

<div class="two-col">
    <form action="/painel/configuracoes/email" method="post" class="panel-form-wide">
        <?= Csrf::field() ?>

        <label for="logo_url">URL do logo</label>
        <input type="text" id="logo_url" name="logo_url" value="<?= View::e($settings['logo_url']) ?>">
        <p class="field-error"><?= View::e($errors['logo_url'] ?? '') ?></p>
        <p class="hint-text" style="margin-top:2px;">Precisa ser uma URL completa e pública — clientes de e-mail não carregam imagem do seu computador. Se for trocar o logo, suba o arquivo novo em <code>/assets/img/</code> antes.</p>

        <div class="form-grid-2">
            <div>
                <label for="header_bg">Cor de fundo do cabeçalho</label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="color" value="<?= View::e($settings['header_bg']) ?>" oninput="document.getElementById('header_bg').value = this.value" style="width:44px; height:38px; padding:2px; border-radius:8px;">
                    <input type="text" id="header_bg" name="header_bg" value="<?= View::e($settings['header_bg']) ?>" style="max-width:120px;">
                </div>
                <p class="field-error"><?= View::e($errors['header_bg'] ?? '') ?></p>
            </div>
            <div>
                <label for="accent_color">Cor de destaque (botões)</label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="color" value="<?= View::e($settings['accent_color']) ?>" oninput="document.getElementById('accent_color').value = this.value" style="width:44px; height:38px; padding:2px; border-radius:8px;">
                    <input type="text" id="accent_color" name="accent_color" value="<?= View::e($settings['accent_color']) ?>" style="max-width:120px;">
                </div>
                <p class="field-error"><?= View::e($errors['accent_color'] ?? '') ?></p>
            </div>
        </div>

        <label for="footer_text">Texto do rodapé</label>
        <textarea id="footer_text" name="footer_text" rows="2" maxlength="500"><?= View::e($settings['footer_text']) ?></textarea>
        <p class="field-error"><?= View::e($errors['footer_text'] ?? '') ?></p>

        <button type="submit" class="btn btn-primary" style="margin-top:16px;">Salvar</button>
    </form>

    <div>
        <h3 class="section-title" style="margin-top:0;">Pré-visualização</h3>
        <p class="hint-text" style="margin-top:-8px;">Usa o evento "Pedido registrado" como exemplo — salve pra ver com as cores novas.</p>
        <iframe srcdoc="<?= View::e($preview) ?>" style="width:100%; min-height:520px; border:1px solid var(--border); border-radius:12px; background:#f1f0e8;"></iframe>
    </div>
</div>
