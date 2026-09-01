-- Ecodiffusore Brasil - Painel - Fase 15 (wizard de orcamento expandido: modelo, ARLA, reprogramacao)

ALTER TABLE leads
    ADD COLUMN vehicle_model VARCHAR(80) NULL AFTER vehicle_brand,
    ADD COLUMN vehicle_reprogrammed_power VARCHAR(30) NULL AFTER vehicle_ecu_status,
    ADD COLUMN vehicle_has_arla ENUM('sim', 'nao') NULL AFTER vehicle_reprogrammed_power;
