-- Fase 57: piso de preco por papel (Vendedor trabalha a R$4.290 por padrao; Gestor/Licenciado
-- podem vender abaixo disso, mas com aprovacao). Reaproveita a tabela approvals ja existente
-- (Fase 9), so acrescenta o preco real solicitado + o papel de quem pediu -- o campo antigo
-- requested_discount_pct continua preenchido (agora calculado a partir do novo piso de R$4.290,
-- nao mais do preco de tabela do produto) pra nao quebrar leitura de linhas antigas.
ALTER TABLE approvals
    ADD COLUMN requested_price DECIMAL(10,2) NULL AFTER requested_discount_pct,
    ADD COLUMN requester_role VARCHAR(20) NULL AFTER requested_price;
