<?php
use App\Core\Csrf;
use App\Core\View;
use App\Models\Order;
$statusLabels = ['em_andamento' => 'Em andamento', 'atendido' => 'Atendido', 'verificado' => 'Confirmado', 'cancelado' => 'Cancelado'];
$methodLabels = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão'];
?>
<div class="page-header">
    <h1>Pedido #<?= (int) $order['id'] ?></h1>
    <a href="/painel" class="btn btn-outline">← Meus pedidos</a>
</div>

<?php if (isset($_GET['docs_sucesso'])): ?>
    <p class="form-msg form-msg-ok">Documentos enviados! Assim que confirmarmos, seu pedido segue pra fabricação.</p>
<?php elseif (!empty($_GET['erro_docs'])): ?>
    <p class="form-msg form-msg-erro"><?= $_GET['erro_docs'] === '1' ? 'Sessão expirada, tente de novo.' : View::e($_GET['erro_docs']) ?></p>
<?php endif; ?>

<?php if (!Order::hasRequiredDocuments($order)): ?>
    <div class="form-msg" style="background:#fff4dc;color:#b7791f;max-width:560px;margin-bottom:18px;">
        <strong>📎 Falta enviar a CNH e o documento do veículo</strong>
        <p style="margin:6px 0 0;">Sem isso, seu pedido não segue pra fabricação — envie assim que puder.</p>
        <form action="/painel/meus-pedidos/<?= (int) $order['id'] ?>/documentos" method="post" enctype="multipart/form-data" class="panel-form" style="margin-top:12px;">
            <?= Csrf::field() ?>
            <div class="form-grid-2">
                <?php if (empty($order['vehicle_document_path'])): ?>
                    <div>
                        <label for="client-vehicle-document">Documento do veículo (CRLV)</label>
                        <input type="file" id="client-vehicle-document" name="vehicle_document" accept="image/*,.pdf">
                    </div>
                <?php endif; ?>
                <?php if (empty($order['cnh_document_path'])): ?>
                    <div>
                        <label for="client-cnh-document">CNH</label>
                        <input type="file" id="client-cnh-document" name="cnh_document" accept="image/*,.pdf">
                    </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">Enviar documentos</button>
        </form>
    </div>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y', strtotime($order['order_date']))) ?></p>
    <p><strong>Situação:</strong> <span class="status-badge status-<?= $order['status'] === 'verificado' ? 'active' : ($order['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$order['status']] ?? $order['status'] ?></span></p>
    <?php
        $isCorreios = !empty($order['tracking_carrier']) && stripos($order['tracking_carrier'], 'correios') !== false;
        $correiosUrl = 'https://rastreamento.correios.com.br/app/index.php?objetos=' . urlencode((string) ($order['tracking_code'] ?? ''));
    ?>
    <?php if (!empty($order['tracking_code']) || !empty($order['tracking_carrier'])): ?>
        <p><strong>Rastreio:</strong> <?= View::e($order['tracking_carrier'] ?: '—') ?>
            <?php if (!empty($order['tracking_code']) && $isCorreios): ?>
                — código <a href="<?= View::e($correiosUrl) ?>" target="_blank" rel="noopener"><code><?= View::e($order['tracking_code']) ?></code> ↗</a>
            <?php elseif (!empty($order['tracking_code'])): ?>
                — código <code><?= View::e($order['tracking_code']) ?></code>
            <?php endif; ?>
        </p>
        <?php if (!empty($order['tracking_status'])): ?>
            <p><strong>Status atual:</strong> <?= View::e($order['tracking_status']) ?><?php if (!empty($order['tracking_status_date'])): ?> <small class="hint-text">(em <?= View::e(date('d/m/Y H:i', strtotime($order['tracking_status_date']))) ?>)</small><?php endif; ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($order['prazo_entrega'])): ?>
        <p><strong>Previsão de entrega:</strong> <?= View::e(date('d/m/Y', strtotime($order['prazo_entrega']))) ?></p>
    <?php endif; ?>
    <?php if (!empty($order['vehicle_type']) || !empty($order['vehicle_plate'])): ?>
        <p><strong>Veículo:</strong> <?= View::e($order['vehicle_type'] ?: '—') ?><?= $order['vehicle_plate'] ? ' · Placa ' . View::e($order['vehicle_plate']) : '' ?></p>
    <?php endif; ?>
    <?php if (!empty($order['nfe_pdf_url'])): ?>
        <p><strong>Nota Fiscal:</strong> <a href="<?= View::e($order['nfe_pdf_url']) ?>" target="_blank" rel="noopener">📄 Baixar NF-e</a></p>
    <?php elseif (!empty($order['nfe_status'])): ?>
        <p><strong>Nota Fiscal:</strong> <span class="hint-text">Em processamento, disponível em breve</span></p>
    <?php endif; ?>
</div>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= View::e($item['product_name']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td>R$ <?= number_format((float) $item['unit_price'], 2, ',', '.') ?></td>
                    <td>R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" style="text-align:right"><strong>Total</strong></td><td><strong>R$ <?= number_format((float) $order['total_value'], 2, ',', '.') ?></strong></td></tr>
        </tfoot>
    </table>
</div>

<h3 class="section-title">Pagamento</h3>
<?php if (!$payments): ?>
    <p class="hint-text">Nenhuma cobrança gerada ainda para este pedido. Fale com quem te vendeu o produto.</p>
<?php endif; ?>
<?php foreach ($payments as $p): ?>
    <div class="dash-card" style="margin-bottom:14px;">
        <span><?= View::e($methodLabels[$p['method']] ?? $p['method']) ?> — R$ <?= number_format((float) $p['amount'], 2, ',', '.') ?></span>
        <?php if ($p['status'] === 'pendente'): ?>
            <strong class="text-red">Pendente</strong>
            <?php if ($p['checkout_url']): ?>
                <a href="<?= View::e($p['checkout_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary" style="margin-top:8px;width:fit-content;">Pagar agora</a>
            <?php endif; ?>
            <?php if ($p['method'] === 'PIX' && $p['pix_payload']): ?>
                <small class="hint-text">Pix copia-e-cola:</small>
                <input type="text" readonly value="<?= View::e($p['pix_payload']) ?>" style="width:100%;font-size:.75rem;padding:6px 8px;border-radius:6px;border:1px solid var(--border);margin-top:4px;" onclick="this.select()">
            <?php endif; ?>
        <?php else: ?>
            <strong class="text-green">Pago</strong>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($approvedWarranty): ?>
    <a href="/painel/minhas-garantias/<?= (int) $approvedWarranty['id'] ?>/termo" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:16px">📄 Baixar Comprovante de Instalação</a>
<?php elseif ($order['status'] === 'verificado'): ?>
    <a href="/painel/minhas-garantias/nova?order_id=<?= (int) $order['id'] ?>" class="btn btn-outline" style="margin-top:16px">⚠️ Confirmar instalação do produto (obrigatório)</a>
<?php endif; ?>
