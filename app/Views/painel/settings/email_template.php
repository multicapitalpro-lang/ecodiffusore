<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $settings */
/** @var array $eventTemplates */
/** @var string $previewKey */
/** @var string $preview */
/** @var array $errors */
/** @var array $eventErrors */
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);

$eventMeta = [
    'lead_roteado' => ['label' => 'Lead roteado', 'placeholders' => ['nome', 'whatsapp', 'cidade']],
    'orcamento_registrado' => ['label' => 'Orçamento registrado', 'placeholders' => ['cliente', 'valor']],
    'pedido_registrado' => ['label' => 'Pedido registrado', 'placeholders' => ['cliente', 'valor', 'produto', 'veiculo_placa', 'veiculo_tipo', 'comprador_documento', 'comprador_email', 'comprador_whatsapp', 'comprador_cidade', 'pagamento_forma', 'pagamento_status', 'licenciado', 'vendedor']],
    'pedido_aprovado' => ['label' => 'Pedido aprovado', 'placeholders' => ['cliente', 'valor', 'produto', 'veiculo_placa', 'veiculo_tipo', 'comprador_documento', 'comprador_email', 'comprador_whatsapp', 'comprador_cidade', 'pagamento_forma', 'pagamento_status', 'licenciado', 'vendedor']],
    'cadastro_aprovado' => ['label' => 'Cadastro de Licenciado aprovado', 'placeholders' => ['nome']],
];
?>
<h1>Configurações de E-mail</h1>
<p class="hint-text" style="margin-top:0;">Ajusta os e-mails automáticos (lead roteado, pedido/orçamento registrado, pedido aprovado, cadastro aprovado): identidade visual embaixo e o texto de cada evento mais abaixo.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso — já vale pros próximos e-mails enviados.</p>
<?php endif; ?>
<?php if ($erro): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente novamente.</p>
<?php endif; ?>

<div class="two-col">
    <div>
        <h3 class="section-title" style="margin-top:0;">Identidade visual</h3>
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

            <button type="submit" class="btn btn-primary" style="margin-top:16px;">Salvar identidade visual</button>
        </form>

        <h3 class="section-title">Texto de cada evento</h3>
        <?php foreach ($eventMeta as $key => $meta): ?>
            <?php $tpl = $eventTemplates[$key]; $evErrors = $eventErrors[$key] ?? []; ?>
            <details class="inline-details" <?= $previewKey === $key ? 'open' : '' ?>>
                <summary><?= View::e($meta['label']) ?></summary>
                <form action="/painel/configuracoes/email/evento/<?= View::e($key) ?>" method="post" class="panel-form">
                    <?= Csrf::field() ?>

                    <label for="subject-<?= $key ?>">Assunto do e-mail</label>
                    <input type="text" id="subject-<?= $key ?>" name="subject" value="<?= View::e($tpl['subject']) ?>" maxlength="150">
                    <p class="field-error"><?= View::e($evErrors['subject'] ?? '') ?></p>

                    <label for="title-<?= $key ?>">Título (dentro do e-mail)</label>
                    <input type="text" id="title-<?= $key ?>" name="title" value="<?= View::e($tpl['title']) ?>" maxlength="150">
                    <p class="field-error"><?= View::e($evErrors['title'] ?? '') ?></p>

                    <label for="intro-<?= $key ?>">Texto de introdução</label>
                    <textarea id="intro-<?= $key ?>" name="intro_text" rows="2" maxlength="500"><?= View::e($tpl['intro_text']) ?></textarea>
                    <p class="field-error"><?= View::e($evErrors['intro_text'] ?? '') ?></p>
                    <?php if ($meta['placeholders']): ?>
                        <p class="hint-text" style="margin-top:2px;">Pode usar: <?php foreach ($meta['placeholders'] as $ph): ?><code>{<?= $ph ?>}</code> <?php endforeach; ?></p>
                    <?php endif; ?>

                    <label for="button-<?= $key ?>">Texto do botão</label>
                    <input type="text" id="button-<?= $key ?>" name="button_label" value="<?= View::e($tpl['button_label']) ?>" maxlength="80">
                    <p class="field-error"><?= View::e($evErrors['button_label'] ?? '') ?></p>

                    <div style="display:flex; gap:10px; align-items:center; margin-top:10px;">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <a href="/painel/configuracoes/email?preview=<?= View::e($key) ?>" class="link-small">Ver pré-visualização</a>
                    </div>
                </form>
            </details>
        <?php endforeach; ?>
    </div>

    <div>
        <h3 class="section-title" style="margin-top:0;">Pré-visualização — <?= View::e($eventMeta[$previewKey]['label']) ?></h3>
        <p class="hint-text" style="margin-top:-8px;">
            <?php foreach ($eventMeta as $key => $meta): ?>
                <a href="/painel/configuracoes/email?preview=<?= View::e($key) ?>" class="<?= $previewKey === $key ? 'text-green' : '' ?>" style="margin-right:10px;"><?= View::e($meta['label']) ?></a>
            <?php endforeach; ?>
        </p>
        <iframe srcdoc="<?= View::e($preview) ?>" style="width:100%; min-height:520px; border:1px solid var(--border); border-radius:12px; background:#f1f0e8;"></iframe>
    </div>
</div>
