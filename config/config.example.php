<?php
// Copie este arquivo para config.php (fora do git) e preencha com os dados reais.
// config.php NUNCA deve ser versionado nem ficar dentro de public_html.

return [
    'app_env' => 'production', // 'local' | 'production'
    'app_url' => 'https://ecodiffusorebrasil.com.br',

    'db' => [
        'host' => 'localhost',
        'name' => 'u719183319_ecodiffusore',
        'user' => 'u719183319_ecodiffusore',
        'pass' => 'TROQUE_AQUI',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => 'ecodiffusore_sess',
        'lifetime' => 60 * 60 * 8, // 8 horas
    ],
];
