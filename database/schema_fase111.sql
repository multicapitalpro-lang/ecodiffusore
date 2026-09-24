-- Fase 111: segunda moeda (hoje so' Guarani paraguaio, PYG) nas calculadoras de economia/comissao,
-- restrita a UM Licenciado especifico (e a rede dele) que vai atuar no Paraguai -- pedido explicito
-- do usuario. users.secondary_currency fica NULL pra todo mundo, exceto quem o admin configurar.
ALTER TABLE users ADD COLUMN secondary_currency VARCHAR(3) NULL AFTER city;

-- Cache da cotacao (Fase 111 busca automatica numa API publica, ver App\Core\ExchangeRateClient)
-- -- evita bater na API a cada carga de tela, e da um fallback se a API cair.
CREATE TABLE exchange_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pair VARCHAR(10) NOT NULL,
    rate DECIMAL(14,4) NOT NULL,
    fetched_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pair (pair)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Licenciado LUCINEI LUCION (id=86 em producao, conferir antes de rodar em outro ambiente) --
-- vai atuar no Paraguai, pedido explicito do usuario. Gestores/Vendedores da rede dele herdam
-- via User::licenciadoFor() (sobe a cadeia de manager_id), nao precisa marcar cada um.
UPDATE users SET secondary_currency = 'PYG' WHERE id = 86;
