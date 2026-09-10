<?php
use App\Core\Csrf;
use App\Core\View;

$roleLabels = ['gestor' => 'Gestor', 'vendedor' => 'Vendedor'];
?>
<section class="buy-hero">
    <div class="site-container">
        <h1>Condições de comissão</h1>
        <p>Confirme que você está de acordo com as condições combinadas.</p>
    </div>
</section>

<section class="buy-section">
    <div class="site-container" style="max-width:520px;">
        <?php if (!$found): ?>
            <p>Link inválido ou expirado. Fale com seu Licenciado pra receber um novo.</p>
        <?php elseif ($target['commission_accepted_at']): ?>
            <p>✅ <strong><?= View::e($target['name']) ?></strong>, você já confirmou essas condições em <?= date('d/m/Y \à\s H:i', strtotime($target['commission_accepted_at'])) ?>.</p>
        <?php else: ?>
            <p>Olá, <strong><?= View::e($target['name']) ?></strong>! Você foi cadastrado(a) como <strong><?= $roleLabels[$target['role_slug']] ?? $target['role_slug'] ?></strong> no painel da Ecodiffusore Brasil. Confira suas condições de comissão:</p>

            <?php if ($target['role_slug'] === 'gestor' && $target['commission_pct']): ?>
                <div class="dash-card" style="margin:16px 0;">
                    <span>Sua comissão</span>
                    <strong><?= number_format((float) $target['commission_pct'], 2, ',', '.') ?>%</strong>
                    <span class="hint-inline">do pool de comissão que o Licenciado recebe em cada venda da sua equipe.</span>
                </div>
            <?php elseif ($tierRows): ?>
                <div class="table-scroll" style="margin:16px 0;">
                    <table class="data-table">
                        <thead><tr><th>Faixa de preço</th><th>Sua comissão</th></tr></thead>
                        <tbody>
                            <?php foreach ($tierRows as $t): ?>
                                <tr>
                                    <td>R$ <?= number_format((float) $t['min_price'], 2, ',', '.') ?><?= $t['max_price'] !== null ? ' a R$ ' . number_format((float) $t['max_price'], 2, ',', '.') : ' acima' ?></td>
                                    <td>
                                        <?php if ($t['value'] === null): ?>
                                            —
                                        <?php elseif ($target['commission_type'] === 'fixo'): ?>
                                            R$ <?= number_format((float) $t['value'], 2, ',', '.') ?> por unidade
                                        <?php else: ?>
                                            <?= number_format((float) $t['value'], 2, ',', '.') ?>% da venda
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($target['commission_pct']): ?>
                <div class="dash-card" style="margin:16px 0;">
                    <span>Sua comissão</span>
                    <strong><?= number_format((float) $target['commission_pct'], 2, ',', '.') ?>%</strong>
                </div>
            <?php endif; ?>

            <form method="post" action="/aceite-comissao/<?= View::e($token) ?>/aceitar">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary" style="width:100%;">Aceito as condições</button>
            </form>
        <?php endif; ?>
    </div>
</section>
