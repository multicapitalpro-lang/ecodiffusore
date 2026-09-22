<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$erro = $_GET['erro'] ?? null;
?>
<div class="page-header">
    <h1>Treinamento Obrigatório do Vendedor</h1>
</div>
<p class="hint-text" style="margin-top:-6px;">Vídeos que todo Vendedor recém-cadastrado precisa assistir até o fim (mín. 90%) antes de liberar o acesso completo ao painel. Organize em módulos e use os botões ↑/↓ pra definir a sequência (módulos entre si, e vídeos dentro de cada módulo). Só arquivo direto (.mp4/.webm) — link de embed do YouTube não permite rastrear o progresso real de reprodução, então não use aqui (pros Vídeos Tutoriais do comprador, que não são obrigatórios, os dois formatos continuam valendo).</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php elseif ($erro === '2'): ?>
    <p class="form-msg form-msg-erro">Informe o título do módulo.</p>
<?php elseif ($erro): ?>
    <p class="form-msg form-msg-erro">Preencha título e link do vídeo.</p>
<?php endif; ?>

<?php foreach ($modules as $i => $m): ?>
    <div class="dash-card" style="text-align:left;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
            <strong>📦 <?= View::e($m['title']) ?></strong>
            <div style="display:flex;gap:4px;align-items:center;">
                <form method="post" action="/painel/configuracoes/treinamento/modulos/<?= (int) $m['id'] ?>/subir" class="inline-form">
                    <?= Csrf::field() ?>
                    <button type="submit" class="link-button" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                </form>
                <form method="post" action="/painel/configuracoes/treinamento/modulos/<?= (int) $m['id'] ?>/descer" class="inline-form">
                    <?= Csrf::field() ?>
                    <button type="submit" class="link-button" <?= $i === count($modules) - 1 ? 'disabled' : '' ?>>↓</button>
                </form>
                <form method="post" action="/painel/configuracoes/treinamento/modulos/<?= (int) $m['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir este módulo? Os vídeos dentro dele NÃO são excluídos, só ficam sem módulo.');">
                    <?= Csrf::field() ?>
                    <button type="submit" class="link-button" style="color:#c53030">Excluir módulo</button>
                </form>
            </div>
        </div>

        <?php $moduleVideos = $byModule[$m['id']] ?? []; ?>
        <div class="table-scroll" style="margin-top:10px;">
            <table class="data-table">
                <thead><tr><th>Título</th><th>Link</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($moduleVideos as $vi => $v): ?>
                        <tr>
                            <td><?= View::e($v['title']) ?></td>
                            <td><a href="<?= View::e($v['video_url']) ?>" target="_blank" rel="noopener" class="link-small"><?= View::e($v['video_url']) ?></a></td>
                            <td style="white-space:nowrap;">
                                <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/subir" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button" <?= $vi === 0 ? 'disabled' : '' ?>>↑</button>
                                </form>
                                <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/descer" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button" <?= $vi === count($moduleVideos) - 1 ? 'disabled' : '' ?>>↓</button>
                                </form>
                                <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir este vídeo? Vendedores que já assistiram perdem o progresso registrado nele.');">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$moduleVideos): ?>
                        <tr><td colspan="3">Nenhum vídeo neste módulo ainda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php $semModulo = $byModule[0] ?? []; ?>
<?php if ($semModulo): ?>
    <div class="dash-card" style="text-align:left;margin-bottom:16px;">
        <strong>Sem módulo</strong>
        <div class="table-scroll" style="margin-top:10px;">
            <table class="data-table">
                <thead><tr><th>Título</th><th>Link</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($semModulo as $vi => $v): ?>
                        <tr>
                            <td><?= View::e($v['title']) ?></td>
                            <td><a href="<?= View::e($v['video_url']) ?>" target="_blank" rel="noopener" class="link-small"><?= View::e($v['video_url']) ?></a></td>
                            <td style="white-space:nowrap;">
                                <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/subir" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button" <?= $vi === 0 ? 'disabled' : '' ?>>↑</button>
                                </form>
                                <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/descer" class="inline-form">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button" <?= $vi === count($semModulo) - 1 ? 'disabled' : '' ?>>↓</button>
                                </form>
                                <form method="post" action="/painel/configuracoes/treinamento/<?= (int) $v['id'] ?>/excluir" class="inline-form" onsubmit="return confirm('Excluir este vídeo? Vendedores que já assistiram perdem o progresso registrado nele.');">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="link-button" style="color:#c53030">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!$modules && !$semModulo): ?>
    <p class="hint-text">Nenhum módulo ou vídeo cadastrado ainda.</p>
<?php endif; ?>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:16px;">
    <details class="settings-card" style="max-width:480px;">
        <summary style="cursor:pointer;font-weight:600">+ Adicionar módulo</summary>
        <form method="post" action="/painel/configuracoes/treinamento/modulos" class="panel-form panel-form-wide" style="margin-top:12px">
            <?= Csrf::field() ?>
            <label for="module-title">Título do módulo</label>
            <input type="text" id="module-title" name="title" placeholder="Ex: 1. Apresentação do produto" required>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
        </form>
    </details>

    <details class="settings-card" style="max-width:640px;">
        <summary style="cursor:pointer;font-weight:600">+ Adicionar vídeo</summary>
        <form method="post" action="/painel/configuracoes/treinamento" class="panel-form panel-form-wide" style="margin-top:12px">
            <?= Csrf::field() ?>
            <label for="training-title">Título</label>
            <input type="text" id="training-title" name="title" placeholder="Ex: Como apresentar o produto pro cliente" required>
            <label for="training-module">Módulo</label>
            <select id="training-module" name="module_id">
                <option value="">— Sem módulo —</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"><?= View::e($m['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="training-url">Link direto do arquivo de vídeo (.mp4/.webm)</label>
            <input type="url" id="training-url" name="video_url" placeholder="https://ecodiffusorebrasil.com.br/assets/videos/treinamento1.mp4" required>
            <button type="submit" class="btn btn-primary" style="margin-top:12px">Salvar</button>
        </form>
    </details>
</div>
