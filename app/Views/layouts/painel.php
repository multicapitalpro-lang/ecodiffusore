<?php
use App\Core\View;
/** @var callable $content */
/** @var array $user */
$role = $user['role_slug'] ?? '';
$roleLabels = [
    'admin' => 'Administrador',
    'gerente' => 'Gerente',
    'supervisor' => 'Supervisor',
    'licenciado' => 'Licenciado / Vendedor',
    'cliente' => 'Cliente',
];
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
    <aside class="painel-sidebar">
        <div class="painel-logo">
            <img src="/assets/img/logo-mark.svg" alt="Ecodiffusore Brasil">
        </div>
        <nav>
            <a href="/painel">Início</a>
            <?php if (in_array($role, ['admin', 'gerente', 'supervisor', 'licenciado'], true)): ?>
                <a href="/painel/leads">Leads</a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
                <a href="/painel/usuarios">Usuários</a>
            <?php endif; ?>

            <p class="painel-nav-soon">Em breve</p>
            <span class="painel-nav-disabled">CRM / Vendas</span>
            <span class="painel-nav-disabled">Financeiro</span>
            <span class="painel-nav-disabled">Comissões</span>
            <span class="painel-nav-disabled">Relatórios</span>
        </nav>
    </aside>

    <div class="painel-main">
        <header class="painel-header">
            <span>Olá, <strong><?= View::e($user['name'] ?? '') ?></strong> — <?= View::e($roleLabels[$role] ?? $role) ?></span>
            <a class="painel-logout" href="/painel/logout">Sair</a>
        </header>
        <main class="painel-content">
            <?php $content(); ?>
        </main>
    </div>
</div>
</body>
</html>
