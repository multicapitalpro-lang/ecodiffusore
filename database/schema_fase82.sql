-- Fase 82: meta por quantidade de equipamentos vendidos, alem de meta por valor em R$ --
-- pedido explicito do usuario ("fica mais exato o processo").
ALTER TABLE goals ADD COLUMN metric_type ENUM('valor','quantidade') NOT NULL DEFAULT 'valor' AFTER target_value;
