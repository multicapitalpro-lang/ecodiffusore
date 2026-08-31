<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
$frequencyLabels = ['diario' => 'Diário', 'semanal' => 'Semanal', 'mensal' => 'Mensal'];
$flatCatalog = [];
foreach ($catalog as $group => $reports) {
    foreach ($reports as $key => $label) {
        $flatCatalog[$key] = $group . ' — ' . $label;
    }
}
?>
<div class="page-header">
    <h1>Relatórios agendados</h1>
    <a href="/painel/financeiro/relatorios" class="btn btn-outline">← Relatórios</a>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Preencha o relatório e o destinatário.</p>
<?php endif; ?>

<p class="hint-text">Cada relatório é enviado por e-mail automaticamente, na frequência escolhida, pra quem você definir aqui.</p>

<details class="inline-details" open>
    <summary>+ Novo agendamento</summary>
    <form action="/painel/financeiro/relatorios/agendamentos" method="post" class="panel-form">
        <?= Csrf::field() ?>
        <label for="report_type">Relatório</label>
        <select id="report_type" name="report_type" required>
            <option value="">Selecione...</option>
            <?php foreach ($flatCatalog as $key => $label): ?>
                <option value="<?= $key ?>"><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="recipient_user_id">Destinatário</label>
        <select id="recipient_user_id" name="recipient_user_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($recipients as $r): ?>
                <option value="<?= (int) $r['id'] ?>"><?= View::e($r['name']) ?> (<?= View::e($r['email']) ?>)</option>
            <?php endforeach; ?>
        </select>

        <label for="frequency">Frequência</label>
        <select id="frequency" name="frequency">
            <option value="diario">Diário</option>
            <option value="semanal">Semanal</option>
            <option value="mensal" selected>Mensal</option>
        </select>

        <button type="submit" class="btn btn-primary">Agendar</button>
    </form>
</details>

<h3 class="section-title">Agendamentos ativos</h3>
<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Relatório</th><th>Destinatário</th><th>Frequência</th><th>Último envio</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($schedules as $s): ?>
                <tr>
                    <td><?= View::e($flatCatalog[$s['report_type']] ?? $s['report_type']) ?></td>
                    <td><?= View::e($s['recipient_name']) ?> <br><small><?= View::e($s['recipient_email']) ?></small></td>
                    <td><?= $frequencyLabels[$s['frequency']] ?? $s['frequency'] ?></td>
                    <td><?= $s['last_sent_at'] ? View::e(date('d/m/Y H:i', strtotime($s['last_sent_at']))) : 'Nunca' ?></td>
                    <td>
                        <form action="/painel/financeiro/relatorios/agendamentos/<?= (int) $s['id'] ?>/excluir" method="post" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="link-button">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$schedules): ?>
                <tr><td colspan="5">Nenhum agendamento criado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
