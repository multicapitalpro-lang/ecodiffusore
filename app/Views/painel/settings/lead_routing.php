<?php
use App\Core\Csrf;
use App\Core\View;
$values = $settings;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<h1>Configurações de Roteamento de Leads</h1>
<p class="hint-text" style="margin-top:0;">Quando o cliente pede orçamento por placa no site (<code>/comprar</code>) e não existe nenhum Vendedor num raio de 100km da cidade dele, esse Lead/Orçamento precisa de um dono. Antes ficava sem responsável e aparecia pra qualquer Licenciado do país poder pegar — agora vai direto pro Licenciado Central escolhido aqui.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Salvo com sucesso.</p>
<?php endif; ?>
<?php if ($erro): ?>
    <p class="form-msg form-msg-erro">Selecione um Licenciado válido.</p>
<?php endif; ?>

<div class="proposta-installments-note">
    ⚠️ O cliente continua vendo o WhatsApp central da Ecodiffusore na tela — isso não muda. Essa
    configuração é só sobre quem fica responsável pelo Lead/Orçamento dentro do CRM, pra não
    aparecer pra todo mundo distribuir.
</div>

<form action="/painel/configuracoes/roteamento" method="post" class="panel-form-wide">
    <?= Csrf::field() ?>

    <label for="central_licenciado_id">Licenciado Central</label>
    <select id="central_licenciado_id" name="central_licenciado_id">
        <option value="">— Nenhum (mantém sem responsável, visível pra todo Licenciado) —</option>
        <?php foreach ($licenciados as $l): ?>
            <option value="<?= (int) $l['id'] ?>" <?= (int) ($values['central_licenciado_id'] ?? 0) === (int) $l['id'] ? 'selected' : '' ?>>
                <?= View::e($l['name']) ?> (<?= View::e($l['email']) ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <p class="hint-text">Sugestão: "DIFERENCIAL DISTRIBUIDORA" parece ser a conta da própria Ecodiffusore Brasil (pelo e-mail cadastrado) — mas confirme antes de escolher.</p>

    <button type="submit" class="btn btn-primary" style="margin-top:12px;">Salvar</button>
</form>
