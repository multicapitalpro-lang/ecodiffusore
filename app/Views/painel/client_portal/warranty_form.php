<?php
use App\Core\Csrf;
use App\Core\View;
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Solicitar Garantia — Pedido #<?= (int) $order['id'] ?></h1>
</div>

<?php if ($erro === '1'): ?>
    <p class="form-msg form-msg-error">Descreva o problema antes de enviar.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-error">Não foi possível enviar um dos anexos (verifique o tipo e o tamanho — máximo 5MB, PDF/JPG/PNG/WEBP).</p>
<?php elseif ($erro === '3'): ?>
    <p class="form-msg form-msg-error">Envie todos os documentos pedidos.</p>
<?php elseif ($erro === '4'): ?>
    <p class="form-msg form-msg-error">Informe o nome e o CPF do motorista.</p>
<?php endif; ?>

<form method="post" action="/painel/minhas-garantias" enctype="multipart/form-data" class="panel-form panel-form-wide">
    <?= Csrf::field() ?>
    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">

    <h3 class="section-title" style="margin-top:0">Dados do motorista</h3>
    <p class="hint-text" style="margin-top:0">Necessários pro Termo de Garantia — pode ser você mesmo ou o motorista responsável pelo veículo.</p>
    <div class="form-grid-2">
        <div>
            <label for="driver_name">Nome completo do motorista</label>
            <input type="text" id="driver_name" name="driver_name" required>
        </div>
        <div>
            <label for="driver_document">CPF do motorista</label>
            <input type="text" id="driver_document" name="driver_document" required>
        </div>
    </div>

    <h3 class="section-title">Documentos necessários</h3>
    <p class="hint-text" style="margin-top:0">Todos os arquivos abaixo são obrigatórios — PDF, JPG, PNG ou WEBP, até 5MB cada.</p>

    <div class="form-grid-2">
        <div>
            <label for="cnh">CNH</label>
            <input type="file" id="cnh" name="cnh" accept="application/pdf,image/jpeg,image/png,image/webp" required>
        </div>
        <div>
            <label for="documento_veiculo">Documento do veículo</label>
            <input type="file" id="documento_veiculo" name="documento_veiculo" accept="application/pdf,image/jpeg,image/png,image/webp" required>
        </div>
        <div>
            <label for="foto1">Foto do veículo (1)</label>
            <input type="file" id="foto1" name="foto1" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <div>
            <label for="foto2">Foto do veículo (2)</label>
            <input type="file" id="foto2" name="foto2" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <div>
            <label for="foto3">Foto do veículo (3)</label>
            <input type="file" id="foto3" name="foto3" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <div>
            <label for="telemetria">Telemetria/Relatório de consumo</label>
            <input type="file" id="telemetria" name="telemetria" accept="application/pdf,image/jpeg,image/png,image/webp" required>
            <p class="hint-text" style="margin-top:2px">Abastecimentos, gasto mensal, média km/l.</p>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:20px">Enviar solicitação</button>
</form>
