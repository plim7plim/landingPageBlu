-- Executar uma única vez na base existente, antes de usar arquivar/restaurar.
-- O arquivamento preserva status, dados e vínculo com o acesso Sou+Blu.
ALTER TABLE lp_parceiros ADD COLUMN archived_at VARCHAR(40) DEFAULT NULL;
