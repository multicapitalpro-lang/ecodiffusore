<?php
use App\Core\Csrf;
use App\Core\SubscriptionPlans;
use App\Core\View;
use App\Models\User;

/** Modal reutilizavel de assinatura -- incluido em qualquer tela paga (Financeiro/Relatorios/
 *  Dashboard) pra abrir quando o usuario tenta USAR uma funcao sem assinatura (ver
 *  App\Core\SubscriptionGate). So precisa de $user (ja disponivel em toda view do painel).
 *  $openSubscriptionModal (opcional, default false): quando true, o dialog abre sozinho no
 *  carregamento da pagina (data-autoopen, ver painel.js) -- usado so' nas telas do Financeiro
 *  (Caixas e Bancos/Contas a Pagar/Contas a Receber/Relatorios), 1x por sessao, pra quem ainda
 *  nao tem assinatura ver de cara o que esta faltando, sem precisar clicar em nada primeiro. */
$modalIsLicenciado = $user['role_slug'] === 'licenciado';
$modalLicenciado = $modalIsLicenciado ? $user : User::licenciadoFor((int) $user['id']);

$modalFeatures = [
    '📊 Relatórios completos' => 'Vendas por vendedor, garantias, fiscal, financeiro.',
    '💰 Financeiro completo' => 'Caixas e Bancos, Contas a Pagar e a Receber, Controle Fiscal.',
    '💬 WhatsApp integrado' => 'Atenda direto do painel, com histórico salvo.',
    '✉️ E-mail Profissional' => 'E-mail no domínio da Ecodiffusore pra sua rede.',
    '👥 Toda a sua equipe' => 'Libera pro Gestor e Vendedores também (até 5 colaboradores inclusos).',
    '✅ Aceite digital de comissão' => 'Gestor/Vendedor confirmam a proposta pelo WhatsApp.',
];
?>
<dialog class="modal" id="modal-assinatura" <?= !empty($openSubscriptionModal) ? 'data-autoopen="1"' : '' ?>>
    <div class="modal-header">
        <h2>⭐ Assinatura</h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
        <p class="hint-text" style="margin-top:0;">Essa função faz parte da assinatura — veja o que ela libera:</p>

        <div class="plan-features">
            <?php foreach ($modalFeatures as $title => $desc): ?>
                <div class="plan-feature">
                    <strong><?= View::e($title) ?></strong>
                    <span><?= View::e($desc) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($modalIsLicenciado): ?>
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
        <?php else: ?>
            <p class="hint-text">
                <?php if ($modalLicenciado): ?>
                    Peça pro seu Licenciado, <strong><?= View::e($modalLicenciado['name']) ?></strong>, assinar — libera pra você e pro resto da equipe também.
                <?php else: ?>
                    Peça pro Licenciado da sua rede assinar.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <a href="/painel/assinatura" class="link-small">Ver todos os detalhes da assinatura</a>
    </div>
</dialog>
