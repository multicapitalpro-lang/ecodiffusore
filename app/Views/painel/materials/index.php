<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $materials */
/** @var array $calculators */
/** @var array $scripts */
/** @var array $testimonials */
/** @var bool $canManage */
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Materiais de venda</h1>
</div>

<?php if (in_array($user['role_slug'], ['licenciado', 'vendedor'], true)): ?>
    <?php $refLink = 'https://ecodiffusorebrasil.com.br/comprar?ref=' . (int) $user['id']; ?>
    <div class="dash-card" style="max-width:640px; margin-bottom:20px;">
        <span>🔗 Seu link de indicação</span>
        <p class="hint-text" style="margin:6px 0 12px;">Mande esse link pro interessado preencher os dados dele — mesmo que não compre na hora, fica salvo no seu CRM. Funciona pra qualquer cidade/estado, não só quem está no seu raio de atendimento.</p>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <input type="text" id="ref-link-input" readonly value="<?= View::e($refLink) ?>" style="flex:1; min-width:220px; font-size:.82rem; padding:8px 10px; border-radius:6px; border:1px solid var(--border);">
            <button type="button" class="btn btn-outline btn-sm" id="ref-link-copy">Copiar link</button>
            <a href="https://wa.me/?text=<?= rawurlencode('Olá! Segue o link pra você conhecer o Ecodiffusore e já garantir sua economia de diesel: ' . $refLink) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">💬 Compartilhar</a>
        </div>
    </div>
    <script>
    document.getElementById('ref-link-copy')?.addEventListener('click', function () {
        var input = document.getElementById('ref-link-input');
        input.select();
        navigator.clipboard?.writeText(input.value);
        this.textContent = 'Copiado!';
        setTimeout(() => { this.textContent = 'Copiar link'; }, 2000);
    });
    </script>
<?php endif; ?>

<p class="hint-text" style="margin-top:-6px;">Documentos institucionais pra mandar pro cliente na hora certa — mesmos arquivos já publicados no site.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-error">Preencha todos os campos.</p>
<?php endif; ?>

<div class="cards-grid">
    <?php foreach ($materials as $m): ?>
        <div class="dash-card">
            <span><?= $m['icon'] ?> <?= View::e($m['title']) ?></span>
            <p class="hint-text" style="margin:6px 0 12px;"><?= View::e($m['description']) ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="<?= View::e($m['file']) ?>" target="_blank" rel="noopener" class="link-small">Abrir</a>
                <a href="<?= View::e($m['file']) ?>" download class="link-small">Baixar</a>
                <a href="https://wa.me/?text=<?= rawurlencode('Segue o material: https://ecodiffusorebrasil.com.br' . $m['file']) ?>" target="_blank" rel="noopener" class="link-small">💬 Enviar por WhatsApp</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="section-title" style="margin-top:32px">Materiais de apoio</h3>
<p class="hint-text" style="margin-top:-4px">Apresentações comerciais prontas pra enviar — sem valor de produto, com link pras calculadoras já incluído.</p>

<div class="cards-grid">
    <?php foreach ($presentations as $m): ?>
        <div class="dash-card">
            <span><?= $m['icon'] ?> <?= View::e($m['title']) ?></span>
            <p class="hint-text" style="margin:6px 0 12px;"><?= View::e($m['description']) ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="<?= View::e($m['file']) ?>" target="_blank" rel="noopener" class="link-small">Abrir</a>
                <a href="<?= View::e($m['file']) ?>" download class="link-small">Baixar</a>
                <a href="https://wa.me/?text=<?= rawurlencode('Segue a apresentação: https://ecodiffusorebrasil.com.br' . $m['file']) ?>" target="_blank" rel="noopener" class="link-small">💬 Enviar por WhatsApp</a>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$presentations): ?>
        <p class="hint-text">Nenhuma apresentação disponível ainda.</p>
    <?php endif; ?>
</div>

<h3 class="section-title" style="margin-top:32px">Calculadoras interativas</h3>
<p class="hint-text" style="margin-top:-4px">Abre numa aba nova, sem precisar de login — pode usar direto na conversa com o cliente ou o interessado em ser licenciado.</p>

<div class="cards-grid">
    <?php foreach ($calculators as $c): ?>
        <div class="dash-card">
            <span><?= $c['icon'] ?> <?= View::e($c['title']) ?></span>
            <p class="hint-text" style="margin:6px 0 12px;"><?= View::e($c['description']) ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="<?= View::e($c['file']) ?>" target="_blank" rel="noopener" class="link-small">Abrir</a>
                <a href="https://wa.me/?text=<?= rawurlencode('Segue a calculadora: https://ecodiffusorebrasil.com.br' . $c['file']) ?>" target="_blank" rel="noopener" class="link-small">💬 Enviar por WhatsApp</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="section-title" style="margin-top:32px">Scripts de resposta pra objeções</h3>
<p class="hint-text" style="margin-top:-4px">Toque em "Enviar por WhatsApp" pra mandar direto pro cliente com quem você está conversando.</p>

<div class="cards-grid">
    <?php foreach ($scripts as $s): ?>
        <div class="dash-card">
            <span>💬 <?= View::e($s['title']) ?></span>
            <p class="hint-text" style="margin:6px 0 12px; white-space:pre-wrap;"><?= View::e($s['response_text']) ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <a href="https://wa.me/?text=<?= rawurlencode($s['response_text']) ?>" target="_blank" rel="noopener" class="link-small">💬 Enviar por WhatsApp</a>
                <?php if ($canManage): ?>
                    <form method="post" action="/painel/materiais/scripts/<?= (int) $s['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir esse script?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$scripts): ?>
        <p class="hint-text">Nenhum script cadastrado ainda.</p>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
    <details class="settings-card" style="max-width:640px;margin-top:12px">
        <summary style="cursor:pointer;font-weight:600">+ Adicionar script</summary>
        <form method="post" action="/painel/materiais/scripts" class="panel-form panel-form-wide" style="margin-top:12px">
            <?= Csrf::field() ?>
            <label for="script-title">Objeção do cliente</label>
            <input type="text" id="script-title" name="title" placeholder="Ex: Tá caro" required>
            <label for="script-text">Resposta sugerida</label>
            <textarea id="script-text" name="response_text" rows="4" style="width:100%" required></textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
        </form>
    </details>
<?php endif; ?>

<h3 class="section-title" style="margin-top:32px">Depoimentos de clientes</h3>
<p class="hint-text" style="margin-top:-4px">Prova social real pra usar durante a conversa de venda.</p>

<div class="cards-grid">
    <?php foreach ($testimonials as $t): ?>
        <div class="dash-card">
            <span>⭐ <?= View::e($t['client_name']) ?><?= $t['city'] ? ' — ' . View::e($t['city']) : '' ?></span>
            <?php if (!empty($t['vehicle'])): ?><p class="hint-text" style="margin:2px 0"><?= View::e($t['vehicle']) ?></p><?php endif; ?>
            <p class="hint-text" style="margin:6px 0 12px; white-space:pre-wrap;">"<?= View::e($t['testimonial_text']) ?>"</p>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <a href="https://wa.me/?text=<?= rawurlencode('Depoimento de cliente Ecodiffusore (' . $t['client_name'] . '): "' . $t['testimonial_text'] . '"') ?>" target="_blank" rel="noopener" class="link-small">💬 Enviar por WhatsApp</a>
                <?php if ($canManage): ?>
                    <form method="post" action="/painel/materiais/depoimentos/<?= (int) $t['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir esse depoimento?');">
                        <?= Csrf::field() ?>
                        <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$testimonials): ?>
        <p class="hint-text">Nenhum depoimento cadastrado ainda<?= $canManage ? ' — adicione um real abaixo' : '' ?>.</p>
    <?php endif; ?>
</div>

<?php if ($canManage): ?>
    <details class="settings-card" style="max-width:640px;margin-top:12px">
        <summary style="cursor:pointer;font-weight:600">+ Adicionar depoimento</summary>
        <form method="post" action="/painel/materiais/depoimentos" class="panel-form panel-form-wide" style="margin-top:12px">
            <?= Csrf::field() ?>
            <div class="form-grid-2">
                <div>
                    <label for="testimonial-name">Nome do cliente</label>
                    <input type="text" id="testimonial-name" name="client_name" required>
                </div>
                <div>
                    <label for="testimonial-city">Cidade</label>
                    <input type="text" id="testimonial-city" name="city">
                </div>
            </div>
            <label for="testimonial-vehicle">Veículo (opcional)</label>
            <input type="text" id="testimonial-vehicle" name="vehicle" placeholder="Ex: Scania R450">
            <label for="testimonial-text">Depoimento</label>
            <textarea id="testimonial-text" name="testimonial_text" rows="4" style="width:100%" required></textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
        </form>
    </details>
<?php endif; ?>
