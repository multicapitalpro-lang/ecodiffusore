-- Fase 30: Persiste os dados de consumo (km/mes, km/litro, preco do diesel) que alimentam o
-- simulador de economia -- ate aqui so viviam em $_SESSION e eram perdidos depois da proposta.
-- Aplicado em produção via script PHP standalone (scp + SSH), documentado aqui pro histórico.

ALTER TABLE leads
    ADD COLUMN vehicle_km_mensal DECIMAL(8,2) NULL AFTER vehicle_has_telemetry,
    ADD COLUMN vehicle_km_litro DECIMAL(6,2) NULL AFTER vehicle_km_mensal,
    ADD COLUMN vehicle_preco_diesel DECIMAL(6,3) NULL AFTER vehicle_km_litro;
