<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Pedidos pra Despachar</h1>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-error">Não foi possível atualizar.</p>
<?php endif; ?>

<div class="cards-grid">
    <div class="dash-card">
        <span>Total pago</span>
        <strong><?= $stats['total'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Pendentes de código</span>
        <strong><?= $stats['pendente_codigo'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Em rota de entrega</span>
        <strong><?= $stats['em_rota'] ?></strong>
    </div>
    <div class="dash-card">
        <span>Entregues</span>
        <strong><?= $stats['entregues'] ?></strong>
    </div>
</div>

<h3 class="section-title">Aguardando ação</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Produto(s)</th>
                <th>Veículo / Obs.</th>
                <th>Destinatário</th>
                <th>Endereço</th>
                <th>Transportadora</th>
                <th>Código</th>
                <th>Previsão</th>
                <th>Nota Fiscal</th>
                <th>Comprovante de Instalação</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <?php $formId = 'entrega-' . (int) $o['id']; ?>
                <tr>
                    <td>#<?= (int) $o['id'] ?><br><small class="hint-text"><?= View::e(date('d/m/Y', strtotime($o['order_date']))) ?></small></td>
                    <td>
                        <?php if ($o['items']): ?>
                            <?php foreach ($o['items'] as $item): ?>
                                <div><?= View::e($item['product_name']) ?> <strong>x<?= (int) $item['quantity'] ?></strong></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td style="min-width:200px">
                        <?php $v = $o['vehicle_info']; ?>
                        <?php if ($v['plate'] || $v['brand']): ?>
                            <strong><?= View::e(trim(($v['brand'] ?: '—') . ($v['model'] ? ' ' . $v['model'] : ''))) ?></strong><?= $v['plate'] ? ' · ' . View::e($v['plate']) : '' ?>
                            <details style="margin-top:4px">
                                <summary class="link-small" style="cursor:pointer">Ver detalhes do veículo</summary>
                                <div style="font-size:.78rem;color:var(--gray-text);margin-top:6px;line-height:1.6">
                                    <?php if ($v['year']): ?>Ano modelo: <?= View::e($v['year']) ?><br><?php endif; ?>
                                    <?php if ($v['power']): ?>Potência do motor: <?= View::e($v['power']) ?><br><?php endif; ?>
                                    <?php if ($v['ecu_status']): ?>
                                        Motor: <?= $v['ecu_status'] === 'original' ? 'Original de fábrica' : 'Reprogramado (chip)' ?><?php if ($v['ecu_status'] === 'reprogramado' && $v['reprogrammed_power']): ?> — <?= View::e($v['reprogrammed_power']) ?><?php endif; ?><br>
                                    <?php endif; ?>
                                    <?php if ($v['has_arla']): ?>Possui ARLA: <?= $v['has_arla'] === 'sim' ? 'Sim' : 'Não' ?><br><?php endif; ?>
                                    <?php if ($v['has_telemetry']): ?>Telemetria: <?= $v['has_telemetry'] === 'sim' ? 'Sim' : 'Não' ?><br><?php endif; ?>
                                    <?php if ($v['km_mensal']): ?>Média km rodados/mês: <?= number_format((float) $v['km_mensal'], 0, ',', '.') ?> km<br><?php endif; ?>
                                    <?php if ($v['km_litro']): ?>Média km/litro: <?= number_format((float) $v['km_litro'], 1, ',', '.') ?> km/l<br><?php endif; ?>
                                    <?php if ($v['preco_diesel']): ?>Preço médio do diesel: R$ <?= number_format((float) $v['preco_diesel'], 3, ',', '.') ?><br><?php endif; ?>
                                </div>
                            </details>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                        <?php if (!empty($o['notes'])): ?>
                            <br><small class="hint-text">📝 <?= View::e($o['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= View::e($o['client_name']) ?><br>
                        <small class="hint-text"><?= View::e($o['client_document'] ?: '—') ?><?php if (!empty($o['client_whatsapp'])): ?> · <?= View::e($o['client_whatsapp']) ?><?php endif; ?><?php if (!empty($o['client_email'])): ?> · <?= View::e($o['client_email']) ?><?php endif; ?></small>
                    </td>
                    <td style="min-width:220px">
                        <?= View::e($o['street'] ?: '—') ?><?= $o['number'] ? ', ' . View::e($o['number']) : '' ?><?= $o['complement'] ? ' - ' . View::e($o['complement']) : '' ?><br>
                        <small class="hint-text"><?= View::e($o['neighborhood'] ?: '—') ?>, <?= View::e($o['city'] ?: '—') ?>/<?= View::e($o['state'] ?: '—') ?> · CEP <?= View::e($o['zip_code'] ?: '—') ?></small>
                    </td>
                    <td><input form="<?= $formId ?>" type="text" name="tracking_carrier" value="<?= View::e($o['tracking_carrier'] ?? '') ?>" placeholder="Correios, Jadlog..." style="width:120px"></td>
                    <td>
                        <input form="<?= $formId ?>" type="text" name="tracking_code" value="<?= View::e($o['tracking_code'] ?? '') ?>" style="width:120px">
                        <?php if (!empty($o['tracking_status'])): ?>
                            <br><small class="hint-text"><?= View::e($o['tracking_status']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><input form="<?= $formId ?>" type="date" name="prazo_entrega" value="<?= View::e($o['prazo_entrega'] ?? '') ?>" style="width:140px"></td>
                    <td>
                        <?php if (!empty($o['nfe_pdf_url'])): ?>
                            <a href="<?= View::e($o['nfe_pdf_url']) ?>" target="_blank" rel="noopener" class="link-small">📄 Baixar</a>
                        <?php elseif (!empty($o['nfe_status'])): ?>
                            <span class="hint-text">Em processamento</span>
                        <?php else: ?>
                            <span class="hint-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($o['warranty_term_id'])): ?>
                            <a href="/painel/fabrica/<?= (int) $o['id'] ?>/termo-garantia" target="_blank" rel="noopener" class="link-small">📄 Baixar</a>
                        <?php else: ?>
                            <span class="hint-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button form="<?= $formId ?>" type="submit" class="btn btn-outline">Salvar</button>
                        <form method="post" action="/painel/fabrica/<?= (int) $o['id'] ?>/entregue" class="inline-form" onsubmit="return confirm('Marcar este pedido como entregue?');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="link-button">Marcar entregue</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="11">Nenhum pedido pago aguardando despacho no momento.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($orders as $o): ?>
    <form id="entrega-<?= (int) $o['id'] ?>" method="post" action="/painel/fabrica/<?= (int) $o['id'] ?>/entrega" hidden><?= Csrf::field() ?></form>
<?php endforeach; ?>
