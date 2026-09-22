<?php
use App\Core\Csrf;
use App\Core\View;
$statusLabels = ['pendente' => 'Pendente', 'respondido' => 'Respondido'];
$statusBadge = ['pendente' => 'novo', 'respondido' => 'active'];
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Cotação de Máquina Agrícola — <?= View::e($quote['client_name']) ?></h1>
    <a href="/painel/cotacoes-maquina" class="btn btn-outline">← Cotações de Máquina Agrícola</a>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Cotação respondida com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Informe um valor válido.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível salvar, tente de novo.</p>
<?php endif; ?>

<div class="order-summary">
    <p><strong>Cliente:</strong> <?= View::e($quote['client_name']) ?></p>
    <?php if (!empty($quote['client_whatsapp'])): ?>
        <p><strong>WhatsApp:</strong> <a href="https://wa.me/55<?= preg_replace('/\D/', '', $quote['client_whatsapp']) ?>" target="_blank" rel="noopener">💬 <?= View::e($quote['client_whatsapp']) ?></a></p>
    <?php endif; ?>
    <p><strong>Cidade:</strong> <?= View::e($quote['client_city'] ?: '—') ?></p>
    <p><strong>Enviada em:</strong> <?= View::e(date('d/m/Y H:i', strtotime($quote['created_at']))) ?></p>
    <p><strong>Status:</strong> <span class="status-badge status-<?= $statusBadge[$quote['status']] ?? 'novo' ?>"><?= $statusLabels[$quote['status']] ?? $quote['status'] ?></span></p>
    <?php if (!empty($quote['assignee_name'])): ?>
        <p><strong>Responsável:</strong> <?= View::e($quote['assignee_name']) ?></p>
    <?php endif; ?>
</div>

<h3 class="section-title">Dados da máquina</h3>
<div class="order-summary">
    <p><strong>Tipo:</strong> <?= View::e($quote['machine_type']) ?></p>
    <p><strong>Marca:</strong> <?= View::e($quote['brand'] ?: '—') ?></p>
    <p><strong>Modelo:</strong> <?= View::e($quote['model'] ?: '—') ?></p>
    <p><strong>Potência:</strong> <?= View::e($quote['power'] ?: '—') ?></p>
    <p><strong>Medida da mangueira:</strong> <?= View::e($quote['hose_measure'] ?: '—') ?></p>
</div>

<h3 class="section-title">Fotos</h3>
<div class="cards-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
    <a href="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/foto/geral" target="_blank" rel="noopener">
        <img src="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/foto/geral" alt="Foto geral da máquina" style="width:100%;border-radius:8px;">
        <p class="hint-text">Foto geral</p>
    </a>
    <a href="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/foto/plaqueta" target="_blank" rel="noopener">
        <img src="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/foto/plaqueta" alt="Foto da plaqueta" style="width:100%;border-radius:8px;">
        <p class="hint-text">Plaqueta de identificação</p>
    </a>
    <a href="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/foto/mangueira" target="_blank" rel="noopener">
        <img src="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/foto/mangueira" alt="Foto da mangueira" style="width:100%;border-radius:8px;">
        <p class="hint-text">Mangueira</p>
    </a>
</div>

<?php if ($quote['status'] === 'respondido'): ?>
    <h3 class="section-title">Resposta</h3>
    <div class="order-summary">
        <p><strong>Valor cotado:</strong> R$ <?= number_format((float) $quote['quoted_price'], 2, ',', '.') ?></p>
        <?php if (!empty($quote['internal_notes'])): ?>
            <p><strong>Observações internas:</strong> <?= nl2br(View::e($quote['internal_notes'])) ?></p>
        <?php endif; ?>
        <p class="hint-text">Respondido por <?= View::e($quote['responder_name'] ?? '—') ?> em <?= $quote['responded_at'] ? View::e(date('d/m/Y H:i', strtotime($quote['responded_at']))) : '—' ?></p>
    </div>
<?php elseif ($canRespond ?? false): ?>
    <h3 class="section-title">Definir preço e responder</h3>
    <form method="post" action="/painel/cotacoes-maquina/<?= (int) $quote['id'] ?>/responder" class="panel-form">
        <?= Csrf::field() ?>
        <label for="quoted_price">Valor cotado (R$)</label>
        <input type="text" id="quoted_price" name="quoted_price" placeholder="Ex: 3200,00" required>

        <label for="internal_notes">Observações internas (opcional)</label>
        <textarea id="internal_notes" name="internal_notes" rows="3"></textarea>

        <p class="hint-text">Salvar aqui NÃO envia nada pro cliente automaticamente — é só pra registrar o valor. Retorne o orçamento pra ele por WhatsApp/telefone.</p>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar resposta</button>
    </form>
<?php else: ?>
    <h3 class="section-title">Aguardando valores</h3>
    <p class="hint-text">Essa cotação ainda não tem preço definido. Só o Gerente ou o Admin podem inserir o valor.</p>
<?php endif; ?>
