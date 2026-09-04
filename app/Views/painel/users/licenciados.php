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
        <thead><tr><th>Licenciado</th><th>E-mail</th><th>Onboarding</th><th>Supervisor responsável</th><th></th></tr></thead>
        <tbody>
            <?php
            $onboardingLabels = [
                'nao_aplicavel' => 'Não aplicável',
                'aguardando_perfil' => 'Aguardando perfil',
                'aguardando_assinatura' => 'Aguardando assinatura',
                'aguardando_aprovacao' => 'Aguardando aprovação',
                'ativo' => 'Ativo',
                'assinatura_recusada' => 'Assinatura recusada',
                'kyc_recusado' => 'KYC recusado',
            ];
            ?>
            <?php foreach ($licenciados as $l): ?>
                <tr>
                    <td><?= View::e($l['name']) ?></td>
                    <td><?= View::e($l['email']) ?></td>
                    <td><?= View::e($onboardingLabels[$l['licenciado_onboarding_status'] ?? 'nao_aplicavel'] ?? '—') ?></td>
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
                    <td><a href="/painel/licenciados/<?= (int) $l['id'] ?>/perfil">Ver cadastro</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$licenciados): ?>
                <tr><td colspan="5">Nenhum licenciado cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
