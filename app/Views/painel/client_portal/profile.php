<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$errors = $errors ?? [];
?>
<div class="page-header">
    <h1>Meus Dados</h1>
</div>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Dados atualizados com sucesso.</p>
<?php endif; ?>

<label>Nome</label>
<input type="text" value="<?= View::e($client['name']) ?>" disabled>

<label>E-mail</label>
<input type="email" value="<?= View::e($client['email'] ?? '') ?>" disabled>
<p class="hint-text" style="margin-top:0">Pra alterar nome ou e-mail, fale com quem te vendeu o produto.</p>

<form method="post" action="/painel/meus-dados">
    <?= Csrf::field() ?>

    <div class="form-grid-2">
        <div>
            <label for="person_type">Tipo de pessoa</label>
            <select id="person_type" name="person_type">
                <option value="fisica" <?= ($client['person_type'] ?? 'fisica') === 'fisica' ? 'selected' : '' ?>>Pessoa Física</option>
                <option value="juridica" <?= ($client['person_type'] ?? '') === 'juridica' ? 'selected' : '' ?>>Pessoa Jurídica</option>
            </select>
        </div>
        <div>
            <label for="document">CPF/CNPJ</label>
            <input type="text" id="document" name="document" value="<?= View::e($client['document'] ?? '') ?>">
            <p class="field-error" data-error-for="document"><?= View::e($errors['document'] ?? '') ?></p>
        </div>
    </div>

    <label for="state_registration">Inscrição Estadual</label>
    <input type="text" id="state_registration" name="state_registration" value="<?= View::e($client['state_registration'] ?? '') ?>" placeholder="Isento, se não houver">

    <label for="whatsapp">WhatsApp</label>
    <input type="text" id="whatsapp" name="whatsapp" value="<?= View::e($client['whatsapp'] ?? '') ?>">
    <p class="field-error" data-error-for="whatsapp"><?= View::e($errors['whatsapp'] ?? '') ?></p>

    <label for="city">Cidade</label>
    <input type="text" id="city" name="city" value="<?= View::e($client['city'] ?? '') ?>">

    <label for="state">UF</label>
    <input type="text" id="state" name="state" maxlength="2" style="text-transform:uppercase" value="<?= View::e($client['state'] ?? '') ?>">

    <label for="address">Endereço</label>
    <input type="text" id="address" name="address" value="<?= View::e($client['address'] ?? '') ?>" placeholder="Rua, número, bairro, CEP">

    <button type="submit" class="btn btn-primary" style="margin-top:16px">Salvar</button>
</form>
