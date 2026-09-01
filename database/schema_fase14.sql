-- Ecodiffusore Brasil - Painel - Fase 14 (orcamento por placa + roteamento pra vendedor + geo)

-- Cidade/UF onde o Licenciado atua, usado pra achar o vendedor mais proximo do cliente.
ALTER TABLE users
    ADD COLUMN city VARCHAR(120) NULL AFTER whatsapp,
    ADD COLUMN state CHAR(2) NULL AFTER city;

-- Dados do veiculo capturados no fluxo de orcamento por placa (/comprar) -- tudo nullable, so
-- preenchido quando o lead vem desse fluxo especifico.
ALTER TABLE leads
    ADD COLUMN vehicle_plate VARCHAR(10) NULL,
    ADD COLUMN vehicle_year VARCHAR(9) NULL,
    ADD COLUMN vehicle_brand VARCHAR(60) NULL,
    ADD COLUMN vehicle_power VARCHAR(30) NULL,
    ADD COLUMN vehicle_ecu_status ENUM('original', 'reprogramado') NULL;

-- Referencia de municipios brasileiros (so leitura, dados importados 1x via database/import_br_cities.php
-- a partir do dataset publico kelvins/municipios-brasileiros). Usada pelo App\Core\GeoMatch pra
-- calcular distancia entre a cidade do cliente e a cidade de cada Licenciado.
CREATE TABLE IF NOT EXISTS br_cities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ibge_code INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    name_normalized VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL,
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(10, 7) NOT NULL,
    INDEX idx_name_normalized (name_normalized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
