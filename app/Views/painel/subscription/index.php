<?php
use App\Core\Csrf;
use App\Core\SubscriptionPlans;
use App\Core\View;

$erro = $_GET['erro'] ?? null;
$bloqueado = isset($_GET['bloqueado']);
$statusLabels = ['pendente' => 'Aguardando pagamento', 'ativa' => 'Ativa', 'expirada' => 'Expirada', 'cancelada' => 'Cancelada'];

$features = [
    '📊 Relatórios completos' => 'Vendas por vendedor, garantias, fiscal, financeiro — tudo que sua rede vende, num só lugar.',
    '💰 Financeiro completo' => 'Caixas e Bancos, Contas a Pagar e a Receber, Controle Fiscal e Antecipações — controle total do dinheiro da sua operação.',
    '👥 Toda a sua equipe' => 'Seu Gestor e seus Vendedores ganham acesso às mesmas ferramentas — a assinatura é sua, mas libera pra rede toda.',
    '✅ Aceite digital de comissão' => 'Gestor e Vendedor recebem a proposta de comissão direto no WhatsApp e confirmam com um clique — tudo registrado no sistema.',
];
?>
<div class="page-header">
    <h1>Assinatura</h1>
</div>

<?php if ($bloqueado): ?>
    <p class="form-msg form-msg-erro">Essa ação faz parte da assinatura — veja abaixo o que você ganha. Enquanto isso, você pode continuar navegando e vendo os números das telas normalmente.</p>
<?php endif; ?>
<?php if ($erro === 'indisponivel'): ?>
    <p class="form-msg form-msg-erro">Pagamento online ainda está sendo configurado — fale com o suporte pra assinar por enquanto.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir. Tente novamente.</p>
<?php endif; ?>

<?php if (!$isLicenciado): ?>
    <p class="hint-text" style="margin-top:0;">
        <?php if ($licenciadoName): ?>
            Essas ferramentas fazem parte da assinatura do seu Licenciado, <strong><?= View::e($licenciadoName) ?></strong>. Peça pra ele assinar em nome da rede — assim você e o resto da equipe ganham acesso automaticamente.
        <?php else: ?>
            Essas ferramentas fazem parte da assinatura do Licenciado da sua rede.
        <?php endif; ?>
    </p>
<?php elseif ($active): ?>
    <div class="cards-grid" style="margin-bottom:20px;">
        <div class="dash-card">
            <span>✅ Assinatura ativa</span>
            <strong><?= SubscriptionPlans::LABELS[$active['plan']] ?? $active['plan'] ?></strong>
            <span class="hint-inline">Válida até <?= date('d/m/Y', strtotime($active['expires_at'])) ?></span>
        </div>
    </div>
<?php endif; ?>

<h3 class="section-title">O que você (e sua equipe) ganham</h3>
<div class="plan-features">
    <?php foreach ($features as $title => $desc): ?>
        <div class="plan-feature">
            <strong><?= View::e($title) ?></strong>
            <span><?= View::e($desc) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($isLicenciado): ?>
    <h3 class="section-title">Planos</h3>
    <div class="plan-cards">
        <?php foreach (SubscriptionPlans::PRICES as $plan => $price): ?>
            <?php $discount = SubscriptionPlans::discountPct($plan); ?>
            <div class="plan-card <?= $plan === 'anual' ? 'plan-card-highlight' : '' ?>">
                <?php if ($discount > 0): ?><span class="plan-card-badge"><?= $discount ?>% OFF</span><?php endif; ?>
                <span class="plan-card-name"><?= SubscriptionPlans::LABELS[$plan] ?></span>
                <span class="plan-card-price">R$ <?= number_format($price, 2, ',', '.') ?></span>
                <span class="plan-card-sub"><?= $plan === 'mensal' ? 'por mês' : 'R$ ' . number_format(SubscriptionPlans::monthlyEquivalent($plan), 2, ',', '.') . '/mês' ?></span>
                <form method="post" action="/painel/assinatura/comprar">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="plan" value="<?= $plan ?>">
                    <button type="submit" class="btn btn-primary" style="width:100%;">Assinar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($history): ?>
        <h3 class="section-title">Histórico</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Plano</th><th>Valor</th><th>Situação</th><th>Válida até</th><th>Criada em</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= SubscriptionPlans::LABELS[$h['plan']] ?? $h['plan'] ?></td>
                            <td>R$ <?= number_format((float) $h['amount'], 2, ',', '.') ?></td>
                            <td><?= $statusLabels[$h['status']] ?? $h['status'] ?></td>
                            <td><?= $h['expires_at'] ? date('d/m/Y', strtotime($h['expires_at'])) : '—' ?></td>
                            <td><?= date('d/m/Y', strtotime($h['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
