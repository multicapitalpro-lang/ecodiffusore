<?php
use App\Core\Csrf;
use App\Core\View;
$errors = $errors ?? [];
$values = $values ?? [];
$rows = $items ?: [['produto' => '', 'quantity' => 1, 'unit_price' => '']];
?>
<div class="page-header">
    <h1>📄 Proposta Comercial</h1>
</div>
<p class="section-sub">Gera um documento de vendas personalizado e com apresentação profissional (PDF e Word) pra apresentar ao cliente — preencha 1 produto (variando a quantidade) ou vários modelos de frota diferentes, o mesmo formulário serve pros dois casos.</p>

<?php if (!empty($errors['items'])): ?>
    <p class="form-msg form-msg-erro"><?= View::e($errors['items']) ?></p>
<?php endif; ?>

<form action="/painel/proposta-comercial/pdf" method="post" class="panel-form panel-form-wide" id="proposta-comercial-form">
    <?= Csrf::field() ?>

    <div class="form-grid-2">
        <div>
            <label for="client_name">Nome do Cliente</label>
            <input type="text" id="client_name" name="client_name" value="<?= View::e($values['client_name'] ?? '') ?>" required>
            <p class="field-error"><?= View::e($errors['client_name'] ?? '') ?></p>
        </div>
        <div>
            <label for="city">Cidade</label>
            <input type="text" id="city" name="city" value="<?= View::e($values['city'] ?? '') ?>">
        </div>
    </div>
    <label for="responsible">Responsável (fica em branco = seu nome)</label>
    <input type="text" id="responsible" name="responsible" value="<?= View::e($values['responsible'] ?? '') ?>" placeholder="<?= View::e($user['name']) ?>">

    <h3 class="section-title">Produtos</h3>
    <p class="hint-text" style="margin-top:0;">Pra um produto só, adicione 1 linha e mude a quantidade. Pra frota com vários modelos, adicione uma linha por equipamento.</p>
    <div class="table-scroll">
        <table class="data-table" id="items-table">
            <thead>
                <tr><th>Produto</th><th>Qtd.</th><th>Valor unit.</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody id="items-body">
                <?php foreach ($rows as $item): ?>
                <tr class="item-row">
                    <td><input type="text" name="produto[]" placeholder="Ex: Ecodiffusore — Linha Volvo" value="<?= View::e($item['produto'] ?? '') ?>" required></td>
                    <td><input type="number" name="quantidade[]" class="item-qty" min="1" value="<?= (int) ($item['quantity'] ?? 1) ?>" required></td>
                    <td><input type="number" step="0.01" name="valor_unitario[]" class="item-price" value="<?= View::e((string) ($item['unit_price'] ?? '')) ?>" required></td>
                    <td class="item-subtotal">R$ 0,00</td>
                    <td><button type="button" class="btn-remove-row" aria-label="Remover">&times;</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" id="add-item-row" class="btn btn-outline btn-sm">+ Adicionar produto</button>

    <p class="order-total">Total da proposta: <strong id="order-total">R$ 0,00</strong></p>

    <label for="payment_terms">Condições de Pagamento</label>
    <textarea id="payment_terms" name="payment_terms" placeholder="Ex: Pix à vista com 5% de desconto, ou em até 12x no cartão"><?= View::e($values['payment_terms'] ?? '') ?></textarea>

    <label for="validity">Validade da Proposta</label>
    <input type="text" id="validity" name="validity" placeholder="Ex: 10 dias" value="<?= View::e($values['validity'] ?? '') ?>">

    <label for="notes">Observações</label>
    <textarea id="notes" name="notes"><?= View::e($values['notes'] ?? '') ?></textarea>

    <div class="modal-form-actions">
        <button type="submit" formaction="/painel/proposta-comercial/pdf" class="btn btn-primary">📄 Baixar PDF</button>
        <button type="submit" formaction="/painel/proposta-comercial/docx" class="btn btn-outline">📝 Baixar Word</button>
    </div>
</form>
