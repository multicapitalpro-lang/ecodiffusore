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

    'asaas' => [
        'env' => 'production', // 'sandbox' | 'production'
        'base_url' => 'https://api.asaas.com/v3', // sandbox: https://api-sandbox.asaas.com/v3
        'api_key' => 'TROQUE_AQUI',
        'webhook_token' => 'TROQUE_AQUI', // gerado com bin2hex(random_bytes(32)), configurado tambem no Asaas
    ],

    'clicksign' => [
        'env' => 'production', // 'sandbox' | 'production'
        'base_url' => 'https://app.clicksign.com', // sandbox: https://sandbox.clicksign.com
        'api_key' => 'TROQUE_AQUI',
        'webhook_secret' => 'TROQUE_AQUI', // segredo por-webhook gerado no cadastro do webhook no ClickSign (HMAC-SHA256)
    ],
];
