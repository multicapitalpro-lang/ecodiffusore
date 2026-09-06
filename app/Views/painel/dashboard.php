<?php
use App\Core\Roles;
use App\Core\View;
$role = $user['role_slug'] ?? '';
$hasMetrics = isset($metrics);
?>
<h1>Bem-vindo(a), <?= View::e($user['name']) ?></h1>

<?php if ($hasMetrics): ?>
    <form method="get" class="filter-bar">
        <label>Período: <input type="date" name="from" value="<?= View::e($from) ?>"></label>
        <label>até <input type="date" name="to" value="<?= View::e($to) ?>"></label>
        <button type="submit" class="btn btn-outline">Visualizar</button>
    </form>

    <?php if (isset($leadsNovos) || isset($pedidosPendentes) || isset($orcamentosPendentes) || !empty($aprovacoesPendentes) || !empty($contasPagarVencidas)): ?>
        <h3 class="section-title" style="margin-top:0;">Requer atenção</h3>
        <div class="cards-grid">
            <?php if (!empty($aprovacoesPendentes)): ?>
                <div class="dash-card dash-card-danger">
                    <span>Cadastros aguardando aprovação</span>
                    <strong><?= (int) $aprovacoesPendentes ?></strong>
                    <a href="/painel/licenciados/aprovacoes">Revisar agora</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($contasPagarVencidas)): ?>
                <div class="dash-card dash-card-danger">
                    <span>Contas a pagar vencidas</span>
                    <strong><?= (int) $contasPagarVencidas ?></strong>
                    <a href="/painel/financeiro/contas-a-pagar">Ver contas</a>
                </div>
            <?php endif; ?>
            <?php if (isset($pedidosPendentes)): ?>
                <div class="dash-card <?= $pedidosPendentes > 0 ? 'dash-card-danger' : '' ?>">
                    <span>Pedidos com pagamento pendente</span>
                    <strong><?= (int) $pedidosPendentes ?></strong>
                    <a href="/painel/pedidos">Ver pedidos</a>
                </div>
            <?php endif; ?>
            <?php if (isset($orcamentosPendentes)): ?>
                <div class="dash-card">
                    <span>Orçamentos pendentes</span>
                    <strong><?= (int) $orcamentosPendentes ?></strong>
                    <a href="/painel/orcamentos">Ver orçamentos</a>
                </div>
            <?php endif; ?>
            <?php if (isset($leadsNovos)): ?>
                <div class="dash-card">
                    <span>Leads novos</span>
                    <strong><?= (int) $leadsNovos ?></strong>
                    <a href="/painel/leads">Ver leads</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($followUpsPendentes)): ?>
                <div class="dash-card dash-card-danger">
                    <span>Follow-ups combinados hoje</span>
                    <strong><?= (int) $followUpsPendentes ?></strong>
                    <a href="/painel/leads">Ver leads</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($vendedoresInativos)): ?>
                <div class="dash-card dash-card-danger">
                    <span>Vendedores inativos</span>
                    <strong><?= count($vendedoresInativos) ?></strong>
                    <a href="/painel/desempenho/vendedores">Ver equipe</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="cards-grid">
        <div class="dash-card">
            <span>Valor Total</span>
            <strong>R$ <?= number_format($metrics['total_value'], 2, ',', '.') ?></strong>
            <small class="<?= $changes['total_value'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['total_value'] >= 0 ? '+' : '' ?><?= $changes['total_value'] ?>% vs. período anterior</small>
        </div>
        <div class="dash-card">
            <span>Quantidade de Pedidos</span>
            <strong><?= $metrics['order_count'] ?></strong>
            <small class="<?= $changes['order_count'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['order_count'] >= 0 ? '+' : '' ?><?= $changes['order_count'] ?>%</small>
        </div>
        <div class="dash-card">
            <span>Produtos Vendidos</span>
            <strong><?= $metrics['products_sold'] ?></strong>
            <small class="<?= $changes['products_sold'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['products_sold'] >= 0 ? '+' : '' ?><?= $changes['products_sold'] ?>%</small>
        </div>
        <div class="dash-card">
            <span>Ticket Médio</span>
            <strong>R$ <?= number_format($metrics['ticket_medio'], 2, ',', '.') ?></strong>
            <small class="<?= $changes['ticket_medio'] >= 0 ? 'text-green' : 'text-red' ?>"><?= $changes['ticket_medio'] >= 0 ? '+' : '' ?><?= $changes['ticket_medio'] ?>%</small>
        </div>
    </div>

    <?php if (isset($saldoCaixa)): ?>
        <div class="page-header">
            <h3 class="section-title" style="margin:28px 0 0;">Financeiro</h3>
            <a href="/painel/financeiro/caixas-bancos" class="link-small">Ver tudo</a>
        </div>
        <div class="cards-grid">
            <div class="dash-card">
                <span>Saldo em caixa</span>
                <strong>R$ <?= number_format($saldoCaixa, 2, ',', '.') ?></strong>
            </div>
            <div class="dash-card <?= $contasPagarVencidas > 0 ? 'dash-card-danger' : '' ?>">
                <span>Contas a pagar em aberto</span>
                <strong>R$ <?= number_format($contasPagarAberto, 2, ',', '.') ?></strong>
                <?php if ($contasPagarVencidas > 0): ?><small class="text-red"><?= (int) $contasPagarVencidas ?> vencida(s)</small><?php endif; ?>
            </div>
            <div class="dash-card">
                <span>Contas a receber em aberto</span>
                <strong>R$ <?= number_format($contasReceberAberto, 2, ',', '.') ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($impostoPagoPeriodo)): ?>
        <div class="page-header">
            <h3 class="section-title" style="margin:28px 0 0;">Fiscal e Antecipações</h3>
            <a href="/painel/financeiro/impostos" class="link-small">Ver tudo</a>
        </div>
        <div class="cards-grid">
            <div class="dash-card">
                <span>Imposto devido no período</span>
                <strong>R$ <?= number_format($impostoPagoPeriodo, 2, ',', '.') ?></strong>
            </div>
            <div class="dash-card">
                <span>Já antecipado (líquido)</span>
                <strong>R$ <?= number_format($antecipadoLiquidoTotal, 2, ',', '.') ?></strong>
            </div>
            <div class="dash-card dash-card-danger">
                <span>Taxa de antecipação paga</span>
                <strong>R$ <?= number_format($antecipacaoTaxaTotal, 2, ',', '.') ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($minhaComissaoPendente)): ?>
        <div class="page-header">
            <h3 class="section-title" style="margin:28px 0 0;">Minhas comissões</h3>
            <a href="/painel/financeiro/comissoes" class="link-small">Ver detalhes</a>
        </div>
        <div class="cards-grid">
            <div class="dash-card <?= $minhaComissaoPendente > 0 ? 'dash-card-danger' : '' ?>">
                <span>Pendente de pagamento</span>
                <strong>R$ <?= number_format($minhaComissaoPendente, 2, ',', '.') ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($goals)): ?>
        <div class="page-header">
            <h3 class="section-title" style="margin:28px 0 0;">Metas em andamento</h3>
            <a href="/painel/metas" class="link-small">Ver todas</a>
        </div>
        <div class="cards-grid">
            <?php foreach ($goals as $g): ?>
                <div class="dash-card goal-card">
                    <span><?= View::e($g['name']) ?></span>
                    <small class="hint-inline">
                        <?= $g['seller_name'] ? View::e($g['seller_name']) : 'Geral' ?>
                        <?php if ((int) $g['created_by'] !== (int) $user['id']): ?> · criada por <?= View::e($g['creator_name'] ?? '—') ?><?php endif; ?>
                    </small>
                    <div class="progress-bar"><div class="progress-fill" style="width:<?= $g['progress']['pct'] ?>%"></div></div>
                    <strong><?= $g['progress']['pct'] ?>%<?= $g['progress']['reached'] ? ' 🎉' : '' ?></strong>
                    <span class="hint-inline">R$ <?= number_format($g['progress']['achieved'], 2, ',', '.') ?> de R$ <?= number_format($g['progress']['target'], 2, ',', '.') ?></span>
                    <?php if ($g['reward_description'] || $g['reward_amount']): ?>
                        <span class="hint-inline">🏆 <?= View::e(implode(' · ', array_filter([$g['reward_description'] ?: null, $g['reward_amount'] ? 'R$ ' . number_format((float) $g['reward_amount'], 2, ',', '.') : null]))) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($licenciadosAtivos)): ?>
        <div class="page-header">
            <h3 class="section-title" style="margin:28px 0 0;">Expansão nacional</h3>
            <a href="/painel/desempenho/panorama" class="link-small">Ver mapa completo</a>
        </div>
        <div class="cards-grid">
            <div class="dash-card">
                <span>Licenciados ativos</span>
                <strong><?= (int) $licenciadosAtivos ?></strong>
            </div>
            <div class="dash-card">
                <span>Estados com cobertura</span>
                <strong><?= (int) $estadosCobertos ?> / 27</strong>
            </div>
        </div>
    <?php endif; ?>

    <h3 class="section-title">Valor total de pedidos por dia</h3>
    <div class="chart-box">
        <div style="position:relative; width:100%; height:320px;">
            <canvas id="dashboard-daily-chart" role="img" aria-label="Valor total de pedidos por dia, período atual comparado ao anterior">Gráfico de valor de pedidos por dia.</canvas>
        </div>
        <script id="dashboard-daily-chart-data" type="application/json"><?= $chartDailyJson ?></script>
        <p class="chart-legend"><span class="dot dot-current"></span> Período atual &nbsp; <span class="dot dot-previous"></span> Período anterior</p>
    </div>

    <?php if (isset($chartByState)): ?>
        <h3 class="section-title">Vendas por região (período filtrado)</h3>
        <div class="charts-grid-3">
            <div class="chart-box">
                <span class="hint-inline">Por estado</span>
                <div class="chart-bar-wrap"><?= $chartByState ?></div>
            </div>
            <div class="chart-box">
                <span class="hint-inline">Por cidade (top 8)</span>
                <div class="chart-bar-wrap"><?= $chartByCity ?></div>
            </div>
            <div class="chart-box">
                <span class="hint-inline">Por licenciado (top 8)</span>
                <div class="chart-bar-wrap"><?= $chartByLicenciado ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($chartByVendedor)): ?>
        <h3 class="section-title">Vendas por vendedor (período filtrado)</h3>
        <div class="chart-box">
            <div class="chart-bar-wrap"><?= $chartByVendedor ?></div>
        </div>
    <?php endif; ?>

    <div class="two-col">
        <div>
            <h3 class="section-title">Top produtos vendidos</h3>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Produto</th><th>Qtd.</th><th>Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($topProducts as $p): ?>
                            <tr>
                                <td><?= View::e($p['name']) ?></td>
                                <td><?= (int) $p['total_qty'] ?></td>
                                <td>R$ <?= number_format((float) $p['total_value'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$topProducts): ?>
                            <tr><td colspan="3">Nenhuma venda no período.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div>
            <h3 class="section-title">Resumo</h3>
            <table class="data-table">
                <tbody>
                    <tr><td>Faturamento</td><td>R$ <?= number_format($metrics['total_value'], 2, ',', '.') ?></td></tr>
                    <tr><td>Custo dos produtos</td><td>R$ <?= number_format($costTotal, 2, ',', '.') ?></td></tr>
                    <tr><td>Margem bruta</td><td>R$ <?= number_format($grossMargin, 2, ',', '.') ?></td></tr>
                    <tr><td>Impostos / Taxas</td><td>R$ 0,00 <span class="hint-inline">(não modelado ainda)</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($role === 'admin'): ?>
        <h3 class="section-title">Outros indicadores</h3>
        <div class="cards-grid">
            <div class="dash-card">
                <span>Leads recebidos</span>
                <strong><?= (int) ($leadCount ?? 0) ?></strong>
                <a href="/painel/leads">Ver todos</a>
            </div>
            <div class="dash-card">
                <span>Usuários</span>
                <a href="/painel/usuarios">Gerenciar usuários e papéis</a>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php $orderStatusLabels = ['em_andamento' => 'Em andamento', 'atendido' => 'Atendido', 'verificado' => 'Confirmado', 'cancelado' => 'Cancelado']; ?>
    <?php if (empty($myClient)): ?>
        <div class="cards-grid">
            <div class="dash-card dash-card-soon">
                <span>Sem cadastro vinculado</span>
                <p>Seu login ainda não está vinculado a um cadastro de cliente. Fale com quem te vendeu o produto.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="cards-grid">
            <div class="dash-card">
                <span>Total comprado</span>
                <strong>R$ <?= number_format($myTotalPurchased, 2, ',', '.') ?></strong>
            </div>
            <div class="dash-card">
                <span>Pedidos</span>
                <strong><?= count($myOrders) ?></strong>
            </div>
        </div>

        <h3 class="section-title">Meus pedidos</h3>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>#</th><th>Data</th><th>Total</th><th>Situação</th><th>Pagamento</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($myOrders as $o): ?>
                        <?php $pendingPayment = current(array_filter($o['payments'], fn ($p) => $p['status'] === 'pendente')) ?: null; ?>
                        <tr>
                            <td>#<?= (int) $o['id'] ?></td>
                            <td><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></td>
                            <td>R$ <?= number_format((float) $o['total_value'], 2, ',', '.') ?></td>
                            <td><span class="status-badge status-<?= $o['status'] === 'verificado' ? 'active' : ($o['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $orderStatusLabels[$o['status']] ?? $o['status'] ?></span></td>
                            <td>
                                <?php if ($pendingPayment): ?>
                                    <span class="status-badge status-contatado">Pendente</span>
                                <?php elseif ($o['payments']): ?>
                                    <span class="status-badge status-active">Pago</span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><a href="/painel/meus-pedidos/<?= (int) $o['id'] ?>">Ver detalhes</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$myOrders): ?>
                        <tr><td colspan="6">Nenhum pedido ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
