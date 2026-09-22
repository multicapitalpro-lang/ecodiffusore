<?php
use App\Core\Csrf;
use App\Core\View;
$sucesso = isset($_GET['sucesso']);
$statusLabels = ['pendente' => 'Pendente', 'ativo' => 'Ativo', 'recusado' => 'Recusado'];
$statusBadge = ['pendente' => 'novo', 'ativo' => 'active', 'recusado' => 'inativo'];
?>
<div class="page-header">
    <h1>E-mails Profissionais — Pedidos</h1>
    <a href="/painel/configuracoes/empresa" class="btn btn-outline">Ajustar cota de e-mails</a>
</div>
<p class="hint-text" style="margin-top:-6px;">Crie a caixa de verdade no hPanel da Hostinger primeiro, depois marque como Ativo aqui informando a senha/instruções de acesso (o Licenciado recebe isso por WhatsApp). Recusar libera a vaga na cota.</p>

<?php if ($sucesso): ?>
    <p class="form-msg form-msg-ok">Atualizado com sucesso.</p>
<?php endif; ?>

<div class="table-scroll">
    <table class="data-table">
        <thead><tr><th>Licenciado</th><th>Endereço</th><th>Status</th><th>Pedido em</th><th>Observação</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= View::e($r['user_name']) ?></td>
                    <td><?= View::e($r['full_address']) ?></td>
                    <td><span class="status-badge status-<?= $statusBadge[$r['status']] ?? 'novo' ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
                    <td><?= View::e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
                    <td><?= $r['admin_note'] ? View::e($r['admin_note']) : '—' ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($r['status'] === 'pendente'): ?>
                            <details style="display:inline-block;">
                                <summary class="link-button" style="display:inline;cursor:pointer;">Ativar</summary>
                                <form method="post" action="/painel/emails-profissionais/admin/<?= (int) $r['id'] ?>/aprovar" class="panel-form" style="margin-top:6px;min-width:220px;">
                                    <?= Csrf::field() ?>
                                    <label>Senha/instruções de acesso</label>
                                    <textarea name="admin_note" rows="2" placeholder="Ex: senha inicial Abc123, acesse via webmail.ecodiffusorebrasil.com.br"></textarea>
                                    <button type="submit" class="btn btn-primary" style="margin-top:6px;">Confirmar ativação</button>
                                </form>
                            </details>
                            <details style="display:inline-block;margin-left:6px;">
                                <summary class="link-button" style="display:inline;cursor:pointer;color:#c53030;">Recusar</summary>
                                <form method="post" action="/painel/emails-profissionais/admin/<?= (int) $r['id'] ?>/recusar" class="panel-form" style="margin-top:6px;min-width:220px;">
                                    <?= Csrf::field() ?>
                                    <label>Motivo</label>
                                    <textarea name="admin_note" rows="2" placeholder="Ex: endereço já em uso por outro setor"></textarea>
                                    <button type="submit" class="btn btn-outline" style="margin-top:6px;">Confirmar recusa</button>
                                </form>
                            </details>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="6">Nenhum pedido ainda.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
