-- Fase 83: treinamento obrigatorio do Vendedor organizado em MODULOS (pedido explicito do
-- usuario) -- Gerente/Admin cria o modulo, escolhe em qual modulo cada video entra e organiza a
-- sequencia (modulos entre si, e videos dentro de cada modulo) com botoes subir/descer.
CREATE TABLE seller_training_modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- module_id NULL = video "solto" (sem modulo) -- cobre os videos ja cadastrados antes desta fase,
-- que continuam aparecendo (bucket "Sem modulo") sem forcar uma migracao de dados.
ALTER TABLE seller_training_videos
    ADD COLUMN module_id INT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_training_video_module FOREIGN KEY (module_id) REFERENCES seller_training_modules(id) ON DELETE SET NULL;
