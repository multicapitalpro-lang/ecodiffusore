<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Vídeos Tutoriais do Comprador</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Cadastre aqui os vídeos que aparecem no painel do cliente (instalação, teste da chave etc.) — cole o link de embed do YouTube (ex: https://www.youtube.com/embed/VIDEO_ID) ou a URL direta de um arquivo .mp4/.webm.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Preencha título e link do vídeo.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Título</th><th>Link</th><th>Descrição</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($videos as $v): ?>
                <tr>
                    <td><?= View::e($v['title']) ?></td>
                    <td><a href="<?= View::e($v['video_url']) ?>" target="_blank" rel="noopener" class="link-small"><?= View::e($v['video_url']) ?></a></td>
                    <td><?= View::e($v['description'] ?: '—') ?></td>
                    <td>
                        <form method="post" action="/painel/configuracoes/tutoriais/<?= (int) $v['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir este vídeo?');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$videos): ?>
                <tr><td colspan="4">Nenhum vídeo cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<details class="settings-card" style="max-width:640px;margin-top:16px">
    <summary style="cursor:pointer;font-weight:600">+ Adicionar vídeo</summary>
    <form method="post" action="/painel/configuracoes/tutoriais" class="panel-form panel-form-wide" style="margin-top:12px">
        <?= Csrf::field() ?>
        <label for="tutorial-title">Título</label>
        <input type="text" id="tutorial-title" name="title" placeholder="Ex: Como instalar o Ecodiffusore" required>
        <label for="tutorial-url">Link do vídeo</label>
        <input type="url" id="tutorial-url" name="video_url" placeholder="https://www.youtube.com/embed/..." required>
        <label for="tutorial-description">Descrição (opcional)</label>
        <textarea id="tutorial-description" name="description" rows="2"></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
    </form>
</details>
