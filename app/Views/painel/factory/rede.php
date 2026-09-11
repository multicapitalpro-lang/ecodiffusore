<?php
use App\Core\View;
?>
<div class="page-header">
    <h1>Consultar Rede</h1>
</div>

<p class="hint-text" style="margin-top:0;">Se alguém te procurar direto (fora de um pedido já pago), confira aqui se essa pessoa já é Licenciado, Vendedor/Gestor ou já é Lead nosso. Se não for ninguém conhecido, use o link abaixo pra mandar ela comprar com a Ecodiffusore Brasil.</p>

<div class="settings-card" style="max-width:680px;margin-bottom:24px;">
    <h3 class="section-title" style="margin-top:0;">Link pra quem não é da rede ainda</h3>
    <p class="hint-text" style="margin-top:0;">Sempre o mesmo link — a pessoa cai no fluxo normal de compra e o sistema encaminha sozinho pro vendedor mais próximo dela.</p>
    <div class="inline-form" style="gap:8px;">
        <input type="text" readonly value="<?= View::e($purchaseLink) ?>" id="factory-link-input" style="flex:1;padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);font-size:.85rem;" onclick="this.select()">
        <button type="button" class="btn btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('factory-link-input').value); this.textContent='Copiado!'; setTimeout(() => this.textContent='Copiar', 1500);">Copiar</button>
    </div>
</div>

<form method="get" action="/painel/fabrica/rede" class="filter-bar">
    <label>
        Nome, CPF ou telefone
        <input type="text" name="q" value="<?= View::e($term) ?>" placeholder="Digite pelo menos 2 letras/números..." autofocus>
    </label>
    <button type="submit" class="btn btn-primary">Buscar</button>
</form>

<?php if ($term !== '' && mb_strlen($term) < 2): ?>
    <p class="hint-text">Digite pelo menos 2 caracteres pra buscar.</p>
<?php elseif ($term !== ''): ?>
    <?php $totalFound = count($licenciados) + count($vendedores) + count($leads); ?>
    <?php if ($totalFound === 0): ?>
        <p class="form-msg form-msg-ok" style="margin-top:16px;">Ninguém encontrado com "<?= View::e($term) ?>" — pode não ser da rede ainda. Use o link de compra acima.</p>
    <?php endif; ?>

    <?php if ($licenciados): ?>
        <h3 class="section-title">Licenciados</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Nome</th><th>CPF</th><th>Telefone</th></tr></thead>
                <tbody>
                    <?php foreach ($licenciados as $l): ?>
                        <tr>
                            <td><?= View::e($l['name']) ?></td>
                            <td><?= View::e($l['cpf_representante'] ?: '—') ?></td>
                            <td><?= View::e($l['whatsapp'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($vendedores): ?>
        <h3 class="section-title">Gestores e Vendedores</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Nome</th><th>Papel</th><th>Telefone</th></tr></thead>
                <tbody>
                    <?php foreach ($vendedores as $v): ?>
                        <tr>
                            <td><?= View::e($v['name']) ?></td>
                            <td><?= View::e($v['role_name']) ?></td>
                            <td><?= View::e($v['whatsapp'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($leads): ?>
        <h3 class="section-title">Leads</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Nome</th><th>Telefone</th><th>Cidade</th></tr></thead>
                <tbody>
                    <?php foreach ($leads as $l): ?>
                        <tr>
                            <td><?= View::e($l['name']) ?></td>
                            <td><?= View::e($l['whatsapp'] ?: '—') ?></td>
                            <td><?= View::e($l['city'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
