<?php
use App\Core\View;
?>
<div class="page-header">
    <h1>🔗 Meu Link de Vendas</h1>
</div>
<p class="section-sub">Um link pessoal pra usar em panfleto, QR code, Instagram ou WhatsApp Status — todo contato que chegar por ele cai direto no seu CRM, sem depender do roteamento automático por região.</p>

<?php if (!$hasAccess): ?>
    <div class="dash-card" style="text-align:left;max-width:520px;">
        <strong>Disponível pra quem tem assinatura ativa</strong>
        <p class="hint-text">Assine o painel pra ativar seu link pessoal de vendas.</p>
        <a href="/painel/assinatura" class="btn btn-primary" style="margin-top:8px;">Ver planos de assinatura</a>
    </div>
<?php else: ?>
    <div class="dash-card" style="text-align:left;max-width:640px;">
        <span>Seu link</span>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px;">
            <input type="text" id="seller-public-link" readonly value="<?= View::e($publicUrl) ?>" style="flex:1;min-width:220px;font-size:.82rem;padding:8px 10px;border-radius:6px;border:1px solid var(--border);">
            <button type="button" class="btn btn-outline btn-sm" id="seller-link-copy">Copiar link</button>
            <a href="https://wa.me/?text=<?= rawurlencode('Confira meu link: ' . $publicUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Compartilhar no WhatsApp</a>
        </div>
        <p class="hint-text" style="margin-top:12px;">Esse link é seu pra sempre — não muda mesmo se você imprimir em panfleto ou colocar num QR code.</p>
    </div>

    <script>
    document.getElementById('seller-link-copy')?.addEventListener('click', function () {
        var input = document.getElementById('seller-public-link');
        input.select();
        navigator.clipboard?.writeText(input.value);
        this.textContent = 'Copiado!';
        setTimeout(() => { this.textContent = 'Copiar link'; }, 2000);
    });
    </script>
<?php endif; ?>
