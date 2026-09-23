<?php
use App\Core\View;
/** @var array $values */
/** @var array $errors */
/** @var array $items */
/** @var array $clients */
/** @var array $products */
/** @var array $pricingTiers */
/** @var array $sellers */
/** @var bool $isVendedor */
/** @var int $preselectClientId */
?>
<?php if (!empty($errors['items'])): ?>
    <p class="form-msg form-msg-erro"><?= View::e($errors['items']) ?></p>
<?php endif; ?>

<div class="form-grid-2">
    <div>
        <label for="client_id">Cliente</label>
        <select id="client_id" name="client_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($values['client_id'] ?? $preselectClientId) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= View::e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="link-small link-button" data-modal-open="modal-client-inline">+ Cadastrar novo cliente</button>
        <p class="field-error" data-error-for="client_id"><?= View::e($errors['client_id'] ?? '') ?></p>
    </div>

    <div>
        <label for="order_date">Data do pedido</label>
        <input type="date" id="order_date" name="order_date" value="<?= View::e($values['order_date'] ?? date('Y-m-d')) ?>" required>
        <p class="field-error" data-error-for="order_date"><?= View::e($errors['order_date'] ?? '') ?></p>
    </div>
</div>

<?php $isAdmin = ($user['role_slug'] ?? '') === 'admin'; $isEditingOrder = !empty($editing['id']); ?>
<?php if ($isAdmin && !$isEditingOrder): ?>
    <div style="margin:10px 0;padding:10px;border:1px dashed var(--border);border-radius:8px;">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
            <input type="checkbox" id="is_cost_price" name="is_cost_price" value="1">
            🏷️ Pedido a preço de custo (mostruário) — sem vendedor, sem comissão
        </label>
        <p class="hint-text" style="margin-top:4px;">Pra compra interna a preço de fábrica (ex: peça de mostruário). Libera o preço abaixo do piso normal e não gera comissão pra ninguém. Depois de verificado, anexe o comprovante do Pix pra fábrica no detalhe do pedido.</p>

        <div id="cost-price-fields" style="display:none;margin-top:10px;">
            <label for="cost_price_billing_name">Faturar para (nome/razão social)</label>
            <input type="text" id="cost_price_billing_name" name="cost_price_billing_name" placeholder="Pra quem a fábrica deve emitir a nota fiscal">
            <p class="field-error" data-error-for="cost_price_billing_name"><?= View::e($errors['cost_price_billing_name'] ?? '') ?></p>

            <label for="cost_price_billing_document">CNPJ/CPF pra nota fiscal</label>
            <input type="text" id="cost_price_billing_document" name="cost_price_billing_document">
            <p class="field-error" data-error-for="cost_price_billing_document"><?= View::e($errors['cost_price_billing_document'] ?? '') ?></p>

            <label for="cost_price_delivery_address">Endereço de entrega</label>
            <input type="text" id="cost_price_delivery_address" name="cost_price_delivery_address" placeholder="Rua, número, bairro, cidade/UF, CEP">
            <p class="field-error" data-error-for="cost_price_delivery_address"><?= View::e($errors['cost_price_delivery_address'] ?? '') ?></p>
        </div>
    </div>
    <script>
    (function () {
        var cb = document.getElementById('is_cost_price');
        var sellerWrap = document.getElementById('seller-field-wrap');
        var vehicleSection = document.getElementById('vehicle-section');
        var costPriceFields = document.getElementById('cost-price-fields');
        var costPriceInputs = costPriceFields ? costPriceFields.querySelectorAll('input') : [];
        if (!cb) return;
        function sync() {
            if (sellerWrap) sellerWrap.style.display = cb.checked ? 'none' : '';
            if (vehicleSection) vehicleSection.style.display = cb.checked ? 'none' : '';
            if (costPriceFields) costPriceFields.style.display = cb.checked ? '' : 'none';
            costPriceInputs.forEach(function (input) { input.required = cb.checked; });
        }
        cb.addEventListener('change', sync);
        sync();
    })();
    </script>
<?php endif; ?>

<?php if (!$isVendedor): ?>
    <div id="seller-field-wrap">
        <label for="seller_id">Vendedor</label>
        <select id="seller_id" name="seller_id">
            <option value="">Sem vendedor definido</option>
            <?php foreach ($sellers as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) ($values['seller_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= View::e($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>

<div id="vehicle-section">
<h3 class="section-title">Veículo</h3>
<div class="form-grid-2">
    <div>
        <label for="vehicle_type">Tipo de veículo</label>
        <select id="vehicle_type" name="vehicle_type">
            <option value="">Selecione...</option>
            <?php foreach (['Caminhão', 'Ônibus', 'Máquina agrícola', 'Máquina de linha amarela', 'Gerador', 'Outro'] as $tipo): ?>
                <option value="<?= View::e($tipo) ?>" <?= ($values['vehicle_type'] ?? '') === $tipo ? 'selected' : '' ?>><?= View::e($tipo) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="vehicle_plate">Placa (opcional)</label>
        <input type="text" id="vehicle_plate" name="vehicle_plate" maxlength="10" style="text-transform:uppercase" value="<?= View::e($values['vehicle_plate'] ?? '') ?>">
    </div>
</div>

<div>
    <label>Foto do documento do veículo (opcional)</label>
    <label class="file-drop" data-file-drop>
        <span data-file-drop-label>Solte o arquivo aqui ou clique para adicionar (PDF, JPG, PNG — até 5MB)</span>
        <input type="file" id="vehicle_document" name="vehicle_document" accept=".pdf,.jpg,.jpeg,.png,.webp">
    </label>
    <ul class="file-list" data-file-list></ul>
    <p class="hint-text">Não trava a compra — o próprio comprador pode enviar depois, pelo painel dele ("Meus Pedidos"), assim que pagar. Só precisa estar completo quando o pedido for liberado pra fábrica.</p>
    <p class="field-error" data-error-for="vehicle_document"><?= View::e($errors['vehicle_document'] ?? '') ?></p>
</div>

<div>
    <label>Foto da CNH do comprador (opcional)</label>
    <label class="file-drop" data-file-drop>
        <span data-file-drop-label>Solte o arquivo aqui ou clique para adicionar (PDF, JPG, PNG — até 5MB)</span>
        <input type="file" id="cnh_document" name="cnh_document" accept=".pdf,.jpg,.jpeg,.png,.webp">
    </label>
    <ul class="file-list" data-file-list></ul>
    <p class="hint-text">Mesma coisa — pode ser enviada depois pelo cliente, sem travar a compra agora.</p>
    <p class="field-error" data-error-for="cnh_document"><?= View::e($errors['cnh_document'] ?? '') ?></p>
</div>
</div>

<h3 class="section-title">Produtos</h3>
<?php if ($pricingTiers): ?>
    <?php if ($isVendedor): ?>
        <p class="hint-text" style="margin-top:0;">Preço padrão de venda: <strong>R$ <?= number_format(\App\Models\PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?></strong>. Pra vender abaixo disso, o pedido fica pendente de aprovação do seu Gestor ou Licenciado antes de poder ser verificado.</p>
    <?php else: ?>
        <p class="hint-text" style="margin-top:0;">Preço negociado livremente (piso R$ <?= number_format((float) $pricingTiers[0]['min_price'], 2, ',', '.') ?>) — a faixa de preço define a % de comissão do Licenciado: <?php foreach ($pricingTiers as $i => $t): ?><?= $i > 0 ? ' · ' : '' ?>R$ <?= number_format((float) $t['min_price'], 2, ',', '.') ?><?= $t['max_price'] !== null ? '–' . number_format((float) $t['max_price'], 2, ',', '.') : ' acima' ?> = <?= number_format((float) $t['licenciado_commission_pct'], 2, ',', '.') ?>%<?php endforeach; ?>. Vender abaixo de R$ <?= number_format(\App\Models\PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?> precisa de aprovação de Gerente, Supervisor ou Admin.</p>
    <?php endif; ?>
<?php endif; ?>
<div class="table-scroll">
    <table class="data-table" id="items-table">
        <thead>
            <tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th><th></th></tr>
        </thead>
        <tbody id="items-body">
            <?php
            $rows = $items ?: [['product_id' => '', 'quantity' => 1, 'unit_price' => '']];
            foreach ($rows as $item):
            ?>
            <tr class="item-row">
                <td>
                    <select name="product_id[]" class="item-product" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($item['product_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= View::e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="quantity[]" class="item-qty" min="1" value="<?= (int) ($item['quantity'] ?? 1) ?>" required></td>
                <td><input type="number" step="0.01" name="unit_price[]" class="item-price" value="<?= View::e((string) ($item['unit_price'] ?? '')) ?>" required></td>
                <td class="item-subtotal">R$ 0,00</td>
                <td><button type="button" class="btn-remove-row" aria-label="Remover">&times;</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<button type="button" id="add-item-row" class="btn btn-outline btn-sm">+ Adicionar produto</button>

<p class="order-total">Total do pedido: <strong id="order-total">R$ 0,00</strong></p>

<?php if ($isVendedor): ?>
    <label for="motivo_desconto">Motivo do preço abaixo do padrão (se estiver pedindo desconto)</label>
    <textarea id="motivo_desconto" name="motivo_desconto" placeholder="Explique pra quem for analisar: por que esse cliente precisa de um preço menor?"><?= View::e($values['motivo_desconto'] ?? '') ?></textarea>
    <p class="field-error" data-error-for="motivo_desconto"></p>
    <p class="hint-text" style="margin-top:0;">Só é obrigatório se o preço ficar abaixo de R$ <?= number_format(\App\Models\PricingTier::VENDOR_STANDARD_PRICE, 2, ',', '.') ?> — o Gestor/Licenciado e o Gerente vão ver esse motivo antes de decidir.</p>
<?php endif; ?>

<label for="notes">Observações</label>
<textarea id="notes" name="notes"><?= View::e($values['notes'] ?? '') ?></textarea>
