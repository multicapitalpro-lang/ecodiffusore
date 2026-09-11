<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = isset($_GET['erro']);
?>
<div class="page-header">
    <h1>Treinamento Obrigatório do Vendedor</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Vídeos que todo Vendedor recém-cadastrado precisa assistir até o fim (mín. 90%) antes de liberar o acesso completo ao painel. Só arquivo direto (.mp4/.webm) — link de embed do YouTube não permite rastrear o progresso real de reprodução, então não use aqui (pros Vídeos Tutoriais do comprador, que não são obrigatórios, os dois formatos continuam valendo).</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Preencha título e link do vídeo.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Título</th><th>Link</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($videos as $v): ?>
                <tr>
                    <td><?= View::e($v['title']) ?></td>
                    <td><a href="<?= View::e($v['video_url']) ?>" target="_blank" rel="noopener" class="link-small"><?= View::e($v['video_url']) ?></a></td>
                    <td>
                        <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir este vídeo? Vendedores que já assistiram perdem o progresso registrado nele.');">
                            <?= Csrf::field() ?>
                            <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$videos): ?>
                <tr><td colspan="3">Nenhum vídeo cadastrado ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<details class="settings-card" style="max-width:640px;margin-top:16px">
    <summary style="cursor:pointer;font-weight:600">+ Adicionar vídeo</summary>
    <form method="post" action="/painel/configuracoes/treinamento" class="panel-form panel-form-wide" style="margin-top:12px">
        <?= Csrf::field() ?>
        <label for="training-title">Título</label>
        <input type="text" id="training-title" name="title" placeholder="Ex: Como apresentar o produto pro cliente" required>
        <label for="training-url">Link direto do arquivo de vídeo (.mp4/.webm)</label>
        <input type="url" id="training-url" name="video_url" placeholder="https://ecodiffusorebrasil.com.br/assets/videos/treinamento1.mp4" required>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
    </form>
</details>
