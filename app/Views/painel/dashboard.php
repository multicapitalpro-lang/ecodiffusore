<?php
use App\Core\View;
$role = $user['role_slug'] ?? '';
?>
<h1>Bem-vindo(a), <?= View::e($user['name']) ?></h1>

<?php if ($role === 'admin'): ?>
    <div class="cards-grid">
        <div class="dash-card">
            <span>Leads recebidos</span>
            <strong><?= (int) ($leadCount ?? 0) ?></strong>
            <a href="/painel/leads">Ver todos</a>
        </div>
        <div class="dash-card">
            <span>Usuários</span>
            <a href="/painel/usuarios">Gerenciar usuários e papéis</a>
        </div>
        <div class="dash-card dash-card-soon">
            <span>CRM / Vendas</span>
            <p>Em breve — Fase 2</p>
        </div>
        <div class="dash-card dash-card-soon">
            <span>Financeiro</span>
            <p>Em breve — Fase 3</p>
        </div>
    </div>

<?php elseif ($role === 'licenciado'): ?>
    <div class="cards-grid">
        <div class="dash-card">
            <span>Meus leads</span>
            <strong><?= count($myLeads ?? []) ?></strong>
            <a href="/painel/leads">Ver meus leads</a>
        </div>
        <div class="dash-card dash-card-soon">
            <span>Minha comissão</span>
            <p>Em breve — Fase 3</p>
        </div>
        <div class="dash-card dash-card-soon">
            <span>Meus clientes</span>
            <p>Em breve — Fase 2</p>
        </div>
    </div>

<?php elseif (in_array($role, ['gerente', 'supervisor'], true)): ?>
    <div class="cards-grid">
        <div class="dash-card">
            <span>Leads</span>
            <a href="/painel/leads">Ver leads recebidos</a>
        </div>
        <div class="dash-card dash-card-soon">
            <span>Indicadores da equipe</span>
            <p>Em breve — Fase 2</p>
        </div>
    </div>

<?php else: ?>
    <div class="cards-grid">
        <div class="dash-card dash-card-soon">
            <span>Meu pedido</span>
            <p>Em breve — Fase 2</p>
        </div>
    </div>
<?php endif; ?>
