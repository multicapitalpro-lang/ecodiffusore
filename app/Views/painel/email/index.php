<?php
use App\Core\View;
$totalPages = (int) ceil($total / $perPage);
?>
<div class="page-header">
    <h1>Caixa de Entrada — atendimento@ecodiffusorebrasil.com.br</h1>
</div>

<?php if ($error): ?>
    <p class="form-msg form-msg-erro">Não foi possível conectar na caixa de e-mail agora: <?= View::e($error) ?></p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th></th><th>De</th><th>Assunto</th><th>Data</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($messages as $m): ?>
                <tr style="<?= $m['unread'] ? 'font-weight:600;' : '' ?>">
                    <td><?= $m['unread'] ? '🔵' : '' ?></td>
                    <td><?= View::e($m['from']) ?></td>
                    <td><a href="/painel/email/<?= (int) $m['uid'] ?>"><?= View::e($m['subject']) ?></a></td>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($m['date']))) ?></td>
                    <td><?= $m['answered'] ? '<span class="hint-text">↩️ respondido</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$messages): ?>
                <tr><td colspan="5">Nenhuma mensagem encontrada.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="inline-form" style="margin-top:16px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="/painel/email?page=<?= $p ?>" class="btn btn-outline btn-sm <?= $p === $page ? 'is-active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
