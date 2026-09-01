<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<h1>Licenciados</h1>
<p class="section-sub">Defina qual Supervisor fica responsável por dar suporte a cada Licenciado.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Não foi possível atualizar.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Licenciado</th><th>E-mail</th><th>Supervisor responsável</th></tr></thead>
        <tbody>
            <?php foreach ($licenciados as $l): ?>
                <tr>
                    <td><?= View::e($l['name']) ?></td>
                    <td><?= View::e($l['email']) ?></td>
                    <td>
                        <form action="/painel/licenciados/<?= (int) $l['id'] ?>/supervisor" method="post" class="inline-form">
                            <?= Csrf::field() ?>
                            <select name="supervisor_id" onchange="this.form.requestSubmit()">
                                <option value="">— nenhum —</option>
                                <?php foreach ($supervisors as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($l['supervisor_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                        <?= View::e($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$licenciados): ?>
                <tr><td colspan="3">Nenhum licenciado cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
