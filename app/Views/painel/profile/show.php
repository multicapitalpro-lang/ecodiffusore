<?php
use App\Core\Csrf;
use App\Core\View;
/** @var array $user */
/** @var array $events Notifier::EVENTS, agrupado por 'group' */
/** @var string[] $disabled chaves de evento desativadas */
$disabledSet = array_flip($disabled);
$groups = [];
foreach ($events as $key => $ev) {
    $groups[$ev['group']][$key] = $ev['label'];
}
?>
<div class="page-header">
    <h1>Meu Perfil</h1>
</div>

<?php if ($sucesso === 'senha'): ?>
    <p class="form-msg form-msg-ok">Senha alterada com sucesso.</p>
<?php elseif ($sucesso === 'notificacoes'): ?>
    <p class="form-msg form-msg-ok">Preferências de notificação salvas.</p>
<?php elseif ($erro === 'senha_atual'): ?>
    <p class="form-msg form-msg-erro">Senha atual incorreta.</p>
<?php elseif ($erro === 'senha_nova'): ?>
    <p class="form-msg form-msg-erro">A nova senha precisa ter pelo menos 8 caracteres e as duas devem ser iguais.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Sessão expirada, tente de novo.</p>
<?php endif; ?>

<h3 class="section-title">Dados da conta</h3>
<div class="panel-form" style="max-width:500px;">
    <p><strong>Nome:</strong> <?= View::e($user['name']) ?></p>
    <p><strong>E-mail:</strong> <?= View::e($user['email']) ?></p>
    <p><strong>Papel:</strong> <?= View::e($user['role_name']) ?></p>
</div>

<h3 class="section-title">Trocar senha</h3>
<form action="/painel/perfil/senha" method="post" class="panel-form" style="max-width:400px;">
    <?= Csrf::field() ?>
    <div>
        <label for="current_password">Senha atual</label>
        <input type="password" id="current_password" name="current_password" required>
    </div>
    <div>
        <label for="password">Nova senha</label>
        <input type="password" id="password" name="password" minlength="8" required>
    </div>
    <div>
        <label for="password_confirm">Confirmar nova senha</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:12px;">Trocar senha</button>
</form>

<h3 class="section-title">Notificações que quero receber (push no app/celular)</h3>
<p class="hint-text" style="margin-top:-6px;">Desmarcar aqui só afeta a notificação push. E-mail e WhatsApp desses mesmos eventos continuam normalmente.</p>
<form action="/painel/perfil/notificacoes" method="post" class="panel-form" style="max-width:600px;">
    <?= Csrf::field() ?>
    <?php foreach ($groups as $groupName => $groupEvents): ?>
        <h4 style="margin:18px 0 8px; font-size:.9rem; color:var(--gray-text);"><?= View::e($groupName) ?></h4>
        <?php foreach ($groupEvents as $key => $label): ?>
            <label class="checkbox-inline" style="display:block; margin-bottom:6px;">
                <input type="checkbox" name="events[<?= View::e($key) ?>]" value="1" <?= !isset($disabledSet[$key]) ? 'checked' : '' ?>>
                <?= View::e($label) ?>
            </label>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary" style="margin-top:16px;">Salvar preferências</button>
</form>
