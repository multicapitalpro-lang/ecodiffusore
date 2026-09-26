<?php
use App\Core\Csrf;
use App\Core\SubscriptionPlans;
use App\Core\View;
/** @var array $user */
/** @var bool $hasAccess */
/** @var array|null $fiscalData */
/** @var int $balance */
/** @var array $purchases */
/** @var array $packages Fase 138: quantidade => preco */
$f = fn (string $k) => View::e((string) ($fiscalData[$k] ?? ''));
$statusLabels = ['pendente' => 'Aguardando pagamento', 'ativa' => 'Confirmado'];
?>
<div class="page-header">
    <h1>Nota Fiscal Automática</h1>
</div>

<?php if (!$hasAccess): ?>
    <div class="subscription-blocked-banner">
        <strong>🔒 Essa função é do plano assinante: Nota Fiscal Automática</strong>
        <p>Você pode ver e preencher seus dados fiscais agora, mas comprar crédito e emitir nota exige assinatura ativa. <a href="/painel/assinatura">Ver planos</a>.</p>
    </div>
<?php endif; ?>

<?php if ($sucesso === 'dados'): ?>
    <p class="form-msg form-msg-ok">Dados fiscais salvos.</p>
<?php elseif ($erro === 'dados'): ?>
    <p class="form-msg form-msg-erro">Preencha ao menos a razão social e um CNPJ válido (14 dígitos).</p>
<?php elseif ($erro === 'indisponivel'): ?>
    <p class="form-msg form-msg-erro">Pagamento online ainda está sendo configurado — tente novamente em instantes.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível concluir. Tente novamente.</p>
<?php endif; ?>

<p class="hint-text" style="margin-top:-6px; max-width:760px;">
    Para receber sua comissão da Ecodiffusore/Diferencial, é preciso emitir uma nota fiscal referente a ela. Preencha aqui os dados da sua empresa e mantenha um saldo de crédito — assim que a emissão automática entrar no ar, ela sai sozinha usando esses dados, sem você precisar fazer nada manualmente a cada pedido.
</p>

<h3 class="section-title">1. Dados da sua empresa</h3>
<form method="post" action="/painel/nota-fiscal/dados" class="panel-form panel-form-wide" style="max-width:720px;">
    <?= Csrf::field() ?>
    <div class="form-grid-2">
        <div>
            <label for="razao_social">Razão social</label>
            <input type="text" id="razao_social" name="razao_social" value="<?= $f('razao_social') ?>" required>
        </div>
        <div>
            <label for="cnpj">CNPJ</label>
            <input type="text" id="cnpj" name="cnpj" value="<?= $f('cnpj') ?>" placeholder="00.000.000/0000-00" required>
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="inscricao_municipal">Inscrição municipal (se tiver)</label>
            <input type="text" id="inscricao_municipal" name="inscricao_municipal" value="<?= $f('inscricao_municipal') ?>">
        </div>
        <div>
            <label for="email_nota">E-mail pra receber a nota</label>
            <input type="email" id="email_nota" name="email_nota" value="<?= $f('email_nota') ?>">
        </div>
    </div>
    <h4 style="margin:16px 0 8px; font-size:.88rem; color:var(--gray-text);">Endereço da empresa</h4>
    <div class="form-grid-2">
        <div>
            <label for="endereco_cep">CEP</label>
            <input type="text" id="endereco_cep" name="endereco_cep" value="<?= $f('endereco_cep') ?>">
        </div>
        <div>
            <label for="endereco_logradouro">Rua/Av.</label>
            <input type="text" id="endereco_logradouro" name="endereco_logradouro" value="<?= $f('endereco_logradouro') ?>">
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="endereco_numero">Número</label>
            <input type="text" id="endereco_numero" name="endereco_numero" value="<?= $f('endereco_numero') ?>">
        </div>
        <div>
            <label for="endereco_complemento">Complemento</label>
            <input type="text" id="endereco_complemento" name="endereco_complemento" value="<?= $f('endereco_complemento') ?>">
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="endereco_bairro">Bairro</label>
            <input type="text" id="endereco_bairro" name="endereco_bairro" value="<?= $f('endereco_bairro') ?>">
        </div>
        <div>
            <label for="endereco_cidade">Cidade</label>
            <input type="text" id="endereco_cidade" name="endereco_cidade" value="<?= $f('endereco_cidade') ?>">
        </div>
    </div>
    <div class="form-grid-2">
        <div>
            <label for="endereco_uf">UF</label>
            <input type="text" id="endereco_uf" name="endereco_uf" value="<?= $f('endereco_uf') ?>" maxlength="2" style="max-width:80px;">
        </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:12px;">Salvar dados fiscais</button>
</form>

<h3 class="section-title">2. Crédito de notas</h3>
<div class="cards-grid" style="margin-bottom:16px;">
    <div class="dash-card">
        <span>Saldo disponível</span>
        <strong><?= (int) $balance ?> nota(s)</strong>
        <span class="hint-inline">Descontado automaticamente conforme as notas forem emitidas</span>
    </div>
</div>

<p class="hint-text" style="margin-top:-6px;">Cada nota custa R$ 3,00 avulsa — nos pacotes abaixo sai mais barato.</p>
<div class="proposta-tiers" style="max-width:760px;">
    <?php foreach ($packages as $quantity => $price): ?>
        <div class="proposta-tier">
            <strong><?= (int) $quantity ?> notas</strong>
            <p>R$ <?= number_format($price, 2, ',', '.') ?></p>
            <span class="hint-inline">R$ <?= number_format($price / $quantity, 2, ',', '.') ?>/nota · <?= \App\Core\SubscriptionPlans::nfeCreditDiscountPct((int) $quantity) ?>% de desconto</span>
            <?php if ($hasAccess): ?>
                <form method="post" action="/painel/nota-fiscal/creditos" style="margin-top:10px;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="quantity" value="<?= (int) $quantity ?>">
                    <button type="submit" class="btn btn-primary">Comprar</button>
                </form>
            <?php else: ?>
                <a href="/painel/assinatura" class="btn btn-outline" style="margin-top:10px;">Assine pra comprar</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($purchases): ?>
    <h3 class="section-title">Histórico de compras</h3>
    <div class="table-scroll" style="max-width:760px;">
        <table class="data-table">
            <thead><tr><th>Notas</th><th>Valor</th><th>Situação</th><th>Data</th></tr></thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= (int) $p['quantity'] ?></td>
                        <td>R$ <?= number_format((float) $p['amount'], 2, ',', '.') ?></td>
                        <td><span class="status-badge status-<?= $p['status'] === 'ativa' ? 'active' : 'novo' ?>"><?= $statusLabels[$p['status']] ?? $p['status'] ?></span></td>
                        <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
