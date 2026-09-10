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
    'fabrica' => 'Fábrica',
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
    'pin' => '<path d="M10 2.2c-3 0-5.3 2.3-5.3 5.2 0 3.9 5.3 10.4 5.3 10.4s5.3-6.5 5.3-10.4c0-2.9-2.3-5.2-5.3-5.2Z"/><circle cx="10" cy="7.4" r="1.9"/>',
    'search' => '<circle cx="8.5" cy="8.5" r="5"/><path d="m16 16-3.6-3.6"/>',
];
$icon = function (string $name) use ($icons) {
    return '<svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
};
/** Restricao granular de tela, so pra Gestor/Vendedor (ver App\Core\ScreenPermissions) -- esconde
 *  o link do menu, mas quem realmente bloqueia acesso direto por URL e' o Router. */
$canScreen = fn (string $key) => \App\Core\ScreenPermissions::can($user, $key);

$managerRoles = Roles::MANAGEMENT;
$staffRoles = Roles::STAFF;
$userManagementRoles = Roles::USER_MANAGEMENT;
$supervisorAssignmentRoles = Roles::SUPERVISOR_ASSIGNMENT;
$pendingApprovals = in_array($role, Roles::SUPERVISOR_ASSIGNMENT, true) ? \App\Models\User::pendingApprovalCount($user) : 0;
$pendingWarranties = in_array($role, Roles::SUPERVISOR_ASSIGNMENT, true)
    ? \App\Models\WarrantyRequest::countPending($role === 'admin' ? null : \App\Models\User::nationalIds((int) $user['id']))
    : 0;
$vendasOpen = $anyActive(['/painel/pedidos', '/painel/orcamentos', '/painel/produtos', '/painel/tabela-precos', '/painel/configuracoes/pagamento', '/painel/simulador', '/painel/materiais', '/painel/garantias', '/painel/entregas']);
$leadsOpen = $anyActive(['/painel/leads', '/painel/clientes', '/painel/configuracoes/roteamento']);
$financeiroOpen = $anyActive(['/painel/financeiro']);
$desempenhoOpen = $anyActive(['/painel/desempenho/vendedores', '/painel/desempenho/funil', '/painel/meu-ranking', '/painel/metas']);
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

            <?php if ($role === 'cliente'): ?>
                <a href="/painel/meus-dados" class="<?= $isActive('/painel/meus-dados') ? 'is-active' : '' ?>"><?= $icon('gear') ?> Meus Dados</a>
                <a href="/painel/minhas-garantias" class="<?= $isActive('/painel/minhas-garantias') ? 'is-active' : '' ?>"><?= $icon('box') ?> Minhas Garantias</a>
                <a href="/painel/tutoriais" class="<?= $isActive('/painel/tutoriais') ? 'is-active' : '' ?>"><?= $icon('megaphone') ?> Vídeos Tutoriais</a>
            <?php endif; ?>

            <?php if ($role === Roles::FACTORY): ?>
                <a href="/painel/fabrica" class="<?= $isActive('/painel/fabrica') ? 'is-active' : '' ?>"><?= $icon('box') ?> Pedidos pra Despachar</a>
            <?php endif; ?>

            <?php if (in_array($role, $staffRoles, true)): ?>
                <a href="/painel/busca" class="painel-search-navlink <?= $isActive('/painel/busca') ? 'is-active' : '' ?>"><?= $icon('search') ?> Busca</a>
                <details class="nav-group" <?= $leadsOpen ? 'open' : '' ?>>
                    <summary><?= $icon('pin') ?> Leads</summary>
                    <div class="nav-subitems">
                        <?php if ($canScreen('leads')): ?>
                            <a href="/painel/leads" class="<?= $isActive('/painel/leads') ? 'is-active' : '' ?>">Leads</a>
                        <?php endif; ?>
                        <?php if (in_array($role, array_merge($managerRoles, Roles::NATIONAL_SUPPORT), true)): ?>
                            <a href="/painel/leads/extensoes" class="<?= $isActive('/painel/leads/extensoes') ? 'is-active' : '' ?>">Extensões de Prazo</a>
                        <?php endif; ?>
                        <?php if ($canScreen('clientes')): ?>
                            <a href="/painel/clientes" class="<?= $isActive('/painel/clientes') ? 'is-active' : '' ?>">Clientes</a>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                            <a href="/painel/configuracoes/roteamento" class="<?= $isActive('/painel/configuracoes/roteamento') ? 'is-active' : '' ?>">Roteamento de Leads</a>
                        <?php endif; ?>
                    </div>
                </details>

                <details class="nav-group" <?= $vendasOpen ? 'open' : '' ?>>
                    <summary><?= $icon('cart') ?> Vendas (CRM)</summary>
                    <div class="nav-subitems">
                        <?php if ($canScreen('simulador')): ?>
                            <a href="/painel/simulador" class="<?= $isActive('/painel/simulador') ? 'is-active' : '' ?>">Simulador de Economia</a>
                        <?php endif; ?>
                        <?php if ($canScreen('materiais')): ?>
                            <a href="/painel/materiais" class="<?= $isActive('/painel/materiais') ? 'is-active' : '' ?>">Materiais de Venda</a>
                        <?php endif; ?>
                        <?php if ($canScreen('pedidos')): ?>
                            <a href="/painel/pedidos" class="<?= $isActive('/painel/pedidos') ? 'is-active' : '' ?>">Pedidos</a>
                        <?php endif; ?>
                        <?php if ($canScreen('orcamentos')): ?>
                            <a href="/painel/orcamentos" class="<?= $isActive('/painel/orcamentos') ? 'is-active' : '' ?>">Orçamentos</a>
                        <?php endif; ?>
                        <?php if ($canScreen('entregas')): ?>
                            <a href="/painel/entregas" class="<?= $isActive('/painel/entregas') ? 'is-active' : '' ?>">Acompanhar Entregas</a>
                        <?php endif; ?>
                        <?php if (in_array($role, ['admin', 'gerente'], true)): ?>
                            <a href="/painel/garantias" class="<?= $isActive('/painel/garantias') ? 'is-active' : '' ?>">
                                Garantias
                                <?php if ($pendingWarranties > 0): ?><span class="nav-badge"><?= (int) $pendingWarranties ?></span><?php endif; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                            <a href="/painel/produtos" class="<?= $isActive('/painel/produtos') ? 'is-active' : '' ?>">Produtos</a>
                            <a href="/painel/tabela-precos" class="<?= $isActive('/painel/tabela-precos') ? 'is-active' : '' ?>">Tabela de preços</a>
                            <a href="/painel/configuracoes/pagamento" class="<?= $isActive('/painel/configuracoes/pagamento') ? 'is-active' : '' ?>">Config. de Pagamento</a>
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
                        <?php if (in_array($role, $managerRoles, true) && $canScreen('desempenho')): ?>
                            <a href="/painel/desempenho/vendedores" class="<?= $isActive('/painel/desempenho/vendedores') ? 'is-active' : '' ?>">Vendedores</a>
                        <?php endif; ?>
                        <?php if (in_array($role, array_merge($managerRoles, $nationalSupportRoles), true) && $canScreen('desempenho')): ?>
                            <a href="/painel/desempenho/funil" class="<?= $isActive('/painel/desempenho/funil') ? 'is-active' : '' ?>">Funil de Conversão</a>
                        <?php endif; ?>
                        <?php if ($role === Roles::SELLER && $canScreen('desempenho')): ?>
                            <a href="/painel/meu-ranking" class="<?= $isActive('/painel/meu-ranking') ? 'is-active' : '' ?>">Meu Ranking</a>
                        <?php endif; ?>
                        <a href="/painel/metas" class="<?= $isActive('/painel/metas') ? 'is-active' : '' ?>">Metas</a>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (in_array($role, $panoramaRoles, true)): ?>
                <a href="/painel/desempenho/panorama" class="nav-link-highlight <?= $isActive('/painel/desempenho/panorama') ? 'is-active' : '' ?>">
                    <?= $icon('pin') ?> Expansão Licenciados
                </a>
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
                        <?php if ($canScreen('comissoes')): ?>
                            <a href="/painel/financeiro/comissoes" class="<?= $isActive('/painel/financeiro/comissoes') ? 'is-active' : '' ?>">Comissões</a>
                        <?php endif; ?>
                        <?php if ($role === 'licenciado'): ?>
                            <a href="/painel/meus-custos" class="<?= $isActive('/painel/meus-custos') ? 'is-active' : '' ?>">Meus Custos</a>
                        <?php endif; ?>
                        <a href="/painel/financeiro/relatorios" class="<?= $isActive('/painel/financeiro/relatorios') ? 'is-active' : '' ?>">Relatórios</a>
                        <?php if (in_array($role, ['admin', 'gerente'], true)): ?>
                            <a href="/painel/financeiro/impostos" class="<?= $isActive('/painel/financeiro/impostos') ? 'is-active' : '' ?>">Controle Fiscal</a>
                            <a href="/painel/financeiro/antecipacoes" class="<?= $isActive('/painel/financeiro/antecipacoes') ? 'is-active' : '' ?>">Antecipações</a>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (in_array($role, ['licenciado', 'gestor', 'vendedor'], true)): ?>
                <a href="/painel/assinatura" class="<?= $isActive('/painel/assinatura') ? 'is-active' : '' ?>">⭐ Assinatura</a>
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

            <?php if ($role === 'admin'): ?>
                <a href="/painel/configuracoes/nfe" class="<?= $isActive('/painel/configuracoes/nfe') ? 'is-active' : '' ?>"><?= $icon('wallet') ?> Config. de NF-e</a>
                <a href="/painel/configuracoes/email" class="<?= $isActive('/painel/configuracoes/email') ? 'is-active' : '' ?>"><?= $icon('gear') ?> Config. de E-mail</a>
                <a href="/painel/configuracoes/empresa" class="<?= $isActive('/painel/configuracoes/empresa') ? 'is-active' : '' ?>"><?= $icon('gear') ?> Dados da Empresa</a>
            <?php endif; ?>
            <?php if (in_array($role, ['admin', 'gerente'], true)): ?>
                <a href="/painel/configuracoes/whatsapp" class="<?= $isActive('/painel/configuracoes/whatsapp') ? 'is-active' : '' ?>"><?= $icon('gear') ?> Config. de WhatsApp</a>
                <a href="/painel/configuracoes/tutoriais" class="<?= $isActive('/painel/configuracoes/tutoriais') ? 'is-active' : '' ?>"><?= $icon('gear') ?> Vídeos Tutoriais</a>
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
            <?php if (in_array($role, $staffRoles, true)): ?>
                <form method="get" action="/painel/busca" class="painel-search">
                    <input type="text" name="q" placeholder="Buscar lead, cliente, pedido..." aria-label="Busca" value="<?= $isActive('/painel/busca') ? View::e($_GET['q'] ?? '') : '' ?>">
                    <button type="submit" aria-label="Buscar">🔍</button>
                </form>
                <button type="button" id="btn-proposta-facil" class="btn-proposta-facil">⚡ Proposta Fácil</button>
            <?php endif; ?>
            <a class="painel-logout" href="/painel/logout">Sair</a>
        </header>
        <main class="painel-content">
            <?php $content(); ?>
        </main>
    </div>
</div>

<?php if (in_array($role, $staffRoles, true)): ?>
    <dialog class="modal" id="modal-proposta-facil">
        <div id="modal-proposta-facil-content"></div>
    </dialog>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= View::asset('/assets/js/painel.js') ?>"></script>
<script src="<?= View::asset('/assets/js/password-toggle.js') ?>"></script>
</body>
</html>
