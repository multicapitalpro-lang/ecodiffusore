-- Fase 81: quantidade de parcelas escolhida pelo comprador no checkout publico -- antes so ia
-- pra Asaas, agora fica salva aqui pra aparecer no card do Kanban (pedido explicito do usuario).
ALTER TABLE payments ADD COLUMN installments INT UNSIGNED NULL AFTER due_date;
