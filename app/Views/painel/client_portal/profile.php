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

<form method="post" action="/painel/meus-dados" class="panel-form panel-form-wide">
    <?= Csrf::field() ?>

    <div class="form-grid-2">
        <div>
            <label>Nome</label>
            <input type="text" value="<?= View::e($client['name']) ?>" disabled>
        </div>
        <div>
            <label>E-mail</label>
            <input type="email" value="<?= View::e($client['email'] ?? '') ?>" disabled>
        </div>
    </div>
    <p class="hint-text" style="margin-top:0">Pra alterar nome ou e-mail, fale com quem te vendeu o produto.</p>

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
            <input type="text" id="document" name="document" value="<?= View::e($client['document'] ?? '') ?>" required>
            <p class="field-error" data-error-for="document"><?= View::e($errors['document'] ?? '') ?></p>
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="state_registration">Inscrição Estadual</label>
            <input type="text" id="state_registration" name="state_registration" value="<?= View::e($client['state_registration'] ?? '') ?>" placeholder="Isento, se não houver">
        </div>
        <div>
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp" value="<?= View::e($client['whatsapp'] ?? '') ?>" required>
            <p class="field-error" data-error-for="whatsapp"><?= View::e($errors['whatsapp'] ?? '') ?></p>
        </div>
    </div>

    <h3 class="section-title">Endereço de entrega</h3>
    <p class="hint-text" style="margin-top:0">Preencha completo — é a partir daqui que o rastreio da sua entrega é enviado.</p>

    <div class="form-grid-2">
        <div>
            <label for="zip_code">CEP</label>
            <input type="text" id="zip_code" name="zip_code" value="<?= View::e($client['zip_code'] ?? '') ?>" required>
            <p class="field-error" data-error-for="zip_code"><?= View::e($errors['zip_code'] ?? '') ?></p>
        </div>
        <div>
            <label for="city">Cidade</label>
            <input type="text" id="city" name="city" value="<?= View::e($client['city'] ?? '') ?>" required>
            <p class="field-error" data-error-for="city"><?= View::e($errors['city'] ?? '') ?></p>
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="street">Rua</label>
            <input type="text" id="street" name="street" value="<?= View::e($client['street'] ?? '') ?>" required>
            <p class="field-error" data-error-for="street"><?= View::e($errors['street'] ?? '') ?></p>
        </div>
        <div>
            <label for="number">Número</label>
            <input type="text" id="number" name="number" value="<?= View::e($client['number'] ?? '') ?>" required>
            <p class="field-error" data-error-for="number"><?= View::e($errors['number'] ?? '') ?></p>
        </div>
    </div>

    <div class="form-grid-2">
        <div>
            <label for="neighborhood">Bairro</label>
            <input type="text" id="neighborhood" name="neighborhood" value="<?= View::e($client['neighborhood'] ?? '') ?>" required>
            <p class="field-error" data-error-for="neighborhood"><?= View::e($errors['neighborhood'] ?? '') ?></p>
        </div>
        <div>
            <label for="state">UF</label>
            <input type="text" id="state" name="state" maxlength="2" style="text-transform:uppercase" value="<?= View::e($client['state'] ?? '') ?>" required>
            <p class="field-error" data-error-for="state"><?= View::e($errors['state'] ?? '') ?></p>
        </div>
    </div>

    <label for="complement">Complemento</label>
    <input type="text" id="complement" name="complement" value="<?= View::e($client['complement'] ?? '') ?>" placeholder="Apto, bloco, referência (opcional)">

    <button type="submit" class="btn btn-primary" style="margin-top:16px">Salvar</button>
</form>
