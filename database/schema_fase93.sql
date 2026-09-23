-- Fase 93: link/cartao de vendas pessoal (ex: ecodiffusorebrasil.com.br/v/joao-silva) -- segunda
-- das 7 ferramentas premium aprovadas. Lead capturado nesse link cai direto no CRM do
-- Licenciado/Gestor/Vendedor dono do link, sem depender do roteamento por raio de 100km.
ALTER TABLE users ADD COLUMN public_slug VARCHAR(60) NULL UNIQUE AFTER whatsapp;
