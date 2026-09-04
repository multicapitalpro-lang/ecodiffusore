<?php
use App\Core\Roles;
use App\Core\View;
/** @var callable $content */
/** @var array $user */
$role = $user['role_slug'] ?? '';
$roleLabels = [
    'admin' => 'Administrador',
    'gestor' => 'Gestor',
    'licenciado' => 'Licenciado',
    'vendedor' => 'Vendedor',
    'gerente' => 'Gerente',
    'supervisor' => 'Supervisor',
    'cliente' => 'Cliente',
];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$isActive = fn (string $prefix) => $path === $prefix || str_starts_with($path, $prefix . '/');
$anyActive = fn (array $prefixes) => array_reduce($prefixes, fn ($carry, $p) => $carry || $isActive($p), false);

$icons = [
    'home' => '<path d="M3 10.5 10 4l7 6.5"/><path d="M5 9v7h10V9"/>',
    'megaphone' => '<path d="M3 10v2a1 1 0 0 0 1 1h1l2 4V5L5 9H4a1 1 0 0 0-1 1Z"/><path d="M10 6.5v9"/><path d="M13 8v6"/>',
    'cart' => '<circle cx="7" cy="16" r="1"/><circle cx="13" cy="16" r="1"/><path d="M3 4h2l1.6 8.6a1 1 0 0 0 1 .9h6a1 1 0 0 0 1-.8L16 7H5.2"/>',
    'users' => '<circle cx="7" cy="7" r="2.5"/><path d="M2.5 15c.6-2.4 2.3-4 4.5-4s3.9 1.6 4.5 4"/><circle cx="14" cy="7.5" r="2"/><path d="M12.5 11.2c1.8.2 3.2 1.6 3.7 3.8"/>',
    'box' => '<path d="M3 6.5 10 3l7 3.5-7 3.5-7-3.5Z"/><path d="M3 6.5V13l7 3.5 7-3.5V6.5"/><path d="M10 10v6.5"/>',
    'chart' => '<path d="M3 16h14"/><rect x="5" y="10" width="2.4" height="6"/><rect x="9" y="6" width="2.4" height="10"/><rect x="13" y="8.5" width="2.4" height="7.5"/>',
    'wallet' => '<rect x="3" y="5.5" width="14" height="9.5" rx="1.5"/><path d="M3 8.5h14"/><circle cx="14" cy="11.5" r="1"/>',
    'percent' => '<circle cx="6" cy="6" r="2"/><circle cx="14" cy="14" r="2"/><path d="M15 5 5 15"/>',
    'gear' => '<circle cx="10" cy="10" r="2.6"/><path d="M10 3.5v2M10 14.5v2M16.5 10h-2M5.5 10h-2M14.6 5.4l-1.4 1.4M6.8 13.2l-1.4 1.4M14.6 14.6l-1.4-1.4M6.8 6.8 5.4 5.4"/>',
];
$icon = function (string $name) use ($icons) {
    return '<svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
};

$managerRoles = Roles::MANAGEMENT;
$staffRoles = Roles::STAFF;
$userManagementRoles = Roles::USER_MANAGEMENT;
$supervisorAssignmentRoles = Roles::SUPERVISOR_ASSIGNMENT;
$pendingApprovals = in_array($role, Roles::SUPERVISOR_ASSIGNMENT, true) ? \App\Models\User::pendingApprovalCount($user) : 0;
$vendasOpen = $anyActive(['/painel/leads', '/painel/pedidos', '/painel/orcamentos', '/painel/clientes', '/painel/produtos']);
$financeiroOpen = $anyActive(['/painel/financeiro']);
$desempenhoOpen = $anyActive(['/painel/desempenho', '/painel/metas']);
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel — Ecodiffusore Brasil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= View::asset('/assets/css/painel.css') ?>">
</head>
<body>
<div class="painel-wrap">
    <div class="painel-overlay" id="painel-overlay"></div>
    <aside class="painel-sidebar" id="painel-sidebar">
        <div class="painel-logo">
            <img src="<?= View::asset('/assets/img/logo-full-white.png') ?>" alt="Ecodiffusore Brasil">
        </div>
        <nav>
            <a href="/painel" class="<?= $isActive('/painel') && $path === '/painel' ? 'is-active' : '' ?>"><?= $icon('home') ?> Início</a>

            <?php if (in_array($role, $staffRoles, true)): ?>
                <details class="nav-group" <?= $vendasOpen ? 'open' : '' ?>>
                    <summary><?= $icon('cart') ?> Vendas (CRM)</summary>
                    <div class="nav-subitems">
                        <a href="/painel/leads" class="<?= $isActive('/painel/leads') ? 'is-active' : '' ?>">Leads</a>
                        <a href="/painel/pedidos" class="<?= $isActive('/painel/pedidos') ? 'is-active' : '' ?>">Pedidos</a>
                        <a href="/painel/orcamentos" class="<?= $isActive('/painel/orcamentos') ? 'is-active' : '' ?>">Orçamentos</a>
                        <a href="/painel/clientes" class="<?= $isActive('/painel/clientes') ? 'is-active' : '' ?>">Clientes</a>
                        <?php if ($role === 'admin'): ?>
                            <a href="/painel/produtos" class="<?= $isActive('/painel/produtos') ? 'is-active' : '' ?>">Produtos</a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php
            $nationalSupportRoles = Roles::NATIONAL_SUPPORT;
            $panoramaRoles = ['admin', 'gerente', 'supervisor'];
            $desempenhoVisible = in_array($role, array_merge($managerRoles, $nationalSupportRoles, [Roles::SELLER]), true);
            ?>
            <?php if ($desempenhoVisible): ?>
                <details class="nav-group" <?= $desempenhoOpen ? 'open' : '' ?>>
                    <summary><?= $icon('chart') ?> Desempenho</summary>
                    <div class="nav-subitems">
                        <?php if (in_array($role, $managerRoles, true)): ?>
                            <a href="/painel/desempenho/vendedores" class="<?= $isActive('/painel/desempenho/vendedores') ? 'is-active' : '' ?>">Vendedores</a>
                        <?php endif; ?>
                        <?php if (in_array($role, array_merge($managerRoles, $nationalSupportRoles), true)): ?>
                            <a href="/painel/desempenho/equipe" class="<?= $isActive('/painel/desempenho/equipe') ? 'is-active' : '' ?>">Equipe</a>
                        <?php endif; ?>
                        <a href="/painel/metas" class="<?= $isActive('/painel/metas') ? 'is-active' : '' ?>">Metas</a>
                        <?php if (in_array($role, $panoramaRoles, true)): ?>
                            <a href="/painel/desempenho/panorama" class="<?= $isActive('/painel/desempenho/panorama') ? 'is-active' : '' ?>">Panorama Nacional</a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (in_array($role, $staffRoles, true)): ?>
                <details class="nav-group" <?= $financeiroOpen ? 'open' : '' ?>>
                    <summary><?= $icon('wallet') ?> Financeiro</summary>
                    <div class="nav-subitems">
                        <?php if (in_array($role, $managerRoles, true)): ?>
                            <a href="/painel/financeiro/caixas-bancos" class="<?= $isActive('/painel/financeiro/caixas-bancos') ? 'is-active' : '' ?>">Caixas e Bancos</a>
                            <a href="/painel/financeiro/contas-a-pagar" class="<?= $isActive('/painel/financeiro/contas-a-pagar') ? 'is-active' : '' ?>">Contas a Pagar</a>
                            <a href="/painel/financeiro/contas-a-receber" class="<?= $isActive('/painel/financeiro/contas-a-receber') ? 'is-active' : '' ?>">Contas a Receber</a>
                            <a href="/painel/financeiro/remessas" class="<?= $isActive('/painel/financeiro/remessas') ? 'is-active' : '' ?>">Remessa e Retorno</a>
                        <?php endif; ?>
                        <a href="/painel/financeiro/comissoes" class="<?= $isActive('/painel/financeiro/comissoes') ? 'is-active' : '' ?>">Comissões</a>
                        <?php if (in_array($role, $managerRoles, true)): ?>
                            <a href="/painel/financeiro/relatorios" class="<?= $isActive('/painel/financeiro/relatorios') ? 'is-active' : '' ?>">Relatórios</a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (in_array($role, $userManagementRoles, true)): ?>
                <a href="/painel/usuarios" class="<?= $isActive('/painel/usuarios') ? 'is-active' : '' ?>"><?= $icon('gear') ?> Usuários</a>
            <?php endif; ?>
            <?php if (in_array($role, $supervisorAssignmentRoles, true)): ?>
                <a href="/painel/licenciados" class="<?= $isActive('/painel/licenciados') && !$isActive('/painel/licenciados/aprovacoes') ? 'is-active' : '' ?>"><?= $icon('users') ?> Licenciados</a>
                <a href="/painel/licenciados/aprovacoes" class="<?= $isActive('/painel/licenciados/aprovacoes') ? 'is-active' : '' ?>">
                    <?= $icon('users') ?> Aprovação de Cadastros
                    <?php if ($pendingApprovals > 0): ?><span class="nav-badge"><?= (int) $pendingApprovals ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
                <a href="/painel/auditoria" class="<?= $isActive('/painel/auditoria') ? 'is-active' : '' ?>"><?= $icon('chart') ?> Auditoria</a>
            <?php endif; ?>

            <?php if (in_array($role, $managerRoles, true)): ?>
                <p class="painel-nav-soon">Em breve</p>
                <span class="painel-nav-disabled">NF-e</span>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="painel-main">
        <header class="painel-header">
            <button type="button" class="painel-menu-toggle" id="painel-menu-toggle" aria-label="Abrir menu">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M3 6h14M3 10h14M3 14h14"/></svg>
            </button>
            <span class="painel-user">
                <span class="user-avatar"><?= View::e(mb_strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span>
                <span>Olá, <strong><?= View::e($user['name'] ?? '') ?></strong> — <?= View::e($roleLabels[$role] ?? $role) ?></span>
            </span>
            <a class="painel-logout" href="/painel/logout">Sair</a>
        </header>
        <main class="painel-content">
            <?php $content(); ?>
        </main>
    </div>
</div>
<script src="<?= View::asset('/assets/js/painel.js') ?>"></script>
<script src="<?= View::asset('/assets/js/password-toggle.js') ?>"></script>
</body>
</html>
