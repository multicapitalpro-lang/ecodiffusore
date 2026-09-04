<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $payments */
/** @var string $chargeAction */
$allowGenerateCharge = $allowGenerateCharge ?? true;
$methodLabels = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CREDIT_CARD' => 'Cartão'];
$statusLabels = ['pendente' => 'Pendente', 'pago' => 'Pago', 'vencido' => 'Vencido', 'cancelado' => 'Cancelado', 'reembolsado' => 'Reembolsado'];
?>
<h3 class="section-title">Cobrança</h3>

<?php if (!empty($_GET['erro_cobranca'])): ?>
    <p class="form-msg form-msg-erro">Não foi possível gerar a cobrança: <?= View::e($_GET['erro_cobranca']) ?></p>
<?php endif; ?>

<?php if ($payments): ?>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Método</th><th>Valor</th><th>Vencimento</th><th>Situação</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= View::e($methodLabels[$p['method']] ?? $p['method']) ?></td>
                        <td>R$ <?= number_format((float) $p['amount'], 2, ',', '.') ?></td>
                        <td><?= View::e(date('d/m/Y', strtotime($p['due_date']))) ?></td>
                        <td><span class="status-badge status-<?= $p['status'] === 'pago' ? 'active' : (in_array($p['status'], ['cancelado', 'reembolsado'], true) ? 'inactive' : 'novo') ?>"><?= $statusLabels[$p['status']] ?? $p['status'] ?></span></td>
                        <td>
                            <?php if ($p['status'] === 'pendente' && $p['checkout_url']): ?>
                                <a href="<?= View::e($p['checkout_url']) ?>" target="_blank" rel="noopener">Ver cobrança</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($p['status'] === 'pendente' && $p['method'] === 'PIX' && $p['pix_payload']): ?>
                        <tr><td colspan="5">
                            <small class="hint-text">Pix copia-e-cola:</small>
                            <input type="text" readonly value="<?= View::e($p['pix_payload']) ?>" style="width:100%;font-size:.75rem;padding:6px 8px;border-radius:6px;border:1px solid var(--border);margin-top:4px;">
                        </td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="hint-text">Nenhuma cobrança gerada ainda.</p>
<?php endif; ?>

<?php if ($allowGenerateCharge): ?>
    <form action="<?= View::e($chargeAction) ?>" method="post" class="inline-form" style="margin-top:12px;">
        <?= Csrf::field() ?>
        <select name="billing_type">
            <option value="PIX">Pix</option>
            <option value="BOLETO">Boleto</option>
            <option value="CREDIT_CARD">Cartão de crédito</option>
        </select>
        <button type="submit" class="btn btn-outline">Gerar cobrança</button>
    </form>
    <p class="hint-text">O cliente precisa ter CPF/CNPJ cadastrado para gerar a cobrança.</p>
<?php endif; ?>
