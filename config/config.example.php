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
        'template_key' => 'TROQUE_AQUI', // chave do Modelo "Contrato Assinatura Diferencial" (Automação > Modelos no ClickSign)
    ],

    // API oficial dos Correios (https://api.correios.com.br) -- App\Core\CorreiosClient. Rastreio
    // real (status/data do ultimo evento) quando a fabrica cadastra o codigo. Precisa de um contrato
    // Correios (Cartao de Postagem) e credenciais do Meu Correios Business -- SEM isso, track()
    // sempre devolve null (fica so o codigo/transportadora digitado manualmente, sem status ao vivo).
    'correios' => [
        'usuario' => 'TROQUE_AQUI', // CNPJ ou usuario do Meu Correios Business
        'senha' => 'TROQUE_AQUI',
        'cartao_postagem' => 'TROQUE_AQUI', // numero do Cartao de Postagem vinculado ao contrato
    ],

    // SMTP autenticado (App\Core\Mailer) -- PHP mail() puro nao entregava (confirmado com
    // mail-tester.com: nem chegava no destino). host/port/encryption vem da tela "Configurar
    // cliente de e-mail" da caixa no hPanel (Hostinger) -- normalmente smtp.hostinger.com,
    // porta 465 com encryption 'ssl', ou porta 587 com 'tls'.
    'smtp' => [
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'encryption' => 'ssl', // 'ssl' (porta 465) ou 'tls' (porta 587)
        'user' => 'atendimento@ecodiffusorebrasil.com.br',
        'pass' => 'TROQUE_AQUI',
        'from_name' => 'Ecodiffusore Brasil',
        'reply_to' => 'contato@ecodiffusorebrasil.com.br',
    ],

    // WhatsApp Cloud API (Meta) -- App\Core\WhatsAppClient. Token permanente gerado via
    // Usuario do Sistema (Configuracoes da Empresa > Usuarios > Usuarios do sistema), com as
    // permissoes whatsapp_business_messaging + whatsapp_business_management, "Nunca expira".
    'whatsapp' => [
        'api_version' => 'v25.0',
        'phone_number_id' => 'TROQUE_AQUI', // Identificacao do numero de telefone no Gerenciador do WhatsApp
        'waba_id' => 'TROQUE_AQUI', // WhatsApp Business account ID
        'access_token' => 'TROQUE_AQUI',
    ],

    // Evolution API (self-hosted, VPS separado) -- App\Core\EvolutionApiClient. Fallback nao-oficial
    // enquanto a WhatsApp Cloud API oficial (bloco 'whatsapp' acima) esta em analise pela Meta.
    'evolution' => [
        'base_url' => 'http://TROQUE_AQUI:8080',
        'api_key' => 'TROQUE_AQUI',
        'instance' => 'ecodiffusore',
    ],
];
