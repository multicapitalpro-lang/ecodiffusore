<?php
use App\Core\Csrf;
use App\Core\Roles;
use App\Core\View;
$statusLabels = [
    'em_andamento' => 'Em andamento',
    'atendido' => 'Atendido',
    'verificado' => 'Verificado',
    'cancelado' => 'Cancelado',
];
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
$isViewOnly = in_array($user['role_slug'] ?? '', Roles::NATIONAL_SUPPORT, true);
$isModal = $isModal ?? false;
$situation = $order['payment_situation'] ?? ['label' => '—', 'badge' => 'novo'];
?>
<?php if ($isModal): ?>
    <div class="modal-header">
        <h2>Pedido #<?= (int) $order['id'] ?></h2>
        <button type="button" class="modal-close" data-modal-close aria-label="Fechar">&times;</button>
    </div>
    <div class="modal-body">
<?php else: ?>
    <div class="page-header">
        <h1>Pedido #<?= (int) $order['id'] ?></h1>
        <?php if ($order['status'] === 'em_andamento' && !$isViewOnly): ?>
            <a href="/painel/pedidos/<?= (int) $order['id'] ?>/editar" class="btn btn-outline">Editar</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['acesso_criado'])): ?>
    <p class="form-msg form-msg-ok">Pedido registrado! Conta do portal criada automaticamente pro cliente — já mandamos as credenciais por e-mail/WhatsApp. Senha temporária (caso precise repassar): <strong><?= View::e($_GET['temp'] ?? '') ?></strong></p>
<?php elseif ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Só é possível editar pedidos em andamento.</p>
<?php elseif (isset($_GET['erro_documentos'])): ?>
    <p class="form-msg form-msg-erro">Anexe a CNH e o documento do veículo, ou peça pro cliente enviar no painel dele — a fábrica precisa do documento do veículo pra montar o pedido certo.</p>
<?php endif; ?>

<?php if (!empty($order['public_token'])): ?>
    <?php $publicLink = 'https://ecodiffusorebrasil.com.br/pedido/' . $order['public_token']; ?>
    <div class="dash-card" style="max-width:640px;margin-bottom:16px;">
        <span>Link do pedido pro cliente</span>
        <p class="hint-text" style="margin:6px 0 12px;">Mande esse link direto pro comprador, sem precisar de login: ele ve o resumo, aceita os Termos de Compra, escolhe a forma de pagamento e paga. Depois de pago, e' onde ele envia CNH/documento do veiculo/fotos/telemetria.</p>
        <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <input type="text" id="order-public-link" readonly value="<?= View::e($publicLink) ?>" style="flex:1; min-width:220px; font-size:.82rem; padding:8px 10px; border-radius:6px; border:1px solid var(--border);">
            <button type="button" class="btn btn-outline btn-sm" id="order-public-link-copy">Copiar link</button>
            <a href="https://wa.me/?text=<?= rawurlencode('Ola, ' . $order['client_name'] . '! Segue o link do seu pedido Ecodiffusore pra confirmar e pagar: ' . $publicLink) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Enviar por WhatsApp</a>
        </div>
    </div>
    <script>
    document.getElementById('order-public-link-copy')?.addEventListener('click', function () {
        var input = document.getElementById('order-public-link');
        input.select();
        navigator.clipboard?.writeText(input.value);
        this.textContent = 'Copiado!';
        setTimeout(() => { this.textContent = 'Copiar link'; }, 2000);
    });
    </script>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($order['client_name']) ?></p>
    <?php if (!empty($order['client_whatsapp'])): ?>
        <p><strong>Telefone:</strong> <a href="https://wa.me/55<?= preg_replace('/\D/', '', $order['client_whatsapp']) ?>" target="_blank" rel="noopener">💬 <?= View::e($order['client_whatsapp']) ?></a></p>
    <?php endif; ?>
    <p><strong>Cidade:</strong> <?= View::e($order['client_city'] ?: '—') ?><?= $order['client_state'] ? '/' . View::e($order['client_state']) : '' ?></p>
    <p><strong>Vendedor:</strong> <?= View::e($order['seller_name'] ?: 'Sem vendedor') ?>
        <?php if (!empty($order['is_cost_price'])): ?><span class="status-badge status-novo">🏷️ Preço de custo (mostruário) — sem comissão</span><?php endif; ?>
    </p>
    <?php if (!empty($order['licenciado_name'])): ?>
        <p><strong>Licenciado:</strong> <?= View::e($order['licenciado_name']) ?></p>
    <?php endif; ?>
    <?php if (!empty($order['influencer_name'])): ?>
        <p><strong>Origem:</strong> venda indicada pelo influenciador <?= View::e($order['influencer_name']) ?> — a comissão dele nesta venda é descontada da comissão do Licenciado (ver Comissões).</p>
    <?php endif; ?>
    <p><strong>Data:</strong> <?= View::e(date('d/m/Y', strtotime($order['order_date']))) ?></p>
    <p><strong>Situação do pedido:</strong> <span class="status-badge status-<?= $order['status'] === 'verificado' ? 'active' : ($order['status'] === 'cancelado' ? 'inactive' : 'novo') ?>"><?= $statusLabels[$order['status']] ?? $order['status'] ?></span></p>
    <p><strong>Situação do pagamento:</strong> <span class="status-badge status-<?= View::e($situation['badge']) ?>"><?= View::e($situation['label']) ?></span></p>
    <?php if (!empty($order['vehicle_type']) || !empty($order['vehicle_plate'])): ?>
        <p><strong>Veículo:</strong> <?= View::e($order['vehicle_type'] ?: '—') ?><?= $order['vehicle_plate'] ? ' · Placa ' . View::e($order['vehicle_plate']) : '' ?></p>
    <?php endif; ?>
    <?php if (!empty($order['vehicle_document_path'])): ?>
        <p><a href="/painel/pedidos/<?= (int) $order['id'] ?>/documento-veiculo" target="_blank" rel="noopener" class="link-small">📄 Ver documento do veículo</a></p>
    <?php endif; ?>
    <?php if (!empty($order['cnh_document_path'])): ?>
        <p><a href="/painel/pedidos/<?= (int) $order['id'] ?>/cnh" target="_blank" rel="noopener" class="link-small">📄 Ver CNH do comprador</a></p>
    <?php endif; ?>
    <?php foreach (['photo1_path' => 'Foto 1 do veículo', 'photo2_path' => 'Foto 2 do veículo', 'photo3_path' => 'Foto 3 do veículo', 'telemetry_path' => 'Telemetria'] as $field => $label): ?>
        <?php if (!empty($order[$field])): ?>
            <p><a href="/painel/pedidos/<?= (int) $order['id'] ?>/arquivo/<?= $field ?>" target="_blank" rel="noopener" class="link-small">📄 Ver <?= $label ?></a></p>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php $missing = \App\Models\Order::missingDocumentLabels($order); ?>
    <?php if ($missing): ?>
        <p class="hint-inline" style="color:#b3790f;">⚠️ Falta <?= View::e(implode(', ', $missing)) ?> — obrigatório antes de enviar pra fabricação (a fábrica só vê pedidos pagos com o cadastro do veículo completo). O cliente pode enviar direto pelo painel dele, em "Meus Pedidos".<?php if (!$isViewOnly && $order['status'] === 'em_andamento'): ?> <a href="/painel/pedidos/<?= (int) $order['id'] ?>/editar" class="link-small">Anexar agora</a><?php endif; ?></p>
    <?php endif; ?>
    <?php if ($order['notes']): ?><p><strong>Obs.:</strong> <?= nl2br(View::e($order['notes'])) ?></p><?php endif; ?>
</div>

<?php if (!empty($order['is_cost_price']) && ($user['role_slug'] ?? '') === 'admin'): ?>
    <div class="dash-card <?= empty($order['cost_price_billing_name']) || empty($order['cost_price_delivery_street']) ? 'dash-card-danger' : '' ?>" style="text-align:left;max-width:560px;margin-bottom:16px;">
        <span>Faturamento e entrega (pedido a preço de custo)</span>
        <p class="hint-text" style="margin:4px 0 10px;">Pra quem a fábrica deve emitir a nota fiscal e pra onde entregar — aparece pra ela em "Pedidos pra Despachar".</p>
        <form action="/painel/pedidos/<?= (int) $order['id'] ?>/faturamento-custo" method="post" class="inline-form" style="display:flex;flex-direction:column;gap:8px;">
            <?= Csrf::field() ?>
            <label>Faturar para (nome/razão social)
                <input type="text" name="cost_price_billing_name" value="<?= View::e($order['cost_price_billing_name'] ?? '') ?>">
            </label>
            <label>CNPJ/CPF pra nota fiscal
                <input type="text" name="cost_price_billing_document" value="<?= View::e($order['cost_price_billing_document'] ?? '') ?>">
            </label>
            <div class="form-grid-2">
                <label>CEP
                    <input type="text" name="cost_price_delivery_zip_code" value="<?= View::e($order['cost_price_delivery_zip_code'] ?? '') ?>">
                </label>
                <label>Rua
                    <input type="text" name="cost_price_delivery_street" value="<?= View::e($order['cost_price_delivery_street'] ?? '') ?>">
                </label>
                <label>Número
                    <input type="text" name="cost_price_delivery_number" value="<?= View::e($order['cost_price_delivery_number'] ?? '') ?>">
                </label>
                <label>Complemento (opcional)
                    <input type="text" name="cost_price_delivery_complement" value="<?= View::e($order['cost_price_delivery_complement'] ?? '') ?>">
                </label>
                <label>Bairro
                    <input type="text" name="cost_price_delivery_neighborhood" value="<?= View::e($order['cost_price_delivery_neighborhood'] ?? '') ?>">
                </label>
                <label>Cidade
                    <input type="text" name="cost_price_delivery_city" value="<?= View::e($order['cost_price_delivery_city'] ?? '') ?>">
                </label>
                <label>UF
                    <input type="text" name="cost_price_delivery_state" maxlength="2" style="text-transform:uppercase" value="<?= View::e($order['cost_price_delivery_state'] ?? '') ?>">
                </label>
            </div>
            <button type="submit" class="btn btn-primary" style="align-self:flex-start;">Salvar</button>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($order['is_cost_price']) && $order['status'] === 'verificado' && ($user['role_slug'] ?? '') === 'admin'): ?>
    <div class="dash-card <?= empty($order['factory_payment_proof_path']) ? 'dash-card-danger' : '' ?>" style="text-align:left;max-width:520px;margin-bottom:16px;">
        <?php if (empty($order['factory_payment_proof_path'])): ?>
            <span>Pagamento à fábrica</span>
            <strong style="font-size:1rem;">Comprovante ainda não anexado</strong>
            <p class="hint-text" style="margin:6px 0 10px;">A fábrica só vê esse pedido na fila de despacho depois que o comprovante do Pix pra ela for anexado aqui.</p>
            <form action="/painel/pedidos/<?= (int) $order['id'] ?>/comprovante-fabrica" method="post" enctype="multipart/form-data" class="inline-form" style="display:flex;flex-direction:column;gap:8px;">
                <?= Csrf::field() ?>
                <label>Valor pago à fábrica (R$)
                    <input type="number" step="0.01" name="factory_payment_amount" required>
                </label>
                <label class="file-drop" data-file-drop>
                    <span data-file-drop-label>Solte o comprovante do Pix aqui ou clique para adicionar (PDF, JPG, PNG — até 5MB)</span>
                    <input type="file" name="factory_payment_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                </label>
                <ul class="file-list" data-file-list></ul>
                <button type="submit" class="btn btn-primary" style="align-self:flex-start;">Anexar comprovante</button>
            </form>
        <?php else: ?>
            <span>Pagamento à fábrica</span>
            <strong style="font-size:1rem;">✅ R$ <?= number_format((float) $order['factory_payment_amount'], 2, ',', '.') ?> enviado em <?= View::e(date('d/m/Y', strtotime($order['factory_payment_sent_at']))) ?></strong>
            <p style="margin-top:6px;"><a href="/painel/pedidos/<?= (int) $order['id'] ?>/comprovante-fabrica" target="_blank" rel="noopener" class="link-small">📄 Ver comprovante</a></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $canApproveDocs = in_array($user['role_slug'] ?? '', ['supervisor', 'gerente', 'admin'], true); ?>
<?php if (empty($order['is_cost_price']) && $order['status'] === 'verificado' && !\App\Models\Order::missingDocumentLabels($order) && $canApproveDocs): ?>
    <div class="dash-card" style="text-align:left;max-width:520px;margin-bottom:16px;">
        <?php if (empty($order['documents_approved_at'])): ?>
            <span>Documentos do veículo</span>
            <strong style="font-size:1rem;">Enviados pelo cliente — aguardando aprovação</strong>
            <p class="hint-text" style="margin:6px 0 10px;">Confira CNH/documento/fotos/telemetria acima antes de aprovar. Só depois disso o pedido entra na fila de despacho da fábrica.</p>
            <form action="/painel/pedidos/<?= (int) $order['id'] ?>/aprovar-documentos" method="post" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary" style="align-self:flex-start;">✅ Aprovar e liberar pra fábrica</button>
            </form>
        <?php else: ?>
            <span>Documentos do veículo</span>
            <strong style="font-size:1rem;">✅ Aprovados em <?= View::e(date('d/m/Y', strtotime($order['documents_approved_at']))) ?></strong>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$isViewOnly): ?>
<form action="/painel/pedidos/<?= (int) $order['id'] ?>/rastreio" method="post" class="inline-form" style="margin-bottom:16px">
    <?= Csrf::field() ?>
    <label>Transportadora <input type="text" name="tracking_carrier" value="<?= View::e($order['tracking_carrier'] ?? '') ?>" placeholder="Ex: Correios, Jadlog"></label>
    <label>Código de rastreio <input type="text" name="tracking_code" value="<?= View::e($order['tracking_code'] ?? '') ?>"></label>
    <label>Previsão de entrega <input type="date" name="prazo_entrega" value="<?= View::e($order['prazo_entrega'] ?? '') ?>"></label>
    <button type="submit" class="btn btn-outline">Salvar rastreio</button>
</form>
<?php endif; ?>
<?php if (!empty($order['tracking_status'])): ?>
    <p><strong>Status (Correios):</strong> <?= View::e($order['tracking_status']) ?><?php if (!empty($order['tracking_status_date'])): ?> <small class="hint-text">(em <?= View::e(date('d/m/Y H:i', strtotime($order['tracking_status_date']))) ?>)</small><?php endif; ?></p>
<?php endif; ?>

<?php include __DIR__ . '/../_approval_banner.php'; ?>

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

<?php
$allowGenerateCharge = $order['status'] !== 'cancelado' && $order['status'] !== 'verificado' && !$isViewOnly;
if ($payments || $allowGenerateCharge):
    $chargeAction = "/painel/pedidos/{$order['id']}/cobranca";
    $basePrice = (float) $order['total_value'];
    // Fase 62: enquanto o cliente nao aceitar os Termos de Compra no painel dele, nem o STAFF ve
    // o link direto de pagamento aqui -- senao o vendedor podia so copiar o link e mandar por
    // fora, pulando o aceite (o motivo inteiro do gate deixaria de valer).
    $termsPending = empty($order['terms_accepted_at']);
    include __DIR__ . '/../_payments_section.php';
endif;
?>

<?php if ($payments && $situation['slug'] === 'pago' && !$isViewOnly): ?>
    <form action="/painel/pedidos/<?= (int) $order['id'] ?>/reembolsar" method="post" class="inline-form" onsubmit="return confirm('Marcar o pagamento deste pedido como reembolsado?');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-outline">Marcar pagamento como Reembolsado</button>
    </form>
<?php endif; ?>

<?php if ($order['status'] !== 'cancelado' && $order['status'] !== 'verificado' && !$isViewOnly): ?>
<div class="order-actions">
    <?php if ($order['status'] === 'em_andamento'): ?>
        <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="status" value="atendido">
            <button type="submit" class="btn btn-outline">Marcar como Atendido</button>
        </form>
    <?php endif; ?>
    <?php if (!$approval): ?>
        <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="status" value="verificado">
            <button type="submit" class="btn btn-primary">Marcar como Verificado</button>
        </form>
    <?php endif; ?>
    <form action="/painel/pedidos/<?= (int) $order['id'] ?>/status" method="post" class="inline-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="status" value="cancelado">
        <button type="submit" class="btn btn-danger">Cancelar Pedido</button>
    </form>
</div>
<p class="hint-text">"Verificado" confirma o recebimento: gera a comissão do vendedor e o lançamento em Contas a Receber automaticamente.</p>
<?php endif; ?>
<?php if ($isModal): ?></div><?php endif; ?>
