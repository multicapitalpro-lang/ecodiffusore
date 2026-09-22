<?php
use App\Core\Csrf;
use App\Core\SubscriptionPlans;
use App\Models\SubscriptionPaywallHit;
use App\Core\View;

$erro = $_GET['erro'] ?? null;
$bloqueado = isset($_GET['bloqueado']);
$statusLabels = ['pendente' => 'Aguardando pagamento', 'ativa' => 'Ativa', 'expirada' => 'Expirada', 'cancelada' => 'Cancelada'];

$features = [
    '📊 Relatórios completos' => 'Vendas por vendedor, pós-venda de instalação, fiscal, financeiro — tudo que sua rede vende, num só lugar, sem precisar pedir planilha pra ninguém.',
    '💰 Financeiro completo' => 'Caixas e Bancos, Contas a Pagar e a Receber, Controle Fiscal e Antecipações — você sabe exatamente quanto entrou, quanto vai sair e quanto sobra, todo dia.',
    '💬 WhatsApp integrado' => 'Atenda seus leads e clientes direto do painel, com histórico salvo — sem depender do celular pessoal do vendedor nem correr risco de perder conversa quando alguém sai da equipe.',
    '✉️ E-mail Profissional' => 'Um e-mail com o domínio da Ecodiffusore pra sua rede (ex: comercial.suacidade@ecodiffusorebrasil.com.br) — mais credibilidade que um e-mail pessoal na hora de negociar.',
    '👥 Toda a sua equipe' => 'Seu Gestor e seus Vendedores ganham acesso às mesmas ferramentas — a assinatura é sua, mas libera pra rede toda (até ' . SubscriptionPlans::INCLUDED_SEATS . ' colaboradores inclusos).',
    '✅ Aceite digital de comissão' => 'Gestor e Vendedor recebem a proposta de comissão direto no WhatsApp e confirmam com um clique — tudo registrado, sem "combinado verbal" pra dar problema depois.',
];
?>
<div class="page-header">
    <h1>Assinatura</h1>
</div>

<?php if ($bloqueado): ?>
    <div class="subscription-blocked-banner">
        <strong>🔒 <?= $feature && isset(SubscriptionPaywallHit::LABELS[$feature]) ? 'Essa função é do plano assinante: ' . View::e(SubscriptionPaywallHit::LABELS[$feature]) : 'Essa ação faz parte da assinatura' ?></strong>
        <p>Sem assinatura, você continua vendo os números normalmente nas telas — só não consegue registrar, dar baixa ou baixar arquivo. Veja abaixo tudo que libera assinando, pra você e pra sua equipe inteira.</p>
    </div>
<?php elseif ($paywallHit && !$active): ?>
    <div class="subscription-blocked-banner">
        <strong>👋 <?= View::e($paywallHit['user_name']) ?> tentou usar <?= View::e(SubscriptionPaywallHit::LABELS[$paywallHit['feature']] ?? $paywallHit['feature']) ?> recentemente</strong>
        <p>Isso já está pronto pra usar assim que a assinatura for ativada — sua equipe está esperando essa ferramenta.</p>
    </div>
<?php endif; ?>

<?php if ($erro === 'indisponivel'): ?>
    <p class="form-msg form-msg-erro">Pagamento online ainda está sendo configurado — fale com o suporte pra assinar por enquanto.</p>
<?php elseif ($erro === 'limite'): ?>
    <p class="form-msg form-msg-erro">Sua equipe já está no limite de colaboradores da assinatura. Compre uma vaga extra abaixo pra cadastrar mais um Gestor ou Vendedor.</p>
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
<?php else: ?>
    <div class="subscription-hero">
        <p><strong>Sem assinatura, sua operação fica no escuro:</strong> você não sabe quanto realmente sobra no fim do mês, não tem histórico de conversa com o cliente se o vendedor sair, e a comissão do time fica só no "combinado verbal". Isso custa mais caro do que os R$ <?= number_format(SubscriptionPlans::PRICES['mensal'], 2, ',', '.') ?>/mês da assinatura — geralmente custa uma venda perdida por falta de controle, ou uma comissão discutida depois.</p>
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
    <p class="hint-text" style="margin-top:-10px;">Todo plano já inclui você + até <strong><?= SubscriptionPlans::INCLUDED_SEATS ?> colaboradores</strong> (Gestor e Vendedor, somados). Precisa de mais gente na equipe? Compre vagas extras abaixo.</p>

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

    <h3 class="section-title" style="margin-top:28px;">Colaboradores</h3>
    <?php $seatLimit = $includedSeats + $extraSeats; $seatPct = $seatLimit > 0 ? min(100, round($seatCount / $seatLimit * 100)) : 0; ?>
    <div class="dash-card" style="text-align:left;max-width:520px;">
        <strong><?= $seatCount ?> de <?= $seatLimit ?> vaga(s) em uso</strong>
        <div class="progress-bar" style="margin-top:8px;"><div class="progress-fill" style="width:<?= $seatPct ?>%"></div></div>
        <span class="hint-inline"><?= $includedSeats ?> incluída(s) na assinatura<?= $extraSeats > 0 ? " + {$extraSeats} extra(s) comprada(s)" : '' ?>. Gestor e Vendedor contam como vaga — Licenciado não conta.</span>
    </div>

    <details class="settings-card" style="max-width:480px;margin-top:16px">
        <summary style="cursor:pointer;font-weight:600">+ Comprar vaga extra (R$ <?= number_format(SubscriptionPlans::SEAT_PRICES['mensal'], 2, ',', '.') ?>/mês por colaborador)</summary>
        <form method="post" action="/painel/assinatura/vagas/comprar" class="panel-form panel-form-wide" style="margin-top:12px">
            <?= Csrf::field() ?>
            <label for="seat-quantity">Quantas vagas extras?</label>
            <input type="number" id="seat-quantity" name="quantity" min="1" value="1" required>
            <label for="seat-plan">Período</label>
            <select id="seat-plan" name="plan">
                <?php foreach (SubscriptionPlans::SEAT_PRICES as $plan => $price): ?>
                    <?php $discount = SubscriptionPlans::seatDiscountPct($plan); ?>
                    <option value="<?= $plan ?>"><?= SubscriptionPlans::LABELS[$plan] ?> — R$ <?= number_format($price, 2, ',', '.') ?> por vaga<?= $discount > 0 ? " ({$discount}% off)" : '' ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Comprar</button>
        </form>
    </details>

    <?php if ($seatHistory): ?>
        <div class="table-scroll" style="margin-top:16px;">
            <table class="data-table">
                <thead><tr><th>Vagas</th><th>Período</th><th>Valor</th><th>Situação</th><th>Válida até</th></tr></thead>
                <tbody>
                    <?php foreach ($seatHistory as $s): ?>
                        <tr>
                            <td><?= (int) $s['quantity'] ?></td>
                            <td><?= SubscriptionPlans::LABELS[$s['plan']] ?? $s['plan'] ?></td>
                            <td>R$ <?= number_format((float) $s['amount'], 2, ',', '.') ?></td>
                            <td><?= $s['status'] === 'ativa' ? 'Ativa' : 'Aguardando pagamento' ?></td>
                            <td><?= $s['expires_at'] ? date('d/m/Y', strtotime($s['expires_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
