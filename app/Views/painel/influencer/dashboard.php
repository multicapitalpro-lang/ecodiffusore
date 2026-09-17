<?php use App\Core\View; ?>
<div class="page-header">
    <h1>Meu Painel de Influenciador</h1>
</div>

<div class="buy-checkout-box" style="max-width:none;margin-bottom:24px;">
    <p><strong>Seu link de indicação:</strong></p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="text" readonly value="<?= View::e($referralLink) ?>" id="referral-link-input" style="flex:1;min-width:260px;padding:10px;border:1px solid #d7dde5;border-radius:8px;">
        <button type="button" class="btn btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('referral-link-input').value).then(() => alert('Link copiado!'))">Copiar link</button>
    </div>
    <p class="hint-text" style="margin-top:10px;">Compartilhe esse link — todo mundo que clicar e comprar fica registrado aqui no seu painel, com sua comissão.</p>
</div>

<div class="cards-grid">
    <div class="dash-card">
        <span>Pessoas que clicaram no seu link</span>
        <strong><?= (int) $stats['leads'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Orçamentos gerados</span>
        <strong><?= (int) $stats['orcamentos'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Vendas confirmadas</span>
        <strong><?= (int) $stats['vendas'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Comissão total</span>
        <strong>R$ <?= number_format((float) $summary['total'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>Já paga</span>
        <strong>R$ <?= number_format((float) $summary['total_pago'], 2, ',', '.') ?></strong>
    </div>
    <div class="dash-card">
        <span>A receber</span>
        <strong>R$ <?= number_format((float) $summary['total_pendente'], 2, ',', '.') ?></strong>
    </div>
</div>

<h3 class="section-title">Quem veio pelo seu link</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>WhatsApp</th>
                <th>Cidade</th>
                <th>Data</th>
                <th>Orçamento</th>
                <th>Venda</th>
                <th>Sua comissão</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($leads as $l): ?>
                <tr>
                    <td><?= View::e($l['name']) ?></td>
                    <td><?= View::e($l['whatsapp']) ?></td>
                    <td><?= View::e($l['city'] ?: '—') ?></td>
                    <td><?= View::e(date('d/m/Y', strtotime($l['created_at']))) ?></td>
                    <td>
                        <?php if ($l['quote_id']): ?>
                            R$ <?= number_format((float) $l['quote_value'], 2, ',', '.') ?>
                            <br><small class="hint-text"><?= View::e($l['quote_status']) ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l['order_id']): ?>
                            <span class="status-badge status-active">Vendido</span>
                            <br>R$ <?= number_format((float) $l['order_value'], 2, ',', '.') ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l['commission_amount']): ?>
                            R$ <?= number_format((float) $l['commission_amount'], 2, ',', '.') ?>
                            <br><small class="hint-text"><?= $l['commission_status'] === 'pago' ? 'Pago' : 'Pendente' ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$leads): ?>
                <tr><td colspan="7">Ninguém clicou no seu link ainda. Compartilhe pra começar a ver resultado aqui.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
